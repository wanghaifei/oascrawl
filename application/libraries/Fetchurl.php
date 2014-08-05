<?php if ( ! defined('BASEPATH')) exit('Access denied!');
/**
 * 抓取HTTP页面
 * curl的封装
 *
 * @filesource
*/

class Crawlurl {

	static $_ipusername = 'hongmai';
    static $_ippassword = 'soften.cn';
    
    private function __construct() {}
    
	static function get_content($options = array()) {
        if( ! $options || ! isset($options['url']) ||  ! $options['url']) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $options['url']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if( isset($options['timeout']) && $options['timeout']) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout']);
        }
        
        if( isset($options['returnHeader']) && $options['returnHeader']) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
        }

        if(isset($options['returnBody']) && ! $options['returnBody']) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }

        if( isset($options['httpHeader'])  && ! empty($options['httpHeader'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $options['httpHeader']);
        }

        if( isset($options['method']) && $options['method'] == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            
            if($options['postData']) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $options['postData']);
            }
        }

        if( isset($options['cookie']) && $options['cookie']) {
            curl_setopt($ch, CURLOPT_COOKIE, $options['cookie']);
        }

        if(isset($options['referer']) && $options['referer']) {
            curl_setopt($ch, CURLOPT_REFERER, $options['referer']);
        }
        
		//if( isset($options['encoding']) && $options['encoding']) {
        //    curl_setopt($ch, CURLOPT_ENCODING, $options['encoding']);
        //}

        if(!isset($options['agent']) || empty($options['agent']) ) {
        	$options['agent'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36';
        }
        curl_setopt($ch, CURLOPT_USERAGENT, $options['agent']);
        
        if(isset($options['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $options['proxy']['ip']);
            curl_setopt($ch, CURLOPT_PROXYTYPE , CURLPROXY_SOCKS5);
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, self :: $_ipusername . ':' . self :: $_ippassword);
        }

 //echo "\r\n options: ";
 //print_r($options);

        $result = curl_exec($ch);
        
        curl_close($ch);
        
        return $result;
    }
}

class Fetchurl extends Crawlurl{
	
	private $_headers;
    private $_userAgent;
    private $_mobile;
    private $_password;
    private $_loginContent;
    private $_passwordInput;
    private $_action;
    private $_vk;
    private $_loginResponse;
    private $_gsid;
    private $_uid;
    private $_cookie;
    private $_referer;
    private $_encoding;
    private $_agent;
    private $_page;
    private $_keyword;
    private $_result;
    public $_encode;

    //private $_ip;
    private $_proxy_ip; //使用代理ip

    //private $_ipopen;   //废弃
    private $_use_proxy = false;    //是否启用代理
    
    //private $_ipurl;
    private $_proxy_list_url = 'http://210.73.220.187:8082/get_ips/index/15'; //获取代理列表的URL

     
	function __construct() {
		//$this->_ipurl = 'http://210.73.220.187:8082/get_ip';
	}

	/**
	 * 初始化所有类基础属性
	 */
	function init() {
		$this->_cookie = "";
		$this->_referer = "";
		$this->_encoding = "";
		$this->_agent = "";
		
		$this->_ip = "";
        $this->_proxy_ip = '';
        $this->_use_proxy = false;

		$this->_encode = "";
		$this->_ipopen = false;
	}
	
	function set_cookie($cookie) {
        $this->_cookie = $cookie;
        return true;
    }
    
	function set_referer($referer) {
        $this->_referer = $referer;
        return true;
    }
    
	function set_encoding($encoding) {
        $this->_encoding = $encoding;
        return true;
    }
    
	function set_agent($agent) {
        $this->_agent = $agent;
        return true;
    }
    
	//function set_proxy_ip() {
	//	if($this->_use_proxy){
    //    	$this->_proxy_ip = $this->get_proxy_ip();
	//	}
    //}
    
    function use_proxy() {
        $this->_use_proxy = true;
    }

    //获取动态IP
    function get_proxy_ip(){
        //@TODO  这里需要把ip换成一次性取多个，缓存 3-5秒， by quwei
        // _ipurl 里的ip应该一次性返回多个。并且如果使用了代理，retry 默认至少重试1次
        //$this->_proxy_list_url =  'http://210.73.220.187:8082/get_ips/index/20';

        $iplist = cache_get('proxy_list', 5);  //缓存5秒
        if(empty($iplist) ) {
            $str = file_get_contents($this->_proxy_list_url);
            if( empty($str) ) $str = file_get_contents($this->_proxy_list_url);
            if( !empty($str) )  {
                $iplist = array();
                foreach( explode('|', $str) as $ip) {
                    $ip = trim($ip);
                    if(!empty($ip)) $iplist[] = $ip;
                }
                cache_set('proxy_list', $iplist);
            }
        }
        if( !empty($iplist) ) {
            return $iplist[array_rand($iplist)];
        } else {
            return false;
        }
    }

	/**
	 * 获取远程页面
	 * @param $url	地址
	 * @param $retry	重试次数
	 * @param $timeout	超时时间
	 */
	public function get($url, $retry = 0, $timeout = 10 ) {
		$options = array();
        
		$options['url'] = $url;
		$options['timeout'] = $timeout;
        $options['cookie'] = $this->_cookie;
        $options['referer'] = $this->_referer;
        $options['encoding'] = $this->_encoding;
        $options['agent'] = $this->_agent;
        
        $i = 0;
        while($i <= $retry){
        	
            if($this->_use_proxy){
               $proxy_ip = $this->get_proxy_ip();
               if( $proxy_ip ) $options['proxy']['ip'] = $proxy_ip;
            }
        
        	$this->_result = self::get_content($options);
echo "\r\n result:",     strlen($this->_result) ;   	
        	if($this->_result) break;
echo " \r\n get again: $i;  retry: $retry \r\n";        	
        	$i ++;
        }
        
        
        return $this->convert_encode($this->_result, $this->_encode);
	}

	public function fetch($url, $retry = 0, $timeout = 5 ) {
		//fetch 方法 与 get 的区别：
		//执行fetch前也可以执行 set_*　系列方法，但是只要调用过一次fetch，所有设置都会恢复原样
		//@TODO 待改进： 在多个get方法之间，如果调用过一次fetch，就影响了多次的get方法。如何避免？		

		$options = array();
        
		$options['url'] = $url;
		$options['timeout'] = $timeout;
        
		$i = 0;
        while($i <= $retry){
        	
            if($this->_use_proxy){
               $proxy_ip = $this->get_proxy_ip();
               if( $proxy_ip ) $options['proxy']['ip'] = $proxy_ip;
            }
        
        	$this->_result = self::get_content($options);
        	
        	if($this->_result) break;
        	
        	$i ++;
        }
        
        return $this->convert_encode($this->_result);
	}

    function convert_encode($str) {
        
        if( strtolower($this->_encoding) == 'auto') {
            //如果设定编码为 auto， 自动分析编码
            return do_encode($str, '', true);
        }elseif( !empty($this->_encoding) ) {
            //如果指定了编码，转码
            return do_encode($str, $this->_encoding);
        }
        return $str;
    }

}
