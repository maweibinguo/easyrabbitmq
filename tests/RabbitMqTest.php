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
            $instance->pushToDirect(time(), "", "ma_test3");
        }

    }
