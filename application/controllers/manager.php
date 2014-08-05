<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-7-1
 * Time: 下午3:01
 */

class Manager extends CI_Controller {

    const Q_RELATION = 'relation_crawl';

    function __construct() {
        parent::__construct();
        $this->load->model('class_model');
        $this->load->model('feeds_model');
        $this->load->model('relation_model');
        $this->load->model('redis_model');
        $this->load->model('queue_model');
        $this->load->library('htmlparser');
    }

    public function add_class()
    {
        $info = array('name'=>'humor');
        $this->class_model->add($info);
    }

    public function find_class()
    {
        $info = array('classid'=>1);
        $result = $this->class_model->findOne($info);
        print_r($result);
    }

    public function add_feeds()
    {
        //$this->feeds_model->add('http://www.999gag.com/en/tag', 1, array(), 1, 1);
        //$this->feeds_model->add('http://www.komikdunya.com/komikresimler/', 1, array(), 1, 1);
        //$this->feeds_model->add('http://www.komikdunya.com/karikaturler/', 1, array(), 1, 1);
        //$this->feeds_model->add('http://www.komikler.com/komikresim/', 1, array('gaoxiao'), 1, 1);
        $this->feeds_model->add('http://www.komikfikralar.org/', 1, array(), 0, 2, 1);
    }

    /**
     * 清空缓存
     */
    public function emptycache()
    {
        if ($unlock_lists = $this->relation_model->find(array(), 0, 0)) {
            foreach ($unlock_lists as $crawl_info) {
                $this->redis_model->set_redis_cache('url_relation', $crawl_info['url'], array());
            }
        }
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
    public function tcrawl()
    {
        header("Content-type: text/html; charset=utf-8");

        if(empty($_GET['url'])) die();

        if ('' == $url = $_GET['url']) die();
        $url = $_GET['url'];
        $type = !empty($_GET['type']) ? $_GET['type'] : 2;
        $with_pic = !empty($_GET['with_pic']) ? true : false;
        $pr_page = !empty($_GET['pr_page']) ? true : false;

        $lists = $this->htmlparser->start($url, $type, $with_pic);
        if ($pr_page) {
            $lists = $this->htmlparser->turn_page_url();
        }
        print_r($lists);
    }

    /**
     * 重新抓取
     * @param string $url
     */
    public function recrawl()
    {
        if(!empty($_GET['url'])){
            $rel_info = $this->relation_model->findOneByUrl($_GET['url']);
            $lists = array($rel_info);
        }else{
            //清空队列
            while (true) {
                $queue_info = $this->queue_model->get_queue(self::Q_RELATION);
                if(empty($queue_info)) break;
            }
            $lists = $this->relation_model->find(array(), 0, 0);
        }
        foreach ((array)$lists as $rel_info) {
            //删除缓存
            $this->redis_model->del_redis_cache('url_relation', $rel_info['url']);
            //更改抓取状态
            $this->relation_model->update_nexttime($rel_info['_id'], true);
            $this->relation_model->update_lasttime($rel_info['_id'], true);
            $this->relation_model->update_status($rel_info['_id'], 0);
        }
    }
}