<?php
/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-6-30
 * Time: 下午5:57
 */

class log_model extends CI_Model {

    var $db = 'index';
    var $log_coll = "log";

    static $log	 = array();

    /**
     *       _id:
     *       url: 请求的url
     *    repose: 体育,新闻,幽默......
     * http_info: 获取返回的错误信息
     *  error_id: 失败原因
     */

    function __construct() {

        define('IS_EMPTY', '1');
        define('ERROR_PAGE', '2');
        define('IMG_SIZE_SMALL', '3');
        define('IMG_SIZE_BIG', '4');
        define('IMG_SIZE_NULL', '5');
        define('HTML_INVALID', '6');

        parent::__construct();
        $this->load->library('mongo_db');
        $this->mongo_db->switch_db($this->db);
    }


    /**
     * 记录日志
     *
     * @static
     * @access public
     *
     * @param string $message 日志信息
     * @param string $type  日志类型
     *
     * @throws ThinkExecption
     *
     */
    public  function record($req_url, $repose, $error_id)
    {
        $data = array(
            'url'=>$req_url,
            'repose'=>$repose,
            'error_id'=> $error_id,
            'date'=> date('YmdHis'),
        );
        return $this->mongo_db->insert($this->log_coll, $data);
    }

    /**
     *
     * 日志保存
     *
     * @static
     * @access public
     *
     * @param string $message 日志信息
     * @param string $type  日志类型
     * @param string $file  写入文件 默认取定义日志文件
     *
     * @throws ThinkExecption
     *
     */
    static function save()
    {
        $day	=	date('y_m_d');

        $_type	=	array(
                  IS_EMPTY => 'crawl html is empty',
                 NOT_FOUND => '404 not found',
            IMG_SIZE_SMALL => 'image is too small',
              IMG_SIZE_BIG => 'image is too big',
             IMG_SIZE_NULL => 'image size is empty',
              HTML_INVALID => 'html is invalid',
        );
    }

}