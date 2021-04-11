[![Software License][ico-license]](LICENSE)


## easy-rabbitmq 包简介 ##
对php-amqplib/php-amqplib包的二次封装，为常见功能提供一套开箱即用的生产解决方案
。目前支持的功能列表如下：
* 推送消息到直连交换机(含延迟消息)
* 推送消息到扇形交换机(含延迟消息)
* 推送消息到主题交换机(含延迟消息)
* 订阅模式下的可靠消费, 消费者消费失败后将会尝试继续消费，最多尝试5次。
* 拉取模式下的可靠消费, 消费者消费失败后将会尝试继续消费，最多尝试5次。

如果还有其它场景，欢迎继续补充，随后进行迭代！！

## 要求
* 安装包对PHP版本对要求主要取决于php-amqplib/php-amqplib包本身对要求,这里为了兼顾php5.0的使用者,我们使用了php-amqplib/php-amqplib包V2.9.0的版本。
具体的要求参照[这里](https://packagist.org/packages/php-amqplib/php-amqplib#v2.9.0)。
不过笔者推荐使用php7.0及其以上版本, 这个开发包也是在7.0这个版本上面开发完成的！

## 安装
```php

```

## 使用
在这里我们推荐php脚本+supervisor结合使用，用以保证消费进程的可靠性、增强worker的消费能力！ 如果你还没有听说过supervisor，可以[点击这里](http://www.supervisord.org/introduction.html)了解.

生产者推送:
### 推送消息

#### 推送消息到直连交换机
```php
	$config = [
	    "host" => "127.0.0.1",
            "port" => "5672",
            "user" => "guest",
            "password" => "guest",
            "vhost" => "/",
            "channel_max_num" => 10,
	];	
	$instance = RabbitMq::getInstance($config);
	
	//延迟消息,30 秒中后才会到达指定的交换机
	$instance->pushToDirect(
				$msg = time(), //消息体内容
				$exchange = "easy_direct_exchange", //交换机名称
				$routingKey = "direct_test_queue", //消息的routingkey，consume方法到bingdingkey 要和routingkey保持一致
				$delaySec = 30 //延迟秒数
	);

	//无延迟，推入到指定到直链交换机
	$instance->pushToDirect(
				$msg = time(), //消息体内容
				$exchange = "easy_direct_exchange", //交换机名称
				$routingKey = "direct_test_queue", //消息的routingkey，consume方法到bingdingkey 要和routingkey保持一致
	);
```
  
#### 推送消息到扇形交换机
```php
	$config = [
	    "host" => "127.0.0.1",
            "port" => "5672",
            "user" => "guest",
            "password" => "guest",
            "vhost" => "/",
            "channel_max_num" => 10,
	];	
	$instance = RabbitMq::getInstance($config);
	
	//延迟消息,30 秒中后才会到达指定的交换机
	$instance->pushToFanout(
				$msg = time(), //消息体内容
				$exchange = "easy_fanout_exchange", //交换机名称
				$delaySec = 30 //延迟秒数
	);

	//无延迟，推入到指定到直链交换机
	$instance->pushToFanout(
				$msg = time(), //消息体内容
				$exchange = "easy_fanout_exchange" //交换机名称
	);
```

#### 推送消息到主题交换机
```php
	$config = [
	    "host" => "127.0.0.1",
            "port" => "5672",
            "user" => "guest",
            "password" => "guest",
            "vhost" => "/",
            "channel_max_num" => 10,
	];	
	$instance = RabbitMq::getInstance($config);
	
	//延迟消息,30 秒中后才会到达指定的交换机
	$instance->pushToTopic(
				$msg = time(), //消息体内容
				$exchange = "easy_topic_exchange", //交换机名称
				$routingKey = "",
				$delaySec = 30 //延迟秒数
	);

	//无延迟，推入到指定到直链交换机
	$instance->pushToTopic(
				$msg = time(), //消息体内容
				$exchange = "easy_topic_exchange", //交换机名称
				$routingKey = ""
	);
```
  
### 消费消息

#### 订阅模式

##### 订阅模式下的可靠消费
```php
	$config = [
	    "host" => "127.0.0.1",
            "port" => "5672",
            "user" => "guest",
            "password" => "guest",
            "vhost" => "/",
            "channel_max_num" => 10,
	];	
	$instance = RabbitMq::getInstance($config);
	$instance->consume(
		$queueName = "direct_test_queue",//订阅的队列名称
		$consumerTag = "c1",//消费标记
		$exchange = "easy_direct_exchange",//交换机名称
		$bindingKey = "direct_test_queue",//bindingkey，如果是直链交换机需要同routingKey保持一致
		$callback = function($msg){
		    $body = $msg->body;
		    file_put_contents("./test.log", "time => " . time() . "\t" . " body => " . $body . PHP_EOL , FILE_APPEND);
		    //如果返回结果不绝对等于(===)true,那么将触发消息重试机制
		    return false;
		},
		//5次消费消费失败后，失败消息将会投递到的队列名称
		$failedQueue = "easymq@failed"
	);
```

#### 拉取模式

##### 拉取模式下的可靠消费
```php
	$config = [
	    "host" => "127.0.0.1",
            "port" => "5672",
            "user" => "guest",
            "password" => "guest",
            "vhost" => "/",
            "channel_max_num" => 10,
	];	
	$instance = RabbitMq::getInstance($config);
	$instance->get(
		$queue = "get_queue",
		$exchange = "easy_fanout_exchange",
		$bindingKey = "",
		$callback = function($msg){
		    $body = $msg->body;
		    file_put_contents("./test.log", "time => " . time() . "\t" . " body => " . $body . PHP_EOL , FILE_APPEND);
		    //如果返回结果不绝对等于(===)true,那么将触发消息重试机制
		    return false;
		},
		//5次消费消费失败后，失败消息将会投递到的队列名称
		$failedQueue = 'easymq@failed'
    	);
```

[ico-license]: https://img.shields.io/badge/License-MIT-blue
