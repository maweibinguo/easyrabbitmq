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

## 使用
在这里我们推荐php脚本+supervisor结合使用，用以保证消费进程的可靠性、增强worker的消费能力！ 如果你还没有听说过supervisor，可以[点击这里](http://www.supervisord.org/introduction.html)了解

### 基本使用
这里我们通过一个简单的例子, 了解基本的使用方式

生产者推送:
```php
```

消费者消费:
```php
```

### 推送消息

#### 推送消息到直连交换机
```php


```
  
#### 推送消息到扇形交换机

#### 推送消息到主题交换机
  
### 消费消息

#### 订阅模式

##### 订阅模式下的可靠消费

#### 拉取模式

##### 拉取模式下的可靠消费



[ico-license]: https://img.shields.io/badge/License-MIT-blue
