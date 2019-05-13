<?php
namespace DemoQueue\Queue\Producer;
/**
 * Created by PhpStorm.
 * User: runBaby
 * Date: 2019/5/13
 * Time: 11:37 AM
 */

include_once __DIR__.'/ProducerClass.php';


$data['type'] = 1;
$data['data'] = 'Hello world!88888';

$exchange = 'demo_exchange_test11';
$queue = 'demo_queue_test11';

$Producer = new ProducerClass($exchange,$queue);
$result = $Producer::Producer($data);

var_dump($result);