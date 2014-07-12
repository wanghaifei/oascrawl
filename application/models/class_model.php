<?php
/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-6-30
 * Time: 下午5:57
 */

class class_model extends CI_Model {

    var $db = 'index';
    var $class_coll = "class";

    /**
     *     _id:
     *    name: 体育,新闻,幽默......
     * classid: 1,2,3.....
     */
    function __construct() {
        parent::__construct();
        $this->load->library('mongo_db');
        $this->mongo_db->switch_db($this->db);
    }

    public function find($condition=array(), $offset=0, $limit=20, $total = false) {
        if( ! empty($condition) ) {
            $this->mongo_db->where($condition);
        }
        if($total){
            return $this->mongo_db->count($this->class_coll);
        }
        $limit && $this->mongo_db->limit($limit);
        $offset && $this->mongo_db->offset($offset);

        return $this->mongo_db->get($this->class_coll);
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
     * 插入数据
     * @param $data
     * @return bool
     */
    public function add($data)
    {
        if($this->findOne(array('name'=>$data['name']))){
            return false;
        }
        if ($maxid = $this->findOneMaxId()) {
            $classid = $maxid + 1;
        }else {
            $classid = 1;
        }
        $info = array(
            'created' => time(), 'name' => $data['name'], 'classid'=>$classid
        );
        return $this->mongo_db->insert($this->class_coll, $info);
    }

    /**
     * 查找最大classid
     * @return bool
     */
    public function findOneMaxId()
    {
        $this->mongo_db->limit(1);
        $this->mongo_db->order_by(array('classid'));
        $results = $this->mongo_db->get($this->class_coll);

        if(!empty($results) && is_array($results)) return $results[0]['classid'];

        return false;
    }

}