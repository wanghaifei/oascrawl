<?php

/**
 * Class Feedurl_model
 */

class Feeds_model extends CI_Model {

    /**
     * @var string
     */
    var $db = 'index';
    var $feeds_coll = "feeds";

    /**
     * _id:  对url使用md5过的值，确保唯一。
     * url： 所抓取的url。
     * created:  创建时间.
     * status:  抓取状态。
     * classid: 分类，（sports, humor, ......................）
     * tags:  该标签所属标签列表。
     * rel_with_pic: 相关列表是否存在图片。  1 ：存在， 0： 不存在
     * rule_id:  匹配规则ID，默认为0。
     */
    //     * filter_lr_content: 是否过滤左右内容。(有的站点标签列表在左边或右边，也有的在中间)   1：过滤， 0：不过滤。
    var $feeds_fields = array('_id', 'url', 'created', 'status', 'classid', 'tags', 'rel_with_pic', 'rule_id');

	function __construct() {
		parent::__construct();
        $this->load->library('mongo_db');
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
            return $this->mongo_db->count($this->feeds_coll);
        }
        $limit && $this->mongo_db->limit($limit);
        $offset && $this->mongo_db->offset($offset);

        return $this->mongo_db->get($this->feeds_coll);
    }

    /**
     * 根据查询条件获取信息
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
        $condition = array('_id'=>new MongoId($id));
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

    public function add($url, $classid, $tags, $rel_with_pic = 1, $rule_id = 0)
    {
        $insert_data = array('_id'=>md5($url), 'url'=>$url, 'created'=>time(), 'status'=>1, 'classid'=>$classid, 'tags'=>$tags, 'rel_with_pic'=>$rel_with_pic, 'rule_id'=>$rule_id);
        return $this->mongo_db->insert($this->feeds_coll, $insert_data);

    }
/*    public function add1($fields)
    {
        if (false == $this->check_fields($fields)) {
            return false;
        }
        $need_fields = array('url', 'classid', 'tags', 'filter_lr_content', 'rel_exist_pic', 'rule_id');

        $insert_data = array('_id'=>md5($url), 'url'=>$url, 'created'=>time(), 'status'=>1, 'classid'=>$classid, 'tags'=>$tags, 'rule_id'=>$rule_id);
        return $this->mongo_db->insert($this->feeds_coll, $insert_data);

    }*/

    public function del()
    {

    }

    public function update()
    {

    }

    public function check_fields($fields)
    {
        if(count(array_intersect(array_keys($fields), $this->feeds_fields) > count($fields))){
            return true;
        }
        return false;
    }

    public function need_fields($need_fields)
    {
        if(count(array_diff(array_keys($need_fields), $this->feeds_fields) > 0)){
            return false;
        }
        return true;
    }
}

