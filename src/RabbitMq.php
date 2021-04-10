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
            "channelMaxNum" => 10,
            "insist" => false,
            "login_method" => 'AMQPLAIN',
            "login_response" => null,
            "locale" => "en_US",
            "connection_timeout" => 3.0,
            "read_write_timeout" => 130.0,
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
         */
        private function getChannel()
        {
            if (!isset(self::$connection)) {
                extract(self::$config);
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

                if (count(self::$channelPoolList) < self::$config['channelMaxNum']) {
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
        }

        /**
         * @param string $msg
         * @param string $exchange
         * @param string $queue
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
                $channel->queue_declare(
                    $delayQueueName,
                    $passive = false,
                    $durable = true,
                    $exclusive = false,
                    $auto_delete = true,
                    $nowait = false,
                    $arguments = [
                        "x-dead-letter-exchange" => $exchange,
                        "x-dead-letter-routing-key" => $routingKey
                    ],
                    $ticket = null
                );
                $channel->queue_bind($delayQueueName, $exchange, $delayQueueName);
                $msgObj->set("expiration", $delaySec * 1000);
                $channel->basic_publish(
                    $msgObj,
                    $exchange,
                    $delayQueueName,
                    $mandatory = false,
                    $immediate = false,
                    $ticket = null
                );
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
         */
        public function pushToFanout($msg = '', $exchange = '', $delaySec = 0)
        {

        }

        /**
         * @param string $msg
         * @param string $routingKey
         * @param string $exchange
         * @param int $delaySec
         */
        public function pushToTopic($msg = '', $routingKey = '', $exchange = '', $delaySec = 0)
        {

        }

        /**
         * @param string $queue
         * @param string $bindingKey
         * @param null $callBack
         */
        public function consume($queue = '', $bindingKey = '', $callBack = null)
        {

        }

        /**
         * @param string $queue
         * @param null $callBack
         */
        public function get($queue = '', $callBack = null)
        {

        }
    }