<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-7-1
 * Time: 下午3:01
 */

class Manager extends CI_Controller {

    const Q_RELATION = 'relation_crawl';
    const Q_DETAIL_TITLE = 'detail_title_crawl';

    function __construct() {
        parent::__construct();
        $this->load->model('class_model');
        $this->load->model('feeds_model');
        $this->load->model('relation_model');
        $this->load->model('redis_model');
        $this->load->model('queue_model');
        $this->load->model('cookie_model');
        $this->load->library('htmlparser');
    }

    public function add_class()
    {
        $info = array('name'=>'humor');
        $this->class_model->add($info);
    }

    public function find_class()
    {
        $id = '53c8f7f1eb8be2e6498b4567';
        $result = $this->class_model->findOneByID($id);
        print_r($result);
    }

    /**
     * 增加feed
     */
    public function add_feeds()
    {
        //add(url, html_type, 添加的标签, 相关内容是否存在图片, 规则ID)
        //$this->feeds_model->add('http://www.999gag.com/en/tag', 1, array(), 1, 0);
        //$this->feeds_model->add('http://www.komikdunya.com/komikresimler/', 1, array(), 1, 0);
        //$this->feeds_model->add('http://www.komikdunya.com/karikaturler/', 1, array(), 1, 0);
        //$this->feeds_model->add('http://www.komikler.com/komikresim/', 1, array(), 1, 0);
        //$this->feeds_model->add('http://www.komikfikralar.org/', 1, array(), 0, 1);
    }
    /**
     * 增加relation
     */
    public function add_relation()
    {
        //http://www.lazland.com/komikresim/default.asp?S=EB
        $this->relation_model->add(0, 'http://www.lazland.com/komikresim/default.asp?Start=0', array(), 1, 1, 0);
    }

    /**
     * 增加detail_title
     */
    public function add_detail_title()
    {
        $url = 'http://www.fikralarim.com/';
        $queue_info = array('url'=>$url, 'with_pic'=>0, 'rule_id'=>1, 'classid'=>1, 'cachekey'=>$url);
        $this->queue_model->add_queue(self::Q_DETAIL_TITLE, $queue_info);
    }

    /**
     * 增加cookie
     */
    public function add_cookie()
    {
        //www.999gag.com
        $cookie = array('host'=>'www.999gag.com', 'cookie'=>'hl=tr;');
        $this->cookie_model->add($cookie);
    }

    /**
     * 缓存状态
     */
    public function stat_cache()
    {
        header("Content-type: text/html; charset=utf-8");

        if ($unlock_lists = $this->relation_model->find(array(), 0, 0)) {
            foreach ($unlock_lists as $crawl_info) {
                $crawl_url_lists = $this->redis_model->get_redis_cache('url_relation', $crawl_info['url']);
                echo 'URL：'.$crawl_info['url']."<br>";
                echo '翻页信息：'. print_r($crawl_url_lists, true) . "<br>";
                echo "<br>--------------------------------------------------------------<br>";
            }
        }
    }

    /**
     *
     * @param string $url 抓取的url
     * @param int $type
     * @param bool $pic
     * @param bool $pr_page
     */
    public function tcrawl($url, $http_type = 2, $with_pic = 0, $rule_id = 0, $pr_page = 0)
    {
        header("Content-type: text/html; charset=utf-8");
        $lists = $this->htmlparser->start(urldecode($url), $http_type, $with_pic, $rule_id);
        if ($pr_page) {
            $lists = $this->htmlparser->turn_page_url();
        }
        print_r($lists);
    }


    /**
     * 重新抓取
     * @param string $url
     */
    public function re_crawl($url='')
    {

            $condition = array('_id'=>"57670788f0e00a7c533cbd6789bac7ca");
            $rel_info = $this->relation_model->find($condition);
            print_r($rel_info);exit;

    }

    /**
     * 清空缓存
     */
    public function emptycache()
    {
        if ($unlock_lists = $this->relation_model->find(array(), 0, 0)) {
            foreach ($unlock_lists as $crawl_info) {
                $this->redis_model->del_redis_cache('url_relation', $crawl_info['url']);
            }
        }
    }

    public function test()
    {
        $condition = array('_id'=>"57670788f0e00a7c533cbd6789bac7ca");
        $rel_info = $this->relation_model->find($condition);
        print_r($rel_info);exit;
        $lists = array($rel_info);
    }
}