<?php
namespace ManaPHP;

use ManaPHP\Amqp\ConnectionException;
use ManaPHP\Amqp\Exception as AmqpException;
use ManaPHP\Amqp\Message;

class Amqp extends Component implements AmqpInterface
{
    /**
     * @var string
     */
    protected $_uri;

    /**
     * @var \AMQPConnection
     */
    protected $_connection;

    /**
     * @var \AMQPChannel
     */
    protected $_channel;

    /**
     * @var \AMQPExchange[]
     */
    protected $_exchanges = [];

    /**
     * @var \AMQPQueue[]
     */
    protected $_queues = [];

    const MESSAGE_METADATA = '_metadata_';

    /**
     * Amqp constructor.
     *
     * @param string $uri
     */
    public function __construct($uri = null)
    {
        $this->_uri = $uri;

        $credentials = [];

        $query = [];

        if ($uri) {
            $parts = parse_url($uri);

            if ($parts['scheme'] !== 'amqp') {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                throw new AmqpException(['`:scheme` scheme is unknown: `:uri`', 'scheme' => $parts['scheme'], 'uri' => $uri]);
            }

            if (isset($parts['host'])) {
                $credentials['host'] = $parts['host'];
            }

            if (isset($parts['port'])) {
                $credentials['port'] = $parts['port'];
            }

            if (isset($parts['user'])) {
                $credentials['login'] = $parts['user'];
            }

            if (isset($parts['pass'])) {
                $credentials['password'] = $parts['pass'];
            }

            if (isset($parts['path'])) {
                $credentials['vhost'] = $parts['path'];
            }

            if (isset($parts['query'])) {
                /** @noinspection NonSecureParseStrUsageInspection */
                $query = parse_str($parts['query']);
            }
        }

        try {
            $this->_connection = new \AMQPConnection($credentials);

            /** @noinspection NotOptimalIfConditionsInspection */
            if (isset($query['persistent']) && $query['persistent']) {
                $r = $this->_connection->pconnect();
            } else {
                $r = $this->_connection->connect();
            }

            if (!$r) {
                throw new ConnectionException(['connect to `:uri` amqp broker failed', 'uri' => $this->_uri]);
            }
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new ConnectionException(['connect to `:uri` amqp broker failed: :error', 'uri' => $this->_uri, 'error' => $e->getMessage()]);
        }

        try {
            $this->_channel = new \AMQPChannel($this->_connection);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new ConnectionException(['create channel with `:uri` uri failed: :error', 'uri' => $this->_uri, 'error' => $e->getMessage()]);
        }
        try {
            $this->_exchanges[''] = new \AMQPExchange($this->_channel);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException('create default exchange instance failed');
        }

        if (isset($query['prefetch_count'])) {
            $this->qos($query['prefetch_count']);
        }
    }

    /**
     * @return \AMQPChannel
     */
    public function getChannel()
    {
        return $this->_channel;
    }

    /**
     * @param int $count
     * @param int $size
     *
     * @return static
     */
    public function qos($count, $size = 0)
    {
        try {
            $this->_channel->qos($size, $count);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException('set the Quality Of Service settings for the channel failed');
        }

        return $this;
    }

    /**
     * @param string $name
     * @param int    $flags support the following flags: AMQP_DURABLE, AMQP_PASSIVE.
     * @param string $type
     *
     * @return \AMQPExchange
     */
    public function declareExchange($name, $type = AMQP_EX_TYPE_DIRECT, $flags = AMQP_DURABLE)
    {
        if (isset($this->_exchanges[$name])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['declare `:exchange` exchange failed: it is exists already', 'exchange' => $name]);
        }

        try {
            $exchange = new \AMQPExchange($this->_channel);

            $exchange->setName($name);
            $exchange->setType($type);
            $exchange->setFlags($flags);

            if (!$exchange->declareExchange()) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                throw new AmqpException(['declare `:exchange` exchange failed', 'exchange' => $name]);
            }
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['declare `:exchange` exchange failed: `:error`', 'exchange' => $name, 'error' => $e->getMessage()]);
        }

        $this->_exchanges[$name] = $exchange;

        return $exchange;
    }

    /**
     * @param bool $name_only
     *
     * @return \AMQPExchange[]|string[]
     */
    public function getExchanges($name_only = true)
    {
        if ($name_only) {
            return array_keys($this->_exchanges);
        } else {
            return $this->_exchanges;
        }
    }

    /**
     * @param string $name
     * @param int    $flags Optionally AMQP_IFUNUSED can be specified to indicate the exchange should not be deleted until no clients are connected to it.
     *
     * @return static
     */
    public function deleteExchange($name, $flags = AMQP_NOPARAM)
    {
        if (!isset($this->_exchanges[$name])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['delete `:exchange` exchange failed: it is NOT exists', 'exchange' => $name]);
        }

        try {
            $this->_exchanges[$name]->delete($flags);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['delete `:exchange` exchange failed: :error', 'exchange' => $name, 'error' => $e->getMessage()]);
        }

        unset($this->_exchanges[$name]);

        return $this;
    }

    /**
     * @param string $name
     * @param int    $flags
     *
     * @return \AMQPQueue
     */
    public function declareQueue($name, $flags = AMQP_DURABLE)
    {
        if (isset($this->queues[$name])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['declare `:queue` queue failed: it is exists already', 'queue' => $name]);
        }

        try {
            $queue = new \AMQPQueue($this->_channel);

            $queue->setName($name);
            $queue->setFlags($flags);

            $queue->declareQueue();
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['declare `:queue` queue failed: `:error`', 'queue' => $name, 'error' => $e->getMessage()]);
        }

        $this->_queues[$name] = $queue;

        return $queue;
    }

    /**
     * @param bool $name_only
     *
     * @return \AMQPQueue[]|string[]
     */
    public function getQueues($name_only = true)
    {
        if ($name_only) {
            return array_keys($this->_queues);
        } else {
            return $this->_queues;
        }
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $binding_key
     *
     * @return static
     */
    public function bindQueue($queue, $exchange, $binding_key = '')
    {
        if (!isset($this->_queues[$queue])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['bind `:queue` queue to `:exchange` exchange with `:binding_key` binding key failed: queue is NOT exists',
                'queue' => $queue,
                'exchange' => $exchange,
                'binding_key' => $binding_key]);
        }

        if (!isset($this->_exchanges[$exchange])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['bind `:queue` queue to `:exchange` exchange with `:binding_key` binding key failed: exchange is NOT exists',
                'queue' => $queue,
                'exchange' => $exchange,
                'binding_key' => $binding_key]);
        }

        try {
            $this->_queues[$queue]->bind($exchange, $binding_key);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['bind `:queue` queue to `:exchange` exchange with `:binding_key` binding key failed: :error',
                'queue' => $queue,
                'exchange' => $exchange,
                'binding_key' => $binding_key,
                'error' => $e->getMessage()]);
        }

        return $this;
    }

    /**
     *  Purge the contents of a queue
     *
     * @param string $name
     *
     * @return static
     */
    public function purgeQueue($name)
    {
        if (!isset($this->_queues[$name])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['purge `:queue` queue failed: it is NOT exists', 'queue' => $name]);
        }

        try {
            $this->_queues[$name]->purge();
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['purge `:queue` queue failed: error', 'queue' => $name, 'error' => $e->getMessage()]);
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function deleteQueue($name)
    {
        if (!isset($this->queues)) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['delete `:queue` queue failed: it is not exists', 'queue' => $name]);
        }

        try {
            $this->_queues[$name]->delete();
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['delete `:queue` queue failed: error', 'queue' => $name, 'error' => $e->getMessage()]);
        }

        unset($this->_queues[$name]);

        return $this;
    }

    /**
     * @param string $message
     * @param string $exchange
     * @param string $routing_key
     * @param int    $flags One or more of AMQP_MANDATORY and AMQP_IMMEDIATE
     * @param array  $attributes
     *
     * @return static
     */
    public function publishMessage($message, $exchange, $routing_key = '', $flags = AMQP_NOPARAM, $attributes = [])
    {
        if (!isset($this->_exchanges[$exchange])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['publish message to `:exchange` exchange with `:routing_key` routing_key failed: exchange is NOT exists',
                'exchange' => $exchange, 'routing_key' => $routing_key]);
        }

        try {
            $this->_exchanges[$exchange]->publish($message, $routing_key, $flags, $attributes);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['publish message to `:exchange` exchange with `:routing_key` routing_key failed: `:error`',
                'exchange' => $exchange, 'routing_key' => $routing_key, 'error' => $e->getMessage()]);
        }

        return $this;
    }

    /**
     * @param array|\JsonSerializable $message
     * @param string                  $exchange
     * @param string                  $routing_key
     * @param int                     $flags One or more of AMQP_MANDATORY and AMQP_IMMEDIATE
     * @param array                   $attributes
     *
     * @return static
     */
    public function publishJsonMessage($message, $exchange, $routing_key = '', $flags = AMQP_NOPARAM, $attributes = [])
    {
        $attributes['content_type'] = 'application/json';

        return $this->publishMessage(json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $exchange, $routing_key, $flags, $attributes);
    }

    /**
     * @param string $queue
     * @param bool   $auto_ack
     *
     * @return false|\ManaPHP\Amqp\Message
     */
    public function getMessage($queue, $auto_ack = false)
    {
        if (!isset($this->_queues[$queue])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['retrieve message from queue failed: `:queue` queue is NOT exists`', 'queue' => $queue]);
        }

        try {
            $envelope = $this->_queues[$queue]->get($auto_ack ? AMQP_AUTOACK : AMQP_NOPARAM);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['retrieve message from `:queue` queue failed: :error ', 'queue' => $queue, 'error' => $e->getMessage()]);
        }

        return $envelope === false ? false : new Message($this, $queue, $envelope);
    }

    /**
     * @param string $queue
     * @param bool   $auto_ack
     *
     * @return false|array
     */
    public function getJsonMessage($queue, $auto_ack = false)
    {
        if (!isset($this->_queues[$queue])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['retrieve message from queue failed: `:queue queue is NOT exists`', 'queue' => $queue]);
        }

        try {
            $envelope = $this->_queues[$queue]->get($auto_ack ? AMQP_AUTOACK : AMQP_NOPARAM);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['retrieve message from `:queue` queue failed: :error ', 'queue' => $queue, 'error' => $e->getMessage()]);
        }

        if ($envelope !== false) {
            $json = json_decode($envelope->getBody(), true);
            if ($json === null) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                throw new AmqpException(['json_decode `:queue` queue `:message` message failed: :error',
                    'queue' => $queue, 'message' => $envelope->getBody(), 'error' => json_last_error_msg()]);
            }

            $json[self::MESSAGE_METADATA] = ['queue' => $queue, 'delivery_tag' => $envelope->getDeliveryTag(), 'is_redelivery' => $envelope->isRedelivery()];

            return $json;
        } else {
            return false;
        }
    }

    /**
     * @param \ManaPHP\Amqp\Message|array $message
     * @param bool                        $multiple
     *
     * @return static
     */
    public function ackMessage($message, $multiple = false)
    {
        if (is_array($message)) {
            if (!isset($message[self::MESSAGE_METADATA])) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                throw new AmqpException(['ack message failed: message not contians metadata information']);
            }
            $queue = $message[self::MESSAGE_METADATA]['queue'];
            $delivery_tag = $message[self::MESSAGE_METADATA]['delivery_tag'];
        } else {
            $queue = $message->getQueue();
            $delivery_tag = $message->getDeliveryTag();
        }

        if (!$this->_queues[$queue]) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['ack message failed: `:queue` queue is NOT exists', 'queue' => $queue]);
        }
        try {
            $this->_queues[$queue]->ack($delivery_tag, $multiple ? AMQP_MULTIPLE : AMQP_NOPARAM);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['ack `:queue` queue message failed: error', 'queue' => $queue, 'error' => $e->getMessage()]);
        }

        return $this;
    }

    /**
     * @param \ManaPHP\Amqp\Message|array $message
     * @param bool                        $multiple
     *
     * @return static
     */
    public function nackMessage($message, $multiple = false)
    {
        if (is_array($message)) {
            if (!isset($message[self::MESSAGE_METADATA])) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                throw new AmqpException(['ack message failed: message not contains metadata information']);
            }
            $queue = $message[self::MESSAGE_METADATA]['queue'];
            $delivery_tag = $message[self::MESSAGE_METADATA]['delivery_tag'];
        } else {
            $queue = $message->getQueue();
            $delivery_tag = $message->getDeliveryTag();
        }

        if (!$this->_queues[$queue]) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['nack message failed: `:queue` queue is NOT exists', 'queue' => $queue]);
        }
        try {
            $this->_queues[$queue]->nack($delivery_tag, $multiple ? AMQP_MULTIPLE : AMQP_NOPARAM);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['nack `:queue` queue message failed: error', 'queue' => $queue, 'error' => $e->getMessage()]);
        }

        return $this;
    }

    /**
     * @param string   $queue
     * @param callable $callback
     * @param int      $flags
     *
     * @return void
     */
    public function consumeMessages($queue, $callback, $flags = AMQP_NOPARAM)
    {
        if (!isset($this->_queues[$queue])) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException(['consume message from queue failed: `:queue queue is NOT exists`', 'queue' => $queue]);
        }

        try {
            $this->_queues[$queue]->consume(function (\AMQPEnvelope $envelope) use ($callback, $queue) {
                return $callback(new Message($this, $queue, $envelope));
            }, $flags);
        } catch (\Exception $e) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new AmqpException('consume `:queue` queue message faield: ', $e->getMessage());
        }
    }
}