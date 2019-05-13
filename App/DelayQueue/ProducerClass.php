<?php
namespace DemoQueue\Producer;
/**
 * Created by PhpStorm.
 * User: 奔跑吧笨笨
 * Date: 2019/5/6
 * Time: 1:04 PM
 */
date_default_timezone_set("Asia/Shanghai");
require_once __DIR__.'/../../vendor/autoload.php';

use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;


class ProducerClass
{

   static protected $rabbit_host;
   static protected $rabbit_port;
   static protected $rabbit_login;
   static protected $rabbit_pwd;
   static protected $rabbit_vhost;

   static protected $connection;
   static protected $channel;

   static protected $business_url;

   static protected $rabbit_err_code = 500;
   static protected $rabbit_success_code = 200;

   static protected $life_time = 600000;                   //单位/ms  10分钟

   static protected $cache_routing;
   static protected $delay_routing;

   static protected $config;

    const DELAY_EXCHANGE = 'delay_exchange_test2';        //延迟交换机
    const CACHE_EXCHANGE = 'cache_echange_test2';         //缓存交换机
    const EXCHANGE_MODEL = 'direct';                //交换机模式

    const DELAY_QUEUE = 'delay_queue_test2';              //延迟队列
    const CACHE_QUEUE = 'cache_queue_test2';            //缓存队列

    public function __construct()
    {
        self::$config = include_once __DIR__."/../Config/config.php";

        self::$config = self::$config['rabbitmq'];
        self::$rabbit_host = self::$config['host'];
        self::$rabbit_port = self::$config['port'];
        self::$rabbit_login = self::$config['login'];
        self::$rabbit_pwd = self::$config['password'];
        self::$rabbit_vhost = self:: $config['vhost'];

        self::$connection = new AMQPStreamConnection(self::$rabbit_host, self::$rabbit_port,self::$rabbit_login, self::$rabbit_pwd, self::$rabbit_vhost);

        if(!self::$connection->isConnected())
        {
            $this->apiResponse(self::$rabbit_err_code,'建立连接失败');
        }

        self::$channel = self::$connection->channel();

        if(!self::$channel->is_open())
        {
            $this->apiResponse(self::$rabbit_err_code,'通道连接失败');
        }

        //路由 （同名 队列 借用——方便记忆）
        self::$delay_routing = self::DELAY_QUEUE;
        self::$cache_routing = self::CACHE_QUEUE;
    }


    /**
     * Explain: 生产者：向队列投递数据
     * User: 奔跑吧笨笨
     * Date: 2019/4/24
     * Time: 11:55 AM
     */
    public function producers($send_info = array())
    {
        // TODO: Implement producers() method.

        //1、声明 交换机
        self::$channel->exchange_declare(self::DELAY_EXCHANGE, self::EXCHANGE_MODEL, false, true, false);
        self::$channel->exchange_declare(self::CACHE_EXCHANGE, self::EXCHANGE_MODEL, false, true, false);

        //2、设置 死信
        $table = new AMQPTable();
        $table->set('x-dead-letter-exchange', self::DELAY_EXCHANGE);       //参数 死信之后交换机
        $table->set('x-dead-letter-routing-key', self::$delay_routing);    //参数 死信之后路由
        $table->set('x-message-ttl', self::$life_time);                    //参数 消息失效时间

        //3、（缓存）队列绑定 交换机               第三个参数 true  是否持久（RabbitMQ 持久化 步骤一：元数据）
        self::$channel->queue_declare(self::CACHE_QUEUE, false, true, false, false, false, $table);
        self::$channel->queue_bind(self::CACHE_QUEUE, self::CACHE_EXCHANGE, self::$cache_routing);

        //4、（延迟）队列绑定 交换机
        self::$channel->queue_declare(self::DELAY_QUEUE, false, true, false, false, false);
        self::$channel->queue_bind(self::DELAY_QUEUE, self::DELAY_EXCHANGE, self::$delay_routing);

        $msg = new AMQPMessage(json_encode($send_info), array(
            //参数 发送消息的时候将消息的 deliveryMode 设置为 2 （RabbitMQ 持久化 步骤二：消息）
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ));
        //5、推送 消息到队列
        self::$channel->basic_publish($msg, self::CACHE_EXCHANGE, self::CACHE_QUEUE);

        //请求相应 返回
        $this->apiResponse(self::$rabbit_success_code, 'success', $send_info);

        return true;
    }



    /*
     * 资源返回
     */
    public  function apiResponse($code= 200 ,$message='默认描述信息',$data=[])
    {
        if(empty($data)){
            $data = (object)$data;
            $this->producersLog($message);
        }else{
            $this->producersLog($data,$message);
        }
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode(['code' => $code, 'message' => $message, 'data' => $data],JSON_UNESCAPED_UNICODE));
    }


    /*
     * 日志记录
     */
    public function producersLog($data = array(),$message = '')
    {
        $filename = __DIR__.'/Log/producers/'.date('Y').'/'.date('m').'/'.date('Y-m-d').'.txt';

        $dir         =  dirname($filename);
        if(!is_dir($dir))
        {
            mkdir($dir,0777,true);
        }

        $log_str = '[ '.date('Y-m-d H:i:s').' ]'."\r\n";

        if($message){
            $log_str .= '*** message *** :'.$message."\r\n";
        }

        if(gettype($data) == 'array')
        {
            $data = json_encode($data);
        }
        $log_str .= $data."\r\n";
        $log_str .= '[end]'."\r\n";

        file_put_contents($filename,$log_str,FILE_APPEND);

        return true;
    }


    /*
     * 关闭连接
     */
    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        self::$channel->close();
        self::$connection->close();
    }

}
