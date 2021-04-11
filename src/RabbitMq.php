<?php

    /**
     * User: maweibinguo
     * Date: 2021-04-10
     * Email: mawb@xiaoma.cn
     */

    namespace Maweibinguo\EasyRabbitMq;

    use PhpAmqpLib\Channel\AMQPChannel;
    use PhpAmqpLib\Connection\AMQPStreamConnection;
    use PhpAmqpLib\Exchange\AMQPExchangeType;
    use PhpAmqpLib\Message\AMQPMessage;
    use PhpAmqpLib\Wire\AMQPTable;

    class RabbitMq
    {
        /**
         * @var
         */
        private static $instance;

        /**
         * @var int
         */
        private static $ackNumber = 0;

        /**
         * @var array
         */
        private static $config = [
            "host" => "127.0.0.1",
            "port" => "5672",
            "user" => "guest",
            "password" => "guest",
            "vhost" => "/",
            "channel_max_num" => 10,
            "connection_timeout" => 3.0,
            "read_write_timeout" => 130.0,
            "insist" => false,
            "login_method" => 'AMQPLAIN',
            "login_response" => null,
            "locale" => "en_US",
            "context" => null,
            "keepalive" => false,
            "heartbeat" => 60,
            "channel_rpc_timeout" => 0.0,
            "ssl_protocol" => null
        ];

        /**
         * @var AMQPStreamConnection
         */
        private static $connection;

        /**
         * @var array
         */
        private static $channelPoolList = [];

        const MAX_RETRY_NUMBER = 5;

        /**
         * @param array $config
         * @return RabbitMq
         */
        public static function getInstance($config = [])
        {
            foreach (self::$config as $key) {
                if (isset($config[$key])) {
                    self::$config[$key] = $config[$key];
                }
            }

            if (isset(self::$instance)) {
                return self::$instance;
            } else {
                return self::$instance = new RabbitMq();
            }
        }

        public function __destruct()
        {
            if (self::$connection instanceof AMQPStreamConnection) {
                self::$connection->close();
            }
            /**
             * @var $channel AMQPChannel
             */
            if (count(self::$channelPoolList) > 0) {
                foreach (self::$channelPoolList as $channel) {
                    $channel->close();
                }
            }
        }

        /**
         * @return mixed
         * @todo一个进程之内获取的要是同一个channel
         */
        private function getChannel()
        {
            if (!isset(self::$connection)) {
                self::$connection = new AMQPStreamConnection(
                    self::$config["host"],
                    self::$config["port"],
                    self::$config["user"],
                    self::$config["password"],
                    self::$config["vhost"],
                    self::$config["insist"],
                    self::$config["login_method"],
                    self::$config["login_response"],
                    self::$config["locale"],
                    self::$config["connection_timeout"],
                    self::$config["read_write_timeout"],
                    self::$config["context"],
                    self::$config["keepalive"],
                    self::$config["heartbeat"],
                    self::$config["channel_rpc_timeout"],
                    self::$config["ssl_protocol"]
                );
            }

            if (count(self::$channelPoolList) < self::$config['channel_max_num']) {
                $channel = self::$connection->channel();
                $channel->confirm_select();
                $channel->set_nack_handler(function ($a) {
                });
                return self::$channelPoolList[] = $channel;
            } else {
                $index = array_rand(self::$channelPoolList, 1);
                return self::$channelPoolList[$index];
            }
        }

        /**
         * @param string $msg
         * @param string $delayQueueName
         * @param string $deadExchange
         * @param string $deadRoutingKey
         * @param int $delaySec
         */
        private function pushToDelayQueue(
            $msg = '',
            $delayQueueName = "",
            $deadExchange = "",
            $deadRoutingKey = "",
            $delaySec = 0
        )
        {
            $delayTime = $delaySec * 1000;
            if ($delayTime > PHP_INT_MAX) {
                $maxSec = (int)(PHP_INT_MAX / 1000);
                throw new EasyRabbitException(sprintf("delay seconds over the max : %s", $maxSec));
            }

            /**
             * @var $channel AMQPChannel
             */
            $channel = $this->getChannel();
            $amqpTable = new AMQPTable();
            $amqpTable->set('x-dead-letter-exchange', $deadExchange);
            $amqpTable->set('x-dead-letter-routing-key', $deadRoutingKey);
            $channel->queue_declare(
                $delayQueueName,
                $passive = false,
                $durable = true,
                $exclusive = false,
                $auto_delete = true,
                $nowait = false,
                $arguments = $amqpTable,
                $ticket = null
            );
            $msgObj = new AMQPMessage();
            $msgObj->setBody($msg);
            $msgObj->set("expiration", $delaySec * 1000);
            $channel->basic_publish(
                $msgObj,
                "",
                $delayQueueName,
                $mandatory = false,
                $immediate = false,
                $ticket = null
            );

        }

        /**
         * @param null $msgObj
         * @param string $retryQueueName
         * @param string $deadRoutingKey
         * @param string $failedQueueName
         * @return bool
         */
        private function pushToRetryQueue(
            $msgObj = null,
            $retryQueueName = "",
            $deadRoutingKey = "",
            $failedQueueName = ""
        )
        {
            $retryNumber = 1;
            if ($msgObj->has('application_headers')) {
                /* @var AMQPTable $properties */
                $properties = $msgObj->get('application_headers');
                $data = $properties->getNativeData();
                $retryNumber = isset($data['retry_nums']) && is_numeric($data['retry_nums']) ? $data['retry_nums'] : 1;
            }

            /**
             * @var $channel AMQPChannel
             */
            $channel = $this->getChannel();
            if ($retryNumber > self::MAX_RETRY_NUMBER) {
                $channel->queue_declare($failedQueueName,
                    $passive = false,
                    $durable = true,
                    $exclusive = false,
                    $auto_delete = false
                );
                $channel->basic_publish(
                    $msgObj,
                    $exchange = '',
                    $failedQueueName
                );
            } else {
                $amqpTable = new AMQPTable();
                $amqpTable->set('x-dead-letter-exchange', "");
                $amqpTable->set('x-dead-letter-routing-key', $deadRoutingKey);
                $channel->queue_declare(
                    $retryQueueName,
                    $passive = false,
                    $durable = true,
                    $exclusive = false,
                    $auto_delete = true,
                    $nowait = false,
                    $arguments = $amqpTable,
                    $ticket = null
                );
                $headers = new AMQPTable(['retry_nums' => $retryNumber + 1]);
                $msgObj->set('application_headers', $headers);
                $msgObj->set('expiration', pow(2, $retryNumber - 1) * 1000);
                $channel->basic_publish(
                    $msgObj,
                    "",
                    $retryQueueName,
                    $mandatory = false,
                    $immediate = false,
                    $ticket = null
                );
                return true;
            }
        }

        /**
         * @param string $msg
         * @param string $exchange
         * @param string $routingKey
         * @param int $delaySec
         * @return bool
         */
        public function pushToDirect($msg = '', $exchange = '', $routingKey = '', $delaySec = 0)
        {
            /**
             * @var $channel AMQPChannel
             */
            $channel = $this->getChannel();
            $msgObj = new AMQPMessage();
            $msgObj->setBody($msg);
            if (!empty($exchange)) {
                $channel->exchange_declare(
                    $exchange,
                    AMQPExchangeType::DIRECT,
                    $passive = false,
                    $durable = true,
                    $auto_delete = false,
                    $internal = false,
                    $nowait = false,
                    $arguments = array(),
                    $ticket = null
                );
            }

            if ($delaySec > 0) {
                $delayQueueName = $exchange . "@" . "delay";
                $this->pushToDelayQueue($msg,
                    $delayQueueName,
                    $exchange,
                    $routingKey,
                    $delaySec);
            } else {
                $channel->basic_publish(
                    $msgObj,
                    $exchange,
                    $routingKey,
                    $mandatory = false,
                    $immediate = false,
                    $ticket = null
                );
            }

            self::$ackNumber++;
            if (self::$ackNumber > 100) {
                $channel->wait_for_pending_acks();
                self::$ackNumber = 0;
            }

            return true;
        }

        /**
         * @param string $msg
         * @param string $exchange
         * @param int $delaySec
         * @return bool
         */
        public function pushToFanout($msg = '', $exchange = '', $delaySec = 0)
        {
            /**
             * @var $channel AMQPChannel
             */
            $channel = $this->getChannel();
            $channel->exchange_declare(
                $exchange,
                AMQPExchangeType::FANOUT,
                $passive = false,
                $durable = true,
                $auto_delete = false,
                $internal = false,
                $nowait = false,
                $arguments = array(),
                $ticket = null
            );
            $msgObj = new AMQPMessage();
            $msgObj->setBody($msg);
            if ($delaySec > 0) {
                $delayQueueName = $exchange . "@" . "delay";
                $this->pushToDelayQueue($msg,
                    $delayQueueName,
                    $exchange,
                    "",
                    $delaySec);
            } else {
                $channel->basic_publish(
                    $msgObj,
                    $exchange,
                    "",
                    $mandatory = false,
                    $immediate = false,
                    $ticket = null
                );
            }

            self::$ackNumber++;
            if (self::$ackNumber > 100) {
                $channel->wait_for_pending_acks();
                self::$ackNumber = 0;
            }

            return true;
        }

        /**
         * @param string $msg
         * @param string $routingKey
         * @param string $exchange
         * @param int $delaySec
         * @return bool
         */
        public function pushToTopic($msg = '',  $exchange = '', $routingKey = '', $delaySec = 0)
        {
            /**
             * @var $channel AMQPChannel
             */
            $channel = $this->getChannel();
            $channel->exchange_declare(
                $exchange,
                AMQPExchangeType::TOPIC,
                $passive = false,
                $durable = true,
                $auto_delete = false,
                $internal = false,
                $nowait = false,
                $arguments = array(),
                $ticket = null
            );
            $msgObj = new AMQPMessage();
            $msgObj->setBody($msg);
            if ($delaySec > 0) {
                $delayQueueName = $exchange . "@" . "delay";
                $this->pushToDelayQueue($msg,
                    $delayQueueName,
                    $exchange,
                    $routingKey,
                    $delaySec);
            } else {
                $channel->basic_publish(
                    $msgObj,
                    $exchange,
                    $routingKey,
                    $mandatory = false,
                    $immediate = false,
                    $ticket = null
                );
            }

            self::$ackNumber++;
            if (self::$ackNumber > 100) {
                $channel->wait_for_pending_acks();
                self::$ackNumber = 0;
            }

            return true;
        }

        /**
         * @param string $queue
         * @param string $consumerTag
         * @param string $exchange
         * @param string $bindingKey
         * @param callable|null $callback
         * @param string $failedQueue
         * @throws \ErrorException
         */
        public function consume($queue = "",
                                $consumerTag = "",
                                $exchange = "",
                                $bindingKey = "",
                                $callback = null,
                                $failedQueue = "easymq@failed")
        {
            /**
             * @var $channel AMQPChannel
             */
            $channel = $this->getChannel();

            $channel->queue_declare(
                $queue,
                $passive = false,
                $durable = true,
                $exclusive = false,
                $auto_delete = false,
                $nowait = false,
                $arguments = array(),
                $ticket = null
            );

            if (!empty($exchange)) {
                $channel->queue_bind(
                    $queue,
                    $exchange,
                    $bindingKey,
                    $nowait = false,
                    $arguments = array(),
                    $ticket = null
                );
            }

            $channel->basic_consume($queue,
                $consumerTag,
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($callback, $channel, $queue, $failedQueue) {
                    $ret = is_callable($callback) ? $callback($message) : true;
                    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
                    if ($ret !== true) {
                        $retryQueue = $queue . '@retry';
                        $this->pushToRetryQueue(
                            $message,
                            $retryQueue,
                            $queue,
                            $failedQueue
                        );
                    }

                    if ($message->body === 'quit') {
                        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
                    }
                });
            while (count($channel->callbacks)) {
                $channel->wait(null, false, self::$config["read_write_timeout"]);
            }
        }

        /**
         * @param string $queue
         * @param string $exchange
         * @param $bindingKey
         * @param null $callback
         * @param string $failedQueue
         * @return bool
         */
        public function get($queue = "", $exchange = "", $bindingKey = "", $callback = null, $failedQueue = 'easymq@failed')
        {
            $ret = true;

            /**
             * @var $channel AMQPChannel
             */
            $channel = $this->getChannel();

            $channel->queue_declare(
                $queue,
                $passive = false,
                $durable = true,
                $exclusive = false,
                $auto_delete = false,
                $nowait = false,
                $arguments = array(),
                $ticket = null
            );

            if (!empty($exchange)) {
                $channel->queue_bind(
                    $queue,
                    $exchange,
                    $bindingKey,
                    $nowait = false,
                    $arguments = array(),
                    $ticket = null
                );
            }

            $message = $channel->basic_get(
                $queue,
                $no_ack = false,
                $ticket = null
            );
            if (isset($message)) {
                $ret = is_callable($callback) ? $callback($message) : true;
                $channel->basic_ack($message->delivery_info['delivery_tag']);
                if ($ret !== true) {
                    $retryQueue = $queue . '@retry';
                    $this->pushToRetryQueue(
                        $message,
                        $retryQueue,
                        $queue,
                        $failedQueue
                    );
                }
            }

            return $ret;
        }
    }