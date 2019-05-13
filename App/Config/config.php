<?php
/**
 * Created by PhpStorm.
 * User: wangjiapeng
 * Date: 2019/4/17
 * Time: 8:38 PM
 */
return [
    'vendor' => [
        'path' => dirname(dirname(__DIR__)) . '../vendor'
    ],
    'rabbitmq' => [
        'host'=>'127.0.0.1',
        'port'=>'5672',
        'login'=>'admin',
        'password'=>'admin123',
        'vhost' => '/'
    ]
];