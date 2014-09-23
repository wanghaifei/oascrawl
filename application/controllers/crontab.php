<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Crontab extends CI_Controller {

    const Q_FEED = 'feed_crawl';
    const Q_RELATION = 'relation_crawl';
    const Q_DETAIL = 'detail_crawl';
    const Q_DETAIL_TITLE = 'detail_title_crawl';

    function __construct() {
        parent::__construct();
        $this->load->model('queue_model');
        $this->load->model('feeds_model');
        $this->load->model('relation_model');
        $this->load->model('redis_model');
        $this->load->model('class_model');
        $this->load->library('htmlparser');
    }

    public function sync_feeds(){
        $total = $this->feeds_model->find(array(), 0, 0, true);
        $offset = 0;
        $limit = 10;
        $count = ceil(count($total)/$limit);
        for ($i = 0; $i < count($count); $i++) {
            $result_feeds = $this->feeds_model->find(array(), $offset, $limit, false);

            foreach ($result_feeds as $feed_info) {
                $this->queue_model->add_queue(self::Q_FEED, $feed_info);
            }
            $offset += $limit;
            sleep(2);
        }
    }

    /**
     * 抓取标签列表
     */
    public function crawl_tags()
    {
        if(false == $queue_info = $this->queue_model->get_queue(self::Q_FEED)){
            sleep(10); return false;
        }
        $tag_lists = $this->htmlparser->start($queue_info['url'], 1, $queue_info['rel_with_pic'],  $queue_info['rule_id']);

        if(empty($tag_lists) && $this->htmlparser->recrawl){
            $this->queue_model->add_queue(self::Q_FEED, $queue_info);
            return false;
        }

        foreach ($tag_lists as $info) {
            if($this->relation_model->findOneByUrl($info['url'])) {
                continue;
            }
            //合并标签
            if ($queue_info['tags'] && ! in_array($info['tag'], $queue_info['tags'])) {
                $tags = array_merge(array($info['tag']), $queue_info['tags']);
            }else{
                $tags = array($info['tag']);
            }
            $this->relation_model->add($queue_info['_id'], $info['url'], $tags, $queue_info['rel_with_pic'], $queue_info['classid'], $queue_info['rule_id']);
        }

    }

    /**
     * 抓取标签
     */
    public function  sync_relation()
    {
        if(false == $tagurls = $this->relation_model->nextCrawl()){
            sleep(5); return false;
        }
        foreach ($tagurls as $info) {

            $crawl_url_lists = array();
            //更改抓取时间为现在
            $this->relation_model->update_lasttime($info['_id']);
            //更改抓取状态为正在抓取
            $this->relation_model->update_status($info['_id'], 1);
            //上次抓取数初始化为0
            $this->relation_model->add_lastcount($info['_id'], 0, true);

            $info['cachekey'] = $info['url'];

            $this->queue_model->add_queue(self::Q_RELATION, $info);

            $crawl_url_lists[$info['url']] = 0;

            $this->redis_model->set_redis_cache('url_relation', $info['cachekey'], $crawl_url_lists);
        }
    }

    /**
     * 抓取相关信息列表
     * @return bool
     */
    public function crawl_relation($url = '')
    {
        pr_exe_process(CRAWL_START);

        if($url) $crawl_info = $this->getCrawlInfo($url);
        else $crawl_info = $this->queue_model->get_queue(self::Q_RELATION);

        pr_exe_process(QUEUE_INFO, print_r($crawl_info, true));

        if(false == $crawl_info) return false;

        $classid = $crawl_info['classid'];

        $cachekey = $crawl_info['cachekey'];

        $relation_lists = $this->htmlparser->start($crawl_info['url'], 2, $crawl_info['with_pic'],  $crawl_info['rule_id']);

        if(empty($relation_lists) && empty($url) && $this->htmlparser->recrawl){
            $this->queue_model->add_queue(self::Q_FEED, $crawl_info);
            return false;
        }

        $url_pages = $this->htmlparser->turn_page_url();

        pr_exe_process(HTML_INFO, print_r($relation_lists, true));

        $class_info = $this->class_model->findOne(array('classid'=>$classid));

        $this->load->model('detail_model');
        $this->detail_model->setTableName($class_info['name']);

        //不存在该记录则存储
        $insert_count = 0;
        foreach ((array)$relation_lists as $url_info) {

            if ($this->detail_model->findOneByUrl($url_info['url']) || empty($url_info['description'])) {
                continue;
            }
            $data['title'] = $url_info['title'];
            $data['description'] = $url_info['description'];
            $data['page_url'] = $crawl_info['url'];
            $data['with_pic'] = $crawl_info['with_pic'];

            $this->detail_model->add($crawl_info['_id'], $url_info['url'], $crawl_info['tags'], $data);
            $this->queue_model->add_queue(self::Q_DETAIL, array('url'=>$url_info['url'], 'with_pic'=>$crawl_info['with_pic'], 'rule_id'=>$crawl_info['rule_id'], 'classid'=>$classid ));
            $insert_count++;
        }
        $this->detail_model->recoverDb();

        $this->relation_model->add_lastcount($crawl_info['_id'], $insert_count);

        pr_exe_process(INSERT_COUNT, print_r($insert_count, true));

        //改变抓取状态
        $crawl_url_lists = $this->redis_model->get_redis_cache('url_relation', $cachekey);
        $crawl_url_lists[$crawl_info['url']] = 1;

        //如果抓取到的新数据少于20%， 则不抓取下一页
        if ($insert_count / count($relation_lists) * 100 >= 20){
            foreach ((array)$url_pages as $url)
            {
                if(in_array($url, array_keys($crawl_url_lists))) continue;

                $crawl_info['url'] = $url;
                $this->queue_model->add_queue(self::Q_RELATION, $crawl_info);
                $crawl_url_lists[$url] = 0;
            }
        }
        pr_exe_process(SET_CACHE, print_r($crawl_url_lists, true));

        $this->redis_model->set_redis_cache('url_relation', $cachekey, $crawl_url_lists);

        pr_exe_process(CRAWL_END);
    }

    /**
     * 抓取详细信息
     */
    public function crawl_detail($url='', $rule_id = 0, $classid = 1)
    {
        pr_exe_process(CRAWL_START);

        if($url){ $crawl_info = $this->getDetailInfo($classid, $url); $crawl_info['rule_id'] = $rule_id; $crawl_info['classid'] = $classid; }

        else $crawl_info = $this->queue_model->get_queue(self::Q_DETAIL);

        pr_exe_process(QUEUE_INFO, print_r($crawl_info, true));

        if(false == $crawl_info) return false;

        $detail_info = $this->htmlparser->start($crawl_info['url'], 3, $crawl_info['with_pic'],  $crawl_info['rule_id']);

        if(empty($detail_info) && empty($url) && $this->htmlparser->recrawl){
            $this->queue_model->add_queue(self::Q_FEED, $crawl_info);
            return false;
        }
        pr_exe_process(HTML_INFO, print_r($detail_info, true));

        if(empty($detail_info['content'])) return false;

        list($usec, $sec) = explode(" ",microtime());
        $msec = intval($sec*1000000) + intval($usec*1000000);

        $insert_data = array('created'=>$sec, 'mcreated'=>$msec, 'content'=>$detail_info['content']);

        if(!empty($detail_info['title'])) $insert_data['title'] = $detail_info['title'];

        $class_info = $this->class_model->findOne(array('classid'=>$crawl_info['classid']));

        $this->load->model('detail_model');

        $this->detail_model->initDb();
        $this->detail_model->setTableName($class_info['name']);
        $detail = $this->detail_model->findOneByUrl($crawl_info['url']);
        $this->detail_model->update($detail['_id'], $insert_data);
        $this->detail_model->updateStatus($detail['_id'], 1);
        $this->detail_model->recoverDb();

        pr_exe_process(CRAWL_END);

    }

    /**
     * 抓取详细信息
     */
    public function detail_title_crawl($url='', $with_pic = 0, $rule_id = 0, $classid = 1)
    {
        pr_exe_process(CRAWL_START);

        if($url) $queue_info = array('url'=>urldecode($url), 'with_pic'=>$with_pic, 'rule_id'=>$rule_id, 'classid'=>$classid,  'cachekey'=>$url);

        else $queue_info = $this->queue_model->get_queue(self::Q_DETAIL_TITLE);

        $cachekey = $queue_info['cachekey'];

        pr_exe_process(QUEUE_INFO, print_r($queue_info, true));

        if(false == $queue_info){
            sleep(10); return false;
        }

        $title_lists = $this->htmlparser->start($queue_info['url'], 1, $queue_info['with_pic'],  $queue_info['rule_id']);

        pr_exe_process(HTML_INFO, print_r($title_lists, true));

        if(empty($title_lists) && $this->htmlparser->recrawl){
            $this->queue_model->add_queue(self::Q_DETAIL_TITLE, $queue_info);
            return false;
        }

        $url_pages = $this->htmlparser->turn_page_url();

        $class_info = $this->class_model->findOne(array('classid'=>$queue_info['classid']));

        $this->load->model('detail_model');
        $this->detail_model->setTableName($class_info['name']);

        $insert_count = 0;

        foreach ($title_lists as $info) {
            if($this->detail_model->findOneByUrl($info['url'])) {
                continue;
            }
            $this->detail_model->add(0, $info['url'], array(), array('title'=>$info['tag']));
            $this->queue_model->add_queue(self::Q_DETAIL, array('url'=>$info['url'], 'with_pic'=>$queue_info['with_pic'], 'rule_id'=>$queue_info['rule_id'], 'classid'=>$queue_info['classid'] ));
            $insert_count++;
        }
        //改变抓取状态
        $crawl_url_lists = $this->redis_model->get_redis_cache('url_relation', $cachekey);
        $crawl_url_lists[$queue_info['url']] = 1;

        //如果抓取到的新数据少于20%， 则不抓取下一页
        if ($insert_count / count($crawl_url_lists) * 100 >= 20)
        {
            foreach ((array)$url_pages as $url){
                if(in_array($url, array_keys($crawl_url_lists))) continue;
                $crawl_info['url'] = $url;
                $this->queue_model->add_queue(self::Q_DETAIL_TITLE, $crawl_info);
                $crawl_url_lists[$url] = 0;
            }
        }
        pr_exe_process(SET_CACHE, print_r($crawl_url_lists, true));

        $this->redis_model->set_redis_cache('url_relation', $cachekey, $crawl_url_lists);

        pr_exe_process(CRAWL_END);

    }


    /**
     * 不在队列中也没抓取过的信息进行抓取
     */
    public function crawl_loss()
    {
        pr_exe_process(CRAWL_START);

        while (true) {
            $relation_lists = $this->relation_model->find(array('status' => 1));
            if(empty($relation_lists)){ sleep(15); continue; }
            else break;
        }
        foreach ($relation_lists as $crawl_info) {
            //如果上次抓取时间离现在小于10分钟，则不抓取
            if(( time() - $crawl_info['lasttime']) < 600) continue;

            $crawl_url_lists = $this->redis_model->get_redis_cache('url_relation', $crawl_info['url']);
            if(empty($crawl_url_lists)) continue;

            foreach ($crawl_url_lists as $url => $status)
            {
                if($status === 0){
                    $crawl_info['cachekey'] = $crawl_info['url'];
                    $crawl_info['url'] = $url;

                    $this->queue_model->add_queue(self::Q_RELATION, $crawl_info);
                    $this->relation_model->update_lasttime($crawl_info['_id']);

                    pr_exe_process(QUEUE_INFO, print_r($crawl_info, true));
                }
            }
        }
        pr_exe_process(CRAWL_END);
    }

    /**
     * 解锁
     */
    public function unlock()
    {
        $unlock_lists = $this->relation_model->find(array('status' => 1));

        if(false == $unlock_lists) return false;

        foreach ($unlock_lists as $crawl_info) {
            $unlock = true;
            $crawl_url_lists = $this->redis_model->get_redis_cache('url_relation', $crawl_info['url']);
            if(empty($crawl_url_lists)) continue;

            foreach ($crawl_url_lists as $url => $status) {
                if ($status === 0) {
                    $unlock = false;
                    break;
                }
            }
            if ($unlock) {
                $this->redis_model->del_redis_cache('url_relation', $crawl_info['url']);
                $info = $this->relation_model->findOneByUrl($crawl_info['url']);
                $this->relation_model->update_nexttime($info['_id']);
                $this->relation_model->update_status($info['_id'], 0);
            }
        }
    }

    /**
     * @param $url
     * @return bool
     */
    private function getCrawlInfo($url)
    {
        $url = urldecode($url);

        $unlock_lists = $this->relation_model->find(array('status' => 1));

        foreach ($unlock_lists as $crawl_info) {
            $crawl_url_lists = $this->redis_model->get_redis_cache('url_relation', $crawl_info['url']);
            if(empty($crawl_url_lists)) continue;

            if(in_array($url, array_keys($crawl_url_lists))){
                $crawl_info['cachekey'] = $crawl_info['url'];
                $crawl_info['url'] = $url;
                return $crawl_info;
            }
        }
        return false;
    }

    private function getDetailInfo($classid, $url){

        $class_info = $this->class_model->findOne(array('classid'=>$classid));

        $this->load->model('detail_model');
        $this->detail_model->setTableName($class_info['name']);

        $detail_info = $this->detail_model->findOneByUrl(urldecode($url));

        $this->detail_model->recoverDb();

        return $detail_info;
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */