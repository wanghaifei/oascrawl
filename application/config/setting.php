<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');


if(!defined('CRAWL_START')) define ("CRAWL_START", '抓取开始');
if(!defined('QUEUE_INFO')) define ("QUEUE_INFO", '队列');
if(!defined('HTML_INFO')) define ("HTML_INFO", '抓取信息');
if(!defined('INSERT_COUNT')) define ("INSERT_COUNT", '插入数');
if(!defined('SET_CACHE')) define ("SET_CACHE", '保存缓存');
if(!defined('CRAWL_END')) define ("CRAWL_END", '抓取结束');

//关键词抓取相关配置参数
$config['url_crawl'] = array(
	'fail_sleep_time' => 10,//抓取失败每次sleep时间..单位为秒
	'empty_retry' => 1,//抓取为空.重试次数,
	'fail_retry' => 1,//抓取失败.重试次数,
	'max_page' => 10,//抓取最大翻页数
);

//抓取数对应的时间间隔
$config['time_interval'] = array(
    86400 => array(0, 1), //24小时
    43200 => array(1, 3), //12小时
    21600 => array(3, 6), //6小时
    10800 => array(6, 12), //3小时
    3600 => array(12, 30), //1小时
    1800 => array(30, 60), //30分
    600 => array(60, 120), //10分钟
    180 => array(120), //3分钟
);

/**
* End of file setting.php
*/
