<?php
/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-6-30
 * Time: 下午5:57
 */

class Cookie_model extends CI_Model {

    var $db = 'index';
    var $cookie_coll = "cookie";

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
            return $this->mongo_db->count($this->cookie_coll);
        }
        $limit && $this->mongo_db->limit($limit);
        $offset && $this->mongo_db->offset($offset);

        return $this->mongo_db->get($this->cookie_coll);
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
     * 插入数据
     * @param $data
     * @return bool
     */
    public function add($data)
    {
        $date = date('Ymdhis');
        $info = array(
            'created' => $date, 'host'=>$data['host'], 'cookie' => $data['cookie'],
        );
        return $this->mongo_db->insert($this->cookie_coll, $info);
    }

    /**
     * @param $host
     * @return bool
     */
    public function rand_cookie($host)
    {
        if (false == $hosts = $this->find(array('host' => $host))) {
            return false;
        }
        $key = rand(0, count($host)-1);

        return $hosts[$key];
    }

}