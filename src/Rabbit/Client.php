<?php

namespace App\Rabbitmq\Rabbit;

use Closure;
use Rabbitmq;
use Exception;
use Throwable;
use ErrorException;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use Illuminate\Database\Eloquent\Model;
use PhpAmqpLib\Channel\AbstractChannel;
use App\Rabbitmq\Contracts\RabbitContract;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class Client implements RabbitContract
{
    const QUEUE_FROM = 0;
    const QUEUE_TO = 9;
    /**
     * @var AbstractChannel|AMQPChannel $channel
     */
    protected $channel;

    /**
     * @var AbstractChannel|AMQPChannel $rpcChannel
     */
    protected $rpcChannel;

    /**
     * @var mixed|string|null
     */
    protected $callback = '';

    /**
     * @var AMQPStreamConnection $connection
     */
    protected $connection;

    /**
     * @var AMQPStreamConnection $rpcConnection
     */
    protected $rpcConnection;

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
     * It can be multiple queue by default. It means, the default queue can be multiplied by 10.
     * Ex: default queue is hello-world and it can be multiplied by 10:
     * hello-world_0
     * hello-world_1
     * hello_world..
     * hello_world_9
     * @var bool $isMultiQueue
     */
    private bool $isMultiQueue = true;

    /**
     * @var string $correlation_id
     */
    private string $correlation_id = '';

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
            $this->setQueue($queue);
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
        info('------- BEGIN RPC REQUEST -------');
        $this->viaRpc()->publish($queue);
        info('-1. message', [$this->message]);
        info('0. params', [$this->getParams()]);
        info('1. via rpc', [$this->isRpc()]);
        info('2. rpc channel', [$this->getRpcChannel()]);
        info('3. channel', [$this->getChannel()]);
        info('4. queue', [$queue]);
        info('5. callback queue', [$this->callback]);
        info('6. correlation id', [$this->correlation_id]);

        $this->setResultNull()->consumeRpc($this->callback, function (AMQPMessage $message) {
            info('7. correlation id', [$this->correlation_id]);
            if ($this->correlation_id !== $message->get('correlation_id')) {
                return;
            }

            $this->result = $this->unserialize($message->getBody());
            info('-66. income result', [$this->result]);

            $message->ack(true);

            info('-6. after ack');

            $this->stopRpc();
        });

        info('8. after consume rpc');

        $client = $this->waitRpc();

        info('------- END RPC REQUEST -------');

        return $client;
    }

    /**
     * @param string $queue
     * @param Closure $callback
     * @return $this
     */
    public function consume(string $queue, Closure $callback): Client
    {
        $callback = $callback->bindTo($this, get_class($this));

        $this->getChannel()->basic_consume(
            $queue,
            '',
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
     * @param Closure $callback
     * @return $this
     */
    public function consumeRpc(string $queue, Closure $callback): Client
    {
        $callback = $callback->bindTo($this, get_class($this));

        $this->getRpcChannel()->basic_consume(
            $queue,
            '',
            false,
            true,
            false,
            false,
            $callback
        );

        return $this;
    }

    /**
     * @param string|null $queue
     * @param string $exchange
     * @return $this
     * @throws Exception
     */
    public function publish(string $queue = null, string $exchange = ''): Client
    {
        $queue = $this->getQueue($queue);
        info('-11. queue rpc', [$queue]);
        $message = $this->getMessage();
        $channel = $this->getChannel();

        if ($this->isRpc()) {
            $channel = $this->getRpcChannel();
        }

        $channel->basic_publish($message, $exchange, $queue);

        return $this;
    }

    /**
     * @return $this
     */
    public function wait(): Client
    {
        while ($this->getChannel()->is_open()) {
            $this->getChannel()->wait();
        }

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function waitRpc(): Client
    {
        info('9. enter wait rpc');
        try {
            $channel = $this->getRpcChannel();
            info('10. rpc channel', [$channel]);

            while (! $this->result) {
                $channel->wait(null, false, config('amqp.channel_rpc_timeout'));
            }

            info('11. after wait rpc channel');

            return $this->stopRpc()->disableRpc();
        } catch (Throwable $exception) {
            info('13. exception throwed');
            $this->stopRpc()->disableRpc();
            info('14. all closed');
            info('15. exception message', [$exception->getMessage(), $exception]);
            info('------- END DISPATCH EVENTS -------');

            throw new Exception('Service is not responding');
        }
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function disableRpc(): Client
    {
        $this->rpc = false;

        return $this->resetRpc();
    }

    /**
     * @param AMQPMessage $message
     * @return Client
     * @throws Exception
     */
    public function dispatchEvents(AMQPMessage $message): Client
    {
        info('------- BEGIN DISPATCH EVENTS -------');
        try {
            /**
             * @var array $data
             */
            $data = $this->unserialize($message->getBody());
            info('1. received data', [$data]);

            app()->setLocale($this->extract('lang', $message));

            if ($this->shouldAuthenticate()) {
                $this->authenticate($this->extract('user_name', $message));
            }

            $result = Rabbitmq::dispatch(
                data_get($data, 'method', 'default'),
                array_merge(data_get($data, 'params', []), [config('amqp.device_parameter_name') => $this->extract('device', $message)])
            );

            info('2. after processing the message', [$result]);

            if (!($message->has('reply_to') && $message->has('correlation_id'))) {
                info('3. it is not rpc', [$message->get_properties()]);
                return $this;
            }

            info('4. after if condition reply_to');

            $client = $this->viaRpc()
                ->setChannel($message->getChannel())
                ->setParams(['correlation_id' => $message->get('correlation_id')])
                ->setMessage($result);

            info('5. after setting message', [$message->get_properties()]);

            if ($this->isMultiQueue()) {
                info('6. multi queue');

                $client = $client->disableMultiQueue()
                    ->publish($message->get('reply_to'))
                    ->enableMultiQueue();

                info('------- END DISPATCH EVENTS -------');

                return $client;
            }

            info('7. after multiqueue condition');

            $client = $client->publish($message->get('reply_to'))->disableRpc();

            info('8. set a message');

            info('------- END DISPATCH EVENTS -------');

            return $client;
        } catch (Throwable $exception) {
            info('9. exception message', [$exception->getMessage(), $exception]);
            info('------- END DISPATCH EVENTS -------');
            throw new Exception($exception->getMessage() ? $exception->getMessage() : 'Unknown error');
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
     * @return AMQPChannel|null
     */
    public function getChannel(): ?AMQPChannel
    {
        return $this->channel;
    }

    /**
     * @return AMQPChannel|null
     */
    public function getRpcChannel(): ?AMQPChannel
    {
        return $this->rpcChannel;
    }

    /**
     * @return AMQPStreamConnection|null
     */
    public function getRpcConnection(): ?AMQPStreamConnection
    {
        return $this->rpcConnection;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function stopRpc(): Client
    {
        optional($this->getRpcConnection())->close();
        optional($this->getRpcChannel())->close();

        info('12. after close rpc connection and channel');

        return $this;
    }

    /**
     * @return bool
     */
    protected function isRpc(): bool
    {
        return $this->rpc === true;
    }

    /**
     * @return bool
     */
    protected function isMultiQueue(): bool
    {
        return $this->isMultiQueue === true;
    }

    /**
     * @return bool
     */
    protected function isNotRpcChannelExists(): bool
    {
        return $this->getRpcChannel() === null;
    }

    /**
     * @return $this
     * @deprecated
     */
    public function open(): Client
    {
        return $this;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
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
     * @param AMQPChannel $channel
     * @return $this
     */
    public function setChannel(AMQPChannel $channel): Client
    {
        if ($this->rpc) {
            $this->rpcChannel = $channel;
        } else {
            $this->channel = $channel;
        }

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
     * @return $this
     */
    public function enableMultiQueue(): Client
    {
        $this->isMultiQueue = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableMultiQueue(): Client
    {
        $this->isMultiQueue = false;

        return $this;
    }

    /**
     * @param string $queue
     * @return Client
     */
    protected function setQueue(string $queue): Client
    {
        if ($this->isMultiQueue()) {
            foreach (range(self::QUEUE_FROM, self::QUEUE_TO) as $proc_num) {
                $this->queueDeclare($queue . '_' . $proc_num);
            }

            return $this;
        }

        return $this->queueDeclare($queue);
    }

    /**
     * @param string $queue
     * @return $this
     */
    private function queueDeclare(string $queue): Client
    {
        $this->getChannel()->queue_declare(
            $queue, false, true, false, false
        );

        return $this;
    }

    /**
     * @return Client
     */
    private function setResultNull(): Client
    {
        $this->result = '';

        return $this;
    }

    /**
     * @return $this
     */
    protected function setExclusiveQueue(): Client
    {
        $this->callback = head($this->getRpcChannel()->queue_declare(
            '', false, false, true, false
        ));

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
     * @param $message
     * @return string
     */
    public function serialize($message): string
    {
        return json_encode($message);
    }

    /**
     * @param $message
     * @return mixed
     */
    public function unserialize($message)
    {
        if ($this->isJson($message)) {
            return json_decode($message, true);
        }

        return $message;
    }

    /**
     * @param $data
     * @return bool|false
     */
    private function isJson($data): bool
    {
        if (is_array($data)) {
            return false;
        }

        return (bool) preg_match('/^({.+})|(\[{.+}])|(\[.*])$/', $data);
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
     * @throws Exception
     */
    protected function viaRpc(): Client
    {
        $this->rpc = true;

        return $this->resetRpc();
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function resetRpc(): Client
    {
        $this->rpcChannel = null;
        $this->rpcConnection = null;

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
    protected function configure(): Client
    {
        $this->setParams([
            'application_headers' => new AMQPTable([
                'user_name' => $this->user,
                'lang' => request()->getPreferredLanguage() ?? config('app.locale'),
                'device' => request()->header('X-Type', config('amqp.default_device'))
            ])
        ]);

        if ($this->isRpc() && $this->isNotRpcChannelExists()) {
            $this->correlation_id = uniqid('rpc_consumer_');

            $this->createRpc()->setExclusiveQueue()->setParams([
                'reply_to' => $this->callback, 'correlation_id' => $this->correlation_id
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
    public function setUser(string $name): Client
    {
        $this->user = $name;

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setColumn(string $name): Client
    {
        $this->column = $name;

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setGuard(string $name = null): Client
    {
        $this->guard = $guard ?? config('auth.defaults.guard');

        return $this;
    }

    /**
     * @return $this
     */
    public function createRpc(): Client
    {
        $this->rpcConnection = app('amqp');
        $this->rpcChannel = $this->rpcConnection->channel();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getQueue(string $queue = null): string
    {
        $queue = $queue ?? $this->defaultQueue;

        if (blank($queue)) {
            throw new Exception("Default queue or queue is not defined");
        }

        if ($this->isMultiQueue()) {
            return $this->generateMultiQueue($queue);
        }

        return $queue;
    }

    /**
     * @param string $queue
     * @return string
     */
    private function generateMultiQueue(string $queue): string
    {
        return $queue . '_' . substr(floor(microtime(true) * 1000), -1, 1);
    }

    /**
     * @return array|string|AMQPMessage
     */
    public function getMessage()
    {
        return new AMQPMessage($this->serialize($this->message), $this->configure()->getParams());
    }

    /**
     * @return AMQPStreamConnection
     */
    public function getConnection(): AMQPStreamConnection
    {
        return $this->connection;
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

        return $this->getModel()->{'where' . $column}($name)->first();
    }

    /**
     * @return void
     */
    protected function authenticate(string $name)
    {
        if ($user = $this->retrieveUserByName($name)) {
            return auth()->guard($this->guard)->login($user);
        }
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
