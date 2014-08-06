<?php
/**
 * 数据库操作
 * @ - 爬虫后台抓取使用库
 *
 * 站点相关表， 数据库操作数据库类
 * 只有这个类里有数据库操作，其他类都应该直接调用此类
 *
 * @package		Unotice v4
 * @author		Soften.cn Dev Team	| By QuWei
 * @copyright	Copyright (c) 2013, Unotice, Inc.
 * @link		http://www.soften.cn
 * @filesource
 */


class Relation_model extends CI_Model {

    var $db = 'index';
    var $tags_coll = "tags";

    /**
    * _id:  对url使用md5过的值，确保唯一。
    * feedid: 集合feedurl 字段_id.
    * url： 所抓取的url。
    * created:  创建时间.
    * lasttime:  上次抓取时间。
    * nexttime:  下次抓取时间。
    * lastcount: 上次抓取到的新信息数。
    * status:  抓取状态。
    * tags:  该标签所属标签列表。
    * with_pic : 抓取列表是否存在图片。1 ：存在， 0： 不存在
    * classid: 分类，（sports, humor, ......................）
    * ruleid:  匹配规则ID，默认为0。
     */
    var $feedurl_fields = array('_id', 'feedid', 'url', 'created', 'lasttime', 'nexttime', 'lastcount', 'status', 'classid', 'tags', 'with_pic', 'rule_id');

    // 0 未开始抓取，1 正在抓取.
    var $conf_status = array(0, 1);

    function __construct() {
        parent::__construct();

        $this->load->config('setting');
        $this->load->library('mongo_db');
        $db = $this->config->item('mongo_db');
        $this->mongo_db->switch_db($this->db);
    }

    /**
     * @access public
     * @param array/string $condition 要查寻的条件 [Optional]
     * @param int $offset 查寻时的偏移量 [Optional]
     * @param int $limit 每次查寻的记录数 [Optional]
     * @param  $total 要查寻排序的条件 [Optional]
     * @return array $mixed
     */
    public function find($condition=array(), $offset=0, $limit=20, $total = false){
        if( ! empty($condition) ) {
            $this->mongo_db->where($condition);
        }
        if($total){
            return $this->mongo_db->count($this->tags_coll);
        }
        $limit && $this->mongo_db->limit($limit);
        $offset && $this->mongo_db->offset($offset);

        return $this->mongo_db->get($this->tags_coll);
    }

    /**
     * 根据id获取信息
     * @param $id
     * @return mixed
     */
    public function findOne($condition)
    {
        if ($lists = $this->find($condition, 0, 1)) {
            return $lists[0];
        }
        return false;
    }
    /**
     * 根据id获取信息
     * @param $id
     * @return mixed
     */
    public function findOneByID($id)
    {
        $condition = array('_id'=>$id);
        return $this->findOne($condition);
    }

    /**
     * 根据url获取信息
     * @param $url
     * @return mixed
     */
    public function findOneByUrl($url)
    {
        return $this->findOneByID(md5($url));
    }

    /**
     * 获取需要抓取的url信息
     * @return mixed
     */
    public function nextCrawl()
    {
        //根据状态、下次抓取时间
        $this->mongo_db->where(array('status'=>0));
        $this->mongo_db->where_lt('nexttime', time());

        return $this->mongo_db->get($this->tags_coll);
    }

    public function add($pid, $url, $tags, $with_pic, $classid, $rule_id = 0)
    {
        $tagurl_info = array(
            '_id' => md5($url), 'pid'=>$pid, 'url' => $url, 'created' => time(), 'lasttime' => 0, 'nexttime' => 0, 'status' => 0,'tags' => $tags, 'with_pic'=>$with_pic, 'classid' => $classid, 'rule_id' => $rule_id
        );
        return $this->mongo_db->insert($this->tags_coll, $tagurl_info);
    }


    /**
     * 增加新抓取到的数据的数目
     * @param $id
     * @param $count
     * @return mixed
     */
    public function add_lastcount($id , $count)
    {
        $this->mongo_db->where(array('_id'=>$id));
        return $this->mongo_db->update($this->tags_coll, array('lastcount'=>$count), array(), '$inc');
    }

    public function del()
    {

    }

    public function update()
    {

    }


    /**
     * 更新抓取状态
     * @param $id
     * @param $status: 0, 1
     * @return bool
     */
    public function update_status($id, $status)
    {
        if (! in_array($status, $this->conf_status)) {
            return false;
        }
        $this->mongo_db->where(array('_id'=>$id));

        return $this->mongo_db->update($this->tags_coll, array('status'=>$status));
    }

    /**
     * 更新下次抓取时间
     * @param $id
     * @param bool $init 是否初始化为0
     * @return mixed
     */
    public function update_nexttime($id, $init = false)
    {
        $this->mongo_db->where(array('_id'=>$id));
        /** 初始化下次抓取时间 */
        if ($init) {
            return $this->mongo_db->update($this->tags_coll, array('nexttime'=>0));
        }

        $tagurl_info = $this->findOneByID($id);
        $interval_lists = $this->config->item('time_interval');

        foreach ($interval_lists as $time => $count_range) {
            if (count($count_range) == 1) {
                $interval = $time;
                break;
            }
            if ($tagurl_info['lastcount'] >= $count_range[0] && $tagurl_info['lastcount'] < $count_range[1]) {
                $interval = $time;
                break;
            }
        }
        return $this->mongo_db->update($this->tags_coll, array('nexttime'=>$interval), array(), '$inc');
    }

    /**
     * 更新上次抓取时间
     * @param $id
     * @param bool $init 是否初始化为0
     * @return mixed
     */
    public function update_lasttime($id, $init = false)
    {
        $this->mongo_db->where(array('_id'=>$id));
        /** 初始化上次抓取时间 */
        if ($init) {
            return $this->mongo_db->update($this->tags_coll, array('lasttime'=>0));
        }
        return $this->mongo_db->update($this->tags_coll, array('lasttime'=>time()));
    }

}

