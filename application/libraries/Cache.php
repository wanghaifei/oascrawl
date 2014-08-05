<?php if ( ! defined('BASEPATH')) exit('Access denied!');
/**
 * 
 * @filesource
*/
class Cache {


	public function __construct() {
        $this->CI = & get_instance();
        $this->CI->load->driver('cache', array('adapter' => 'redis'));
        $this->cache = $this->CI->cache;
		//log_message('debug', "Cache Class Initialized.");
	}


	function get($key) {
        try {
            $data =  $this->cache->get($key);
            $data = $data == false ? array() : unserialize($data);
            return $data;
        } catch (Exception $e) {
            //log_message($e->getMessage());
            return false;
        }
	}


	public function set($key, $value, $exprise = null) {
        try {
        	$value = serialize($value);
            $this->cache->save($key, $value, $exprise);
            
        } catch (Exception $e) {
            //log_message($e->getMessage());
            return false;
        }
	}

	function del($key) {
        $this->cache->delete($key);
	}


}
