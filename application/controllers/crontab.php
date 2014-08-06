<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Crontab extends CI_Controller {

    const Q_FEED = 'feed_crawl';
    const Q_RELATION = 'relation_crawl';
    const Q_DETAIL = 'detail_crawl';

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
        while (true) {
            $queue_info = $this->queue_model->get_queue(self::Q_FEED);
            if(empty($queue_info)){ sleep(10); continue; }
            else break;
        }

        $tag_lists = $this->htmlparser->start($queue_info['url'], 1);

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
        while (true) {
            $tagurls = $this->relation_model->nextCrawl();
            if(empty($tagurls)){ sleep(5); continue; }
            else break;
        }

        foreach ($tagurls as $info) {

            $crawl_url_lists = array();
            //更改抓取时间为现在
            $this->relation_model->update_lasttime($info['_id']);
            //更改抓取状态为正在抓取
            $this->relation_model->update_status($info['_id'], 1);

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
    public function crawl_relation()
    {
        while (true) {
            $crawl_info = $this->queue_model->get_queue(self::Q_RELATION);
            if(empty($crawl_info)){ sleep(2); continue; }
            else break;
        }
        $classid = $crawl_info['classid'];

        $cachekey = $crawl_info['cachekey'];

        $crawl_url_lists = $this->redis_model->get_redis_cache('url_relation', $cachekey);

        $crawl_url_lists[$crawl_info['url']] = 1;

        $this->redis_model->set_redis_cache('url_relation', $cachekey, $crawl_url_lists);

        $relation_lists = $this->htmlparser->start($crawl_info['url'], 2, $crawl_info['with_pic'],  $crawl_info['rule_id']);
        $url_pages = $this->htmlparser->turn_page_url();

        $class_info = $this->class_model->findOne(array('classid'=>$classid));

        $this->load->model('detail_model');
        $this->detail_model->setTableName($class_info['name']);

        //不存在该记录则存储
        $insert_count = 0;
        foreach ((array)$relation_lists as $url_info) {

            if ($this->detail_model->findOneByUrl($url_info['url'])) {
                continue;
            }
            $data['title'] = $url_info['title'];
            $data['description'] = $url_info['description'];
            $data['page_url'] = $crawl_info['url'];
            $data['with_pic'] = $crawl_info['with_pic'];

            $this->detail_model->add($crawl_info['_id'], $url_info['url'], $crawl_info['tags'], $data);
            $this->queue_model->add_queue(self::Q_DETAIL, array('url'=>$url_info['url'], 'classid'=>$classid ));
            $insert_count++;
        }
        $this->relation_model->add_lastcount($crawl_info['_id'], $insert_count);

        //如果抓取到的新数据少于20%， 则不抓取下一页
        if ($insert_count / count($relation_lists) * 100 < 20) {
            return false;
        }

        foreach ((array)$url_pages as $url)
        {
            if(in_array($url, array_keys($crawl_url_lists))) continue;

            $crawl_info['url'] = $url;
            $this->queue_model->add_queue(self::Q_RELATION, $crawl_info);

            $crawl_url_lists[$url] = 0;
            $this->redis_model->set_redis_cache('url_relation', $cachekey, $crawl_url_lists);
        }
    }


    /**
     * 抓取详细信息
     */
    public function crawl_detail()
    {
        while (true) {
            $crawl_info = $this->queue_model->get_queue(self::Q_DETAIL);
            if(empty($crawl_info)){ sleep(2); continue; }
            else break;
        }

        $detail_info = $this->htmlparser->start($crawl_info['url'], 3);

        $insert_data = array();

        if(empty($detail_info['content'])) return false;

        $insert_data['content'] = $detail_info['content'];

        if(!empty($detail_info['title'])) $insert_data['title'] = $detail_info['title'];

        $class_info = $this->class_model->findOne(array('classid'=>$crawl_info['classid']));

        $this->load->model('detail_model');

        $this->detail_model->setTableName($class_info['name']);

        $detail = $this->detail_model->findOneByUrl($crawl_info['url']);

        $this->detail_model->update($detail['_id'], $insert_data);

        $this->detail_model->updateStatus($detail['_id'], 1);
    }

    /**
     * 解锁
     */
    public function unlock()
    {
        while (true) {
            $unlock_lists = $this->relation_model->find(array('status' => 1));
            if(empty($unlock_lists)){ sleep(5); continue; }
            else break;
        }
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
                $info = $this->relation_model->findOneByUrl($crawl_info['url']);
                $this->relation_model->update_nexttime($info['_id']);
                $this->relation_model->init_lastcount($info['_id']);
                $this->relation_model->update_status($info['_id'], 0);
            }
        }
    }
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */