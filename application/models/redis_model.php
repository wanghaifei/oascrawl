<?php
/**
 * FILE_NAME : redis_model.php
 * Redis缓存相关
 *
 * @author		By Jie.zeng <zengjie@soften.cn>
 * @filesource
*/


class Redis_Model extends CI_Model {

    function __construct() {
        parent::__construct();
        
        $this->load->config('cache');
        $this->load->library('cache');
    }
    
    function test() {
        $this->cache->set('testkey', 'aaaa', 3600);
    }
    
    /**
     * 获取配置文件
     * @param $key 缓存文件的key
     * @param $value 缓存文件key中的参数
     */
    function get_config($name, $key){
    	$cache_keys = $this->config->config['cache_keys'][$name];
    	$cache_key = str_replace(array('[key]','[mdkey]'),array($key,md5($key)),$cache_keys['key']);
    	$cache_exprise = $cache_keys['expire'];
    	return array($cache_key,$cache_exprise);
    }

	/**
	 * 获取缓存信息
	 * @param $name 缓存的名称
	 * @param $key	缓存的key
	 */
	function get_redis_cache($name, $key){

		list($keys,$exprise) = $this->get_config($name, $key);
		
        $data = $this->cache->get($keys);

        return $data;
	}
	
	/**
	 * 保存缓存信息
	 * @param $name 缓存的名称
	 * @param $key  缓存的key
	 * @param $data 缓存的数据
	 */
	function set_redis_cache($name, $key, $data){
		
		list($keys, $exprise) = $this->get_config($name, $key);

		$this->cache->set($keys, $data, $exprise);
		
		return true;
	}

    /**
     * 删除缓存信息
     * @param $name 缓存的名称
     * @param $key	缓存的key
     */
    function del_redis_cache($name, $key){

        list($keys,$exprise) = $this->get_config($name, $key);

        $data = $this->cache->del($keys);

        return $data;
    }
}
