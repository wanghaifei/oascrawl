<?php

/**
 *
 * ClassName: QueueRabbitMq
 *
 * description...
 *
 * @author wanghaifei <wanghaifei@soften.cn>
 *
 */
class QueueRabbitMq {

	/**
	* 实例化对象数组
	*
	* @var array
	*/
	protected $instance = array();

	/**
	* amqp 服务器配置信息
	*
	* @var array
	*/
	private $config = array();


	/**
     *
     * Constructor
     *
     * @author wanghaifei <wanghaifei@soften.cn>
     *
     */
	public function __construct($config) {
		if(empty($config['host']) || empty($config['port']) || empty($config['user']) || empty($config['pass']) || empty($config['vhost'])){
			log_message('error',"QueueRabbitMq __construct param is error !! config:".var_export($config,true));
			return false;
		}

		$this->config = $config;
		$options = array(
				'host' => $this->config['host'],
				'port' => $this->config['port'],
				'login' => $this->config['user'],
				'password' => $this->config['pass'],
				'vhost' => $this->config['vhost']
		);

		//初始化connect
		if(empty($this->instance['amqp_connect'])){
			$this->instance['amqp_connect'] = new AMQPConnection($options);
			$this->instance['amqp_connect']->connect();
		}

		//初始化channel
		if(empty($this->instance['amqp_channel'])){
			$this->instance['amqp_channel'] = new AMQPChannel($this->instance['amqp_connect']);
		}

		//初始化exchange
		if(empty($this->instance['amqp_exchange'])){
			$this->instance['amqp_exchange'] = new AMQPExchange($this->instance['amqp_channel']);
			$this->instance['amqp_exchange']->setName($this->config['exchange']);
			$this->instance['amqp_exchange']->setType($this->config['exchangeType']);
			$this->instance['amqp_exchange']->setFlags(AMQP_DURABLE | AMQP_AUTODELETE);
			//@$this->instance['amqp_exchange']->declare();
		}

		//初始化queue
		if(empty($this->instance['amqp_queue'])){
			$this->instance['amqp_queue'] = new AMQPQueue($this->instance['amqp_channel']);
		}
	}

	/**
	* __destruct
	*
	* @access public
	* @return string $mixed
	*/
	public function __destruct() {
		$this->instance['amqp_connect']->disconnect();
	}

	/**
	* add_task
	*
	* @access public
	* @param string $message 消息内容..通常是json串 [Must]
	* @param string $exchange_name 交换机名称 [Must]
	* @param string $queue_name 队列名称 [Must]
	* @param string $routing_key 绑定时用的key [Must]
	* @return bool $mixed
	*/
	public function add_task($message, $exchange_name,$queue_name, $routing_key) {
		if(empty($message) || empty($exchange_name) || empty($queue_name) || empty($routing_key)){
			$error_info = "add_task param is error !! message:{$message}--exchange_name:{$exchange_name}--queue_name:{$queue_name}--routing_key:{$routing_key}";
			log_message('error',$error_info);
			return false;
		}

		$this->instance['amqp_queue']->setName($queue_name);
		$this->instance['amqp_queue']->setFlags(AMQP_DURABLE | AMQP_AUTODELETE);
		@$this->instance['amqp_queue']->declare();
		$this->instance['amqp_queue']->bind($exchange_name, $routing_key);

		$this->instance['amqp_channel']->startTransaction();
		$result = $this->instance['amqp_exchange']->publish($message, $routing_key);
		$this->instance['amqp_channel']->commitTransaction();

		return $result;
	}

	/**
	* get_task
	*
	* @access public
	* @param string $exchange_name 交换机名称 [Must]
	* @param string $queue_name 队列名称 [Must]
	* @param string $routing_key 绑定时用的key [Must]
	* @return string $mixed
	*/
	public function get_task($exchange_name,$queue_name, $routing_key) {
		if(empty($exchange_name) || empty($queue_name) || empty($routing_key)){
			$error_info = "get_task param is error !! exchange_name:{$exchange_name}--queue_name:{$queue_name}--routing_key:{$routing_key}";
			log_message('error',$error_info);
			return false;
		}

		$this->instance['amqp_queue']->setName($queue_name);
		$this->instance['amqp_queue']->setFlags(AMQP_DURABLE | AMQP_AUTODELETE);
		//@$this->instance['amqp_queue']->declare();
		$this->instance['amqp_queue']->bind($exchange_name, $routing_key);

		$messages = $this->instance['amqp_queue']->get(AMQP_AUTOACK);
		if($messages) {
			return $messages->getBody();
		} else {
			return false;
		}
	}
}
// ClassName Queue End