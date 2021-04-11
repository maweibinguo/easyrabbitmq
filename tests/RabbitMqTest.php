<?php
    /**
     * User: ${user}
     * Date: 2021-04-10
     * Email: mawb@xiaoma.cn
     */

    namespace Tests;

    use Maweibinguo\EasyRabbitMq\RabbitMq;
    use PHPUnit\Framework\TestCase;

    class RabbitMqTest extends TestCase
    {
        public function testPushToDirect()
        {
            $instance = RabbitMq::getInstance([]);
            $instance->pushToDirect(time(), "easy_direct_exchange", "direct_test_queue", 31536000000);
            //$instance->pushToDirect(time(), "easy_direct_exchange", "direct_test_queue");
            $this->assertTrue(true);
        }

        public function testConsume()
        {
            $instance = RabbitMq::getInstance([]);
            $instance->consume("direct_test_queue",
                $consumerTag = "c1",
                $exchange = "easy_direct_exchange",
                $bindingKey = "direct_test_queue",
                $callback = function($msg){
                    $body = $msg->body;
                    file_put_contents("./test.log", "time => " . time() . "\t" . " body => " . $body . PHP_EOL , FILE_APPEND);
                    return false;
                },
                $failedQueue = "easymq@failed"
            );
            $this->assertTrue(true);
        }


        public function testPushToFanout()
        {
            $instance = RabbitMq::getInstance([]);
            $instance->pushToFanout(time(), "easy_fanout_exchange", 30);
            $instance->pushToFanout(time(), "easy_fanout_exchange" );
            $this->assertTrue(true);
        }

        public function testGet()
        {
            $instance = RabbitMq::getInstance([]);
            $instance->get(
                $queue = "get_queue",
                $exchange = "easy_fanout_exchange",
                $bindingKey = "",
                $callback = function($msg){
                    $body = $msg->body;
                    file_put_contents("./test.log", "time => " . time() . "\t" . " body => " . $body . PHP_EOL , FILE_APPEND);
                    return false;
                },
                $failedQueue = 'easymq@failed'
            );
        }

    }
