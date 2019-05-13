<?php
namespace DemoQueue\Queue\Consumers;
/**
 * Created by PhpStorm.
 * User: 奔跑吧笨笨
 * Date: 2019/5/6
 * Time: 1:41 PM
 */
include_once __DIR__.'/ConsumersClass.php';


$exchange = 'demo_exchange_test11';    //延迟交换机
$queue = 'demo_queue_test11';          //延迟队列

$consumers = new ConsumersClass($exchange,$queue);

$consumers::consumersClient();