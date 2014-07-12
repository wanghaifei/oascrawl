<?php
/**
 * 队列处理 业务逻辑
 * 
 * 这里与 libary 中 队列类 的区别： 这里的queue是业务逻辑的封装
 * 
 *
 * @package		Unotice v4
 * @author		Soften.cn Dev Team	| By QuWei
 * @copyright	Copyright (c) 2013, Unotice, Inc.
 * @link		http://www.soften.cn
 * @filesource
*/


class Queue_Model extends CI_Model {

	function __construct() {
		parent::__construct();

		$this->load->config('rabbitmq');
		$config = $this->config->item('rabbitmq');

		$this->load->library('QueueRabbitMq',$config);
	}


	/**
	 * 通用添加 队列
	 * @param string $queue_name
	 * @param object 队列内容
	 */
	public function add_queue($queue_name, $data) {
		
		if(empty($queue_name) || empty($data)) {
			log_message('error',"#Queue_model# add_queue param is empty!!");
			return false;
		}

		$exchange_name = $this->get_exchange();
		//$queue_name = "{queue_name}_task";
		$routing_key = "{$queue_name}_key";

		$messages = $this->encode($data);

		return $this->queuerabbitmq->add_task($messages, $exchange_name, $queue_name, $routing_key);
	 }


	/**
	 * 通用获取 队列
	 * @param string $queue_name 
	 */
	public function get_queue($queue_name) {
		$exchange_name = $this->get_exchange();
		$routing_key = "{$queue_name}_key";

		$messages = $this->queuerabbitmq->get_task($exchange_name, $queue_name, $routing_key);
		return $this->decode($messages);
	}

	/**
	* 入队列时的参数编码,最终返回约定过的编码格式(暂定json)
	*
	* @access public
	* @param mixed $messages 消息内容.. [Must]
	* @return string $mixed
	*/
	public function encode($messages) {

		if(empty($messages)){
			//log_message('error',"Queue_model encode param is empty !!!");
			return false;
		}

		return serialize($messages);
	}

	/**
	* 出队列时的参数解码,与encode对应
	* 通常返回值为数组..取决于 消息的格式
	*
	* @access public
	* @param mixed $messages 消息内容.. [Must]
	* @return array $mixed
	*/
	public function decode($messages) {
		if(empty($messages)){
			//log_message('error',"Queue_model decode param is empty !!!");
			return false;
		}

		return unserialize($messages);
	}

	/**
	* 获取exchange信息
	*
	* @access public
	* @return string $mixed
	*/
	public function get_exchange() {
		return $this->config->item('exchange','rabbitmq');
	}

	/**
	* __call
	*
	* @access public
	* @param string $method_name 调用的方法名称 [Must]
	* @param array $arguments 调用此方法时传的参数 [Must]
	* @return string $mixed
	*/
     public function __call($method_name, $arguments) {
     	return call_user_func_array(array($this->queuerabbitmq, $method_name), $arguments);
     }


	/**
	 * 添加 需要抓取的关键词  任务信息
	 * @param $taskinfo
	 */
	// public function add_wordtask($id) {
	// 	if(empty($id)){
	// 		log_message('error',"Queue_model add_wordtask param is empty!!");
	// 		return false;
	// 	}

	// 	$exchange_name = $this->get_exchange();
	// 	$queue_name = 'need_crawl_word_task';
	// 	$routing_key = 'need_crawl_word_task_key';

	// 	//$messages = $this->encode($taskinfo);

	// 	return $this->queuerabbitmq->add_task($id, $exchange_name,$queue_name, $routing_key);
	// }

	/**
	 * 从队列中取出一个需要抓取的关键词的id
	 */
	// function get_crawl_word_id() {
	// 	//调用 libary 中 队列类， 从队列中 返回待抓取网站
	// 	$exchange_name = $this->get_exchange();
	// 	$queue_name = 'need_crawl_word_task';
	// 	$routing_key = 'need_crawl_word_task_key';

	// 	$messages = $this->queuerabbitmq->get_task($exchange_name,$queue_name, $routing_key);
	// 	//$messages_rs = $this->decode($messages);

	// 	return $messages;
	// }
}