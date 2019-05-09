<?php
namespace DemoQueue\Producer;
/**
* Created by PhpStorm.
* User: 奔跑吧笨笨
* Date: 2019/5/6
* Time: 1:41 PM
*/
include_once __DIR__.'/ProducerClass.php';


$data['type'] = 1;
$data['data'] = 'Hello world！87654321';

$producer = new ProducerClass();
$result = $producer->producers($data);

var_dump($result);