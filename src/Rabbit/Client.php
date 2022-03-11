<?php

namespace App\Rabbitmq\Rabbit;

use App\Rabbitmq\Contracts\RabbitContract;
use Closure;
use ErrorException;
use Exception;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Rabbitmq;

class Client implements RabbitContract
{
    /**
     * @var AbstractChannel|AMQPChannel $channel
     */
    protected $channel;

    /**
     * @var mixed|string|null
     */
    protected $callback = '';

    /**
     * @var AMQPStreamConnection $connection
     */
    protected $connection;

    /**
     * @var string|array $result
     */
    protected $result;

    /**
     * @var string|array|AMQPMessage $message
     */
    protected $message = '';

    /**
     * @var bool $isRpc
     */
    protected $isRpc;

    /**
     * @var array $params
     */
    protected $params = [];

    /**
     * Queue names to be declared
     * @var array $queues
     */
    protected $queues = [];

    /**
     * @param AMQPStreamConnection $client
     * @throws Exception
     */
    public function __construct(AMQPStreamConnection $client)
    {
        $this->connection = $client;
        $this->channel = $this->connection->channel();

        $this->init();
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function init()
    {
        foreach ($this->queues as $queue) {
            $this->setQueue($queue, false);
        }

        $this->setQos();
    }

    /**
     * @param string $queue
     * @return Client
     * @throws ErrorException
     */
    public function request(string $queue = 'queue'): Client
    {
        $this->consume($this->callback, function (AMQPMessage $message) {
            $this->result = $message->getBody();

            if (isJson($this->result)) {
                $this->result = json_decode($this->result, true);
            }

            $message->ack();
            $this->stop();
        });

        $this->publish($queue);

        $this->wait();

        return $this;
    }

    /**
     * @param string|array $text
     * @param array|null $parameters
     * @return AMQPMessage
     */
    public function message($text, array $parameters = []): AMQPMessage
    {
        $message = is_array($text) ? json_encode($text) : $text;

        if ($this->isRpc && empty($parameters)) {
            $this->setQueue();

            $parameters = array_merge($parameters, [
                'reply_to' => $this->callback,
                'correlation_id' => uniqid('corr_')
            ]);
        }

        return new AMQPMessage($message, $parameters);
    }

    /**
     * @param string $queue
     * @param Closure $callback
     * @return $this
     */
    public function consume(string $queue, Closure $callback): Client
    {
        $callback = $callback->bindTo($this, get_class($this));

        $this->channel->basic_consume(
            $queue,
            uniqid('consumer_'),
            false,
            false,
            false,
            false,
            $callback
        );

        return $this;
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @return $this
     */
    public function publish(string $queue, string $exchange = ''): Client
    {
        $this->channel->basic_publish($this->message, $exchange, $queue);

        return $this;
    }

    /**
     * @return $this
     * @throws ErrorException
     */
    public function wait(): Client
    {
        while ($this->channel->is_open()) {
            $this->channel->wait();
        }

        return $this;
    }

    public function dispatchEvents(AMQPMessage $message)
    {
        /**
         * @var array $data
         */
        $data = json_decode($message->getBody(), true);

        $result = Rabbitmq::dispatch(
            data_get($data, 'method', 'default'),
            data_get($data, 'params', [])
        );

        if ($message->has('reply_to') && $message->has('correlation_id')) {
            $this->setParams(['correlation_id' => $message->get('correlation_id')])
                ->setMessage($result)
                ->publish($message->get('reply_to'));
        }
    }

    /**
     * @return array|string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function stop(): Client
    {
        $this->channel->close();
        $this->connection->close();

        return $this;
    }

    /**
     * @return $this
     */
    public function open(): Client
    {
        $this->connection->getConnection()->reconnect();

        return app(Client::class);
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): Client
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @param $message
     * @param bool $isRpc
     * @return $this
     */
    public function setMessage($message, bool $isRpc = true): Client
    {
        $this->isRpc = $isRpc;
        $this->message = $this->message($message, $this->params);

        return $this;
    }

    /**
     * @param string $queue
     * @param bool $exclusive
     * @return Client
     */
    public function setQueue(string $queue = '', bool $exclusive = true): Client
    {
        $queue = $this->channel->queue_declare(
            $queue, false, ! $exclusive, $exclusive, false
        );

        if ($exclusive) {
            $this->callback = array_shift($queue);
        }

        return $this;
    }

    /**
     * @return Client
     */
    protected function setQos(): Client
    {
        $this->channel->basic_qos(
            config('amqp.qos_prefetch_size'),
            config('amqp.qos_prefetch_count'),
            config('amqp.qos_a_global')
        );

        return $this;
    }

    /**
     * @param array $names
     * @return $this
     */
    public function addQueues(array $names): Client
    {
        $this->queues = $names;

        return $this;
    }
}
