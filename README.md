# RabbitMQ_Delay
RabbitMQ delayed message queues
RabbitMQ encapsulates delay queue, implements queue-based TTL and dead letter mechanism, and handles timeout;

【中文】
简介：

 案例一： RabbitMQ封装延时队列，基于队列的TTL和死信机制实现，超时处理；
 
 案例二： 消息可靠性处理（Queue）,防止消息丢失，信息丢失存在三端：
 
         生产者：1、网络波动，消息未到达队列服务；
                2、消息到达队列服务，但未持久化，异常宕机；
         解决： confirm 机制，消息 ack
         
         RabbitMQ服务：1、宕机，消息丢失未做持久化；
         解决：开启 消息持久 持久化 
         
         消费者： 已领取消息，但服务不稳定未处理完，宕机；
         解决：  confirm 机制，消息 ack



运行：

     
     1、直接  git clone

     2、自行下载安装PHP amqplib客户端库安装

     composer require php-amqplib/php-amqplib


概念：
   死信机制：
    
       1、消息被拒绝，并且设置 requeue 参数为 false；
       2、消息过期
       3、队列达到最大长度 ：对队列中消息的条数进行限制  x-max-length、对队列中消息的总量进行限制  x-max-length-bytes


交流：

    QQ    ：1364655420 
    WeChat：wangadmin12345678
