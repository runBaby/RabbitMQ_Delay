<?php
namespace DemoQueue\Queue\Producer;
/**
 * Created by PhpStorm.
 * User: runBaby
 * Date: 2019/5/13
 * Time: 11:13 AM
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

    static protected $rabbit_err_code = 500;
    static protected $rabbit_success_code = 200;

    static protected $cache_exchange;
    static protected $cache_routing;
    static protected $cache_queue;

    static protected $config;

    const EXCHANGE_MODEL = 'fanout';                //交换机模式


    public function __construct($cache_exchange,$cache_queue)
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
            self::apiResponse(self::$rabbit_err_code,'建立连接失败');
        }

        self::$channel = self::$connection->channel();

        if(!self::$channel->is_open())
        {
            self::apiResponse(self::$rabbit_err_code,'通道连接失败');
        }

        if(!$cache_exchange)
        {
            self::apiResponse(self::$rabbit_err_code,'请设置交换机名称');
        }else{
            self::$cache_exchange = $cache_exchange;
        }

        if(!$cache_queue)
        {
            self::apiResponse(self::$rabbit_err_code,'请设置队列名称');
        }else{
            self::$cache_queue = $cache_queue;
            //路由 （同名 队列 借用）
            self::$cache_routing = self::$cache_queue;
        }
    }

    /**
     * Explain: 向队列 投递数据
     * @param array $send_info
     * User: runBaby
     * Date: 2019/5/13
     * Time: 11:34 AM
     * @return bool
     */
    public static function Producer($send_info = array())
    {
        //参数 json 转化
        $data = json_encode($send_info);

        //1、声明 交换机
        self::$channel->exchange_declare(self::$cache_exchange, self::EXCHANGE_MODEL, false, true, false);

        //2、声明队列
        self::$channel->queue_declare(self::$cache_queue, false, true, false, false, false);

        //3、队列绑定 交换机
        self::$channel->queue_bind(self::$cache_queue, self::$cache_exchange, self::$cache_routing);

        //4.1 设置异步回调消息确认 （生产者 防止信息丢失）
        self::$channel->set_ack_handler(
            function (AMQPMessage $message) {
                echo "Message acked with content " . $message->body . PHP_EOL;
                self::apiResponse(self::$rabbit_success_code, 'success', $message->body);
            }
        );
        self::$channel->set_nack_handler(
            function (AMQPMessage $message) {
                echo "Message received failed，Please try again：" . $message->body . PHP_EOL;
                self::apiResponse(self::$rabbit_err_code, 'Message received failed，Please try again', $message->body);
            }
        );

        //4.2 选择为 confirm 模式（此模式不可以和事务模式 兼容）
        self::$channel->confirm_select();

        //5、发送消息 到队列
        $msg = new AMQPMessage($data, array(
            //参数 发送消息的时候将消息的 deliveryMode 设置为 2 （RabbitMQ 持久化 步骤二：消息）
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ));
        self::$channel->basic_publish($msg, self::$cache_exchange, self::$cache_queue);

        //4.3 阻塞等待消息确认 （生产者 防止信息丢失）
        self::$channel->wait_for_pending_acks();

        //请求相应 返回
        self::apiResponse(self::$rabbit_success_code, 'success', $data);

        return true;
    }


    /*
    * 资源返回
    */
    public static function apiResponse($code= 200 ,$message='默认描述信息',$data=[])
    {
        if(empty($data)){
            $data = (object)$data;
            self::producersLog($message);
        }else{
           self::producersLog($data,$message);
        }
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode(['code' => $code, 'message' => $message, 'data' => $data],JSON_UNESCAPED_UNICODE));
    }


    /*
     * 日志记录
     */
    public static function producersLog($data = array(),$message = '')
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