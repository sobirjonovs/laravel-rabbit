<?php

namespace App\Rabbitmq\Rabbit;

use App\Rabbitmq\Contracts\RabbitContract;
use Closure;
use ErrorException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
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
     * Remote microservice user name
     * @var string $user
     */
    protected $user;

    /**
     * Local database column of the model
     * @var string $column
     */
    protected $column;

    /**
     * @var string $guard
     */
    protected $guard;

    /**
     * @var bool $rpc
     */
    protected $rpc = false;

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
     * Default queue will be used if request queue is not defined
     * @var string $defaultQueue
     */
    protected $defaultQueue = "";

    /**
     * Defines whether incoming message should be authorized
     * @var bool $authorize
     */
    protected $authorize = false;

    /**
     * @param AMQPStreamConnection $client
     * @throws Exception
     */
    public function __construct(AMQPStreamConnection $client)
    {
        $this->connection = $client;
        $this->channel = $this->connection->channel();
    }

    /**
     * @return Client
     * @throws Exception
     */
    public function init(): Client
    {
        foreach ($this->queues as $queue) {
            $this->setQueue($queue, false);
        }

        $this->setQos();

        return $this;
    }

    /**
     * @param string|null $queue
     * @return Client
     * @throws ErrorException
     * @throws Exception
     */
    public function request(string $queue = null): Client
    {
        if ($queue === null) {
            $queue = $this->getDefaultQueue();
        }

        if (blank($queue)) {
            throw new Exception("Default queue or queue is not defined");
        }

        $this->viaRpc()->publish($queue);

        $this->consume($this->callback, function (AMQPMessage $message) {
            $this->result = $message->getBody();

            if (isJson($this->result)) {
                $this->result = json_decode($this->result, true);
            }

            $message->ack(true);
            $this->stop();
        });

        $this->wait();

        return $this;
    }

    /**
     * @param string|array $text
     * @return AMQPMessage
     */
    protected function message($text): AMQPMessage
    {
        $message = is_array($text) ? json_encode($text) : $text;

        $this->configure();

        return new AMQPMessage($message, $this->params);
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
        $this->channel->basic_publish($this->message($this->message), $exchange, $queue);

        return $this;
    }

    /**
     * @return $this
     */
    public function wait(): Client
    {
        while ($this->channel->is_open()) {
            $this->channel->wait();
        }

        return $this;
    }

    /**
     * @param AMQPMessage $message
     * @return void
     */
    public function dispatchEvents(AMQPMessage $message)
    {
        /**
         * @var array $data
         */
        $data = json_decode($message->getBody(), true);

        if ($this->shouldAuthenticate()) {
            $this->authenticate($this->extract('user_name', $message));
        }

        $result = Rabbitmq::dispatch(
            data_get($data, 'method', 'default'),
            data_get($data, 'params', [])
        );

        if ($message->has('reply_to') && $message->has('correlation_id')) {
            $this->open()
                ->setParams(['correlation_id' => $message->get('correlation_id')])
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
        if (! $this->connection->isConnected()) {
            $this->connection->reconnect();
        }

        if (! $this->channel->is_open()) {
            $this->channel = $this->connection->channel();
        }

        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): Client
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    /**
     * @param $message
     * @return $this
     */
    public function setMessage($message): Client
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return $this
     */
    public function enableAuthentication(): Client
    {
        $this->authorize = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableAuthentication(): Client
    {
        $this->authorize = false;

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
            $queue, false, !$exclusive, $exclusive, false
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

    /**
     * @return $this
     */
    protected function viaRpc()
    {
        $this->rpc = true;

        return $this;
    }

    /**
     * @return bool
     */
    protected function shouldAuthenticate(): bool
    {
        return $this->authorize;
    }

    /**
     * @return $this
     */
    protected function configure()
    {
        $this->setParams(['application_headers' => new AMQPTable([
            'user_name' => $this->user
        ])]);

        if (true === $this->rpc) {
            $this->setQueue()->setParams([
                'reply_to' => $this->callback, 'correlation_id' => uniqid('corr_')
            ]);
        }

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setDefaultQueue(string $name): Client
    {
        $this->defaultQueue = $name;

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setUser(string $name)
    {
        $this->user = $name;

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setColumn(string $name)
    {
        $this->column = $name;

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setGuard(string $name = null)
    {
        $this->guard = $guard ?? config('auth.defaults.guard');

        return $this;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getDefaultQueue(bool $multi = true): string
    {
        if ($multi) {
            return $this->defaultQueue . '_' . substr(floor(microtime(true) * 1000), -1, 1);
        }

        return $this->defaultQueue;
    }

    /**
     * @return Model
     */
    private function getModel()
    {
        return auth()->guard($this->guard)->getProvider()->createModel();
    }

    /**
     * @return Model
     */
    private function retrieveUserByName(string $name)
    {
        $column = ucfirst(strtolower($this->column));

        return $this->getModel()->{ 'where' . $column }($name)->first();
    }

    /**
     * @return void
     */
    protected function authenticate(string $name)
    {
        return auth()->guard($this->guard)->login($this->retrieveUserByName($name));
    }

    /**
     * @param string $key
     * @param AMQPMessage $message
     * @return array|mixed
     */
    protected function extract(string $key, AMQPMessage $message)
    {
        return data_get($message->get('application_headers')->getNativeData(), $key);
    }
}
