<?php
namespace DemoQueue\Queue\Consumers;
/**
 * Created by PhpStorm.
 * User: 奔跑吧笨笨
 * Date: 2019/5/6
 * Time: 1:04 PM
 */
date_default_timezone_set("Asia/Shanghai");
require_once __DIR__.'/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumersClass
{

   static protected $rabbit_host;
   static protected $rabbit_port;
   static protected $rabbit_login;
   static protected $rabbit_pwd;
   static protected $rabbit_vhost;

   static protected $connection;
   static protected $channel;

   static protected $config;

   static protected $rabbit_err_code = 500;
   static protected $rabbit_success_code = 200;

   static protected $rabbit_exchange;
   static protected $rabbit_queue;
   static protected $rabbit_routing;

    const EXCHANGE_MODEL = 'fanout';                //交换机模式 (广播模式)


    public function __construct($rabbit_exchange,$rabbit_queue)
    {
        self::$config = include_once __DIR__."/../Config/config.php";

        self::$config = self::$config['rabbitmq'];
        self::$rabbit_host = self::$config['host'];
        self::$rabbit_port = self::$config['port'];
        self::$rabbit_login = self::$config['login'];
        self::$rabbit_pwd = self::$config['password'];
        self::$rabbit_vhost =  self::$config['vhost'];

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

        if($rabbit_exchange)
        {
            self::$rabbit_exchange = $rabbit_exchange;
        }else{
            self::apiResponse(self::$rabbit_err_code,'请选择Exchange');
        }

        if($rabbit_queue)
        {
            self::$rabbit_queue = $rabbit_queue;
            //同名 借用 路由
            self::$rabbit_routing = $rabbit_queue;
        }else{
            self::apiResponse(self::$rabbit_err_code,'请选择Queue');
        }

    }


    /*
     * 消费者：客户端
     * 消费队列消息，并基于HTTP API路由转发到相应业务代码
     */
    public static function consumersClient()
    {
        //1、声明交换机
        self::$channel->exchange_declare(self::$rabbit_exchange, self::EXCHANGE_MODEL,false,true,false);
        //2、声明队列
        self::$channel->queue_declare(self::$rabbit_queue,false,true,false,false,false);
        //3、交换机和队列 绑定
        self::$channel->queue_bind(self::$rabbit_queue, self::$rabbit_exchange,self::$rabbit_routing);

        //console log : Start to work
        self::consoleLog();

        $callback = function ($msg){

            //console log : Received message
            self::consoleLog(" [x] Received".$msg->body,0);

            //执行业务操作 (根据生产者，设定的路由 Http 访问)
            $data = json_decode($msg->body,true);
            if(isset($data['url']))
            {
                $url = $data['url'].'?data='.$msg->body;
                if(strpos($url,'http') !== false)
                {
                    $result = file_get_contents($url);
                }else{
                    $result = 'HTTP not found';
                }
                self::consoleLog($result);
            }else{
                self::consoleLog('Undefined URL');
            }

            //手动 回复队列，message已消费
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        };

        //只有consumer已经处理，并确认了上一条message时queue才分派新的message给它(非公平分配，如果存在耗时操作，那么也一直等待。现在是空闲领取消息)
        self::$channel->basic_qos(null, 1, null);
        //第四个参数  是否自动回应 ack，false 手动回应
        self::$channel->basic_consume(self::$rabbit_queue,'',false,false,false,false,$callback);


        //进入等待状态
        while (count(self::$channel->callbacks)) {
            self::$channel->wait();
        }

        return true;
    }


    /*
     * Console log
     */
    protected static function consoleLog($message = ' [*] Waiting for message. To exit press CTRL+C ',$type = 1)
    {
        $message = date('Y-m-d H:i:s').$message;

        //输出到 控制台
        echo $message.PHP_EOL;
        //是否记录文件日志
        if($type === 1)
        {
            self::Logs($message);
        }

        return true;
    }


    /*
     * API 返回
     */
   protected static  function apiResponse($code= 200 ,$message='默认描述信息',$data=[])
    {
        if(empty($data)){
            $data = (object)$data;
           self::Logs($message);
        }else{
            self::Logs($data);
        }
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode(['code' => $code, 'message' => $message, 'data' => $data],JSON_UNESCAPED_UNICODE));
    }


    /*
     * 日志记录
     */
   protected static function Logs($message = '')
    {
        $filename = __DIR__.'/Log/consumers/'.date('Y').'/'.date('m').'/'.date('Y-m-d').'.txt';

        $dir         =  dirname($filename);
        if(!file_exists($dir))
        {
            @mkdir($dir,0777,true);
        }

        $log_str = '[ '.date('Y-m-d H:i:s').' ]'."\r\n";
        $log_str .= '*** message *** :'.$message."\r\n";
        $log_str .= '[end]'."\r\n";

        file_put_contents($filename,$log_str,FILE_APPEND);

        return true;
    }


    /*
     * 销毁连接
     */
    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        self::$channel->close();
        self::$connection->close();
    }

}

