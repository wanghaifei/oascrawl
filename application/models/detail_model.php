<?php

/**
 * Class Detail_model
 */

class Detail_model extends CI_Model {

    var $collection = 'detail';
    var $detail_table = '';

    /**
     * _id:  对url使用md5过的值，确保唯一。
     * tagid: 集合tags 字段_id. 父id
     * url： 所抓取的url。
     * created:  创建时间.
     * mcreated: 创建时间（毫秒）.
     * tags:  该标签所属标签列表。
     * status : -1 删除, 0 抓取未完成, 1 抓取完成
     * with_pic : 内容是否是图片。1 ：存在， 0： 不存在
     * title: 标题
     * description: 描述,缩率信息
     * content: 主要内容.
     */
    var $detail_fields = array();

    //  -1 删除, 0 抓取未完成, 1 抓取完成
    var $conf_status = array(-1, 0, 1);
    var $conf_division = array(1, 2);

    function __construct() {
        parent::__construct();
        $this->load->library('mongo_db');
        $this->mongo_db->switch_db($this->collection);
    }

    /**
     *
     * @param $tablename
     */
    public function setTableName($tablename)
    {
        $this->detail_table = $tablename;
    }

    /**
     * @access public
     * @param array/string $condition 要查寻的条件 [Optional]
     * @param int $offset 查寻时的偏移量 [Optional]
     * @param int $limit 每次查寻的记录数 [Optional]
     * @param  $total 要查寻排序的条件 [Optional]
     * @return array $mixed
     */
    public function find($condition=array(), $offset=0, $limit=20, $total = false)
    {
        if( ! empty($condition) ) {
            $this->mongo_db->where($condition);
        }
        if($total){
            return $this->mongo_db->count($this->detail_table);
        }
        $limit && $this->mongo_db->limit($limit);
        $offset && $this->mongo_db->offset($offset);

        return $this->mongo_db->get($this->detail_table);
    }

    /**
     *	Get the documents where the value of a $field is less than $previous_cursor
     *
     * @param $previous_cursor
     * @param $limit
     * @return mixed
     */

    public function findByLtMs($previous_cursor, $status = 1, $limit = 20)
    {
        $this->mongo_db->limit($limit);
        $this->mongo_db->order_by(array('mcreated'));
        $this->mongo_db->where(array('status'=>$status));
        $this->mongo_db->where_lt('mcreated', $previous_cursor);

        return $this->mongo_db->get($this->detail_table);
    }

    /**
     *	Get the documents where the value of a $field is greater than or equal to $next_cursor
     *
     * @param $next_cursor
     * @param $limit
     * @return mixed
     */
    public function findByGtMs($next_cursor, $status = 1, $limit = 20)
    {
        $this->mongo_db->limit($limit);
        $this->mongo_db->where(array('status'=>$status));
        $this->mongo_db->order_by(array('mcreated'=>1));
        $this->mongo_db->where_gt('mcreated', floatval($next_cursor));

        return $this->mongo_db->get($this->detail_table);
    }

    /**
     * @access public
     * @param int $offset 查寻时的开始时间 [Optional]
     * @param int $limit 查寻时的结束时间 [Optional]
     * @param  $total 要查寻排序的条件 [Optional]
     * @return array $mixed
     */
/*    public function findByTimeRange($start_time, $end_time, $total = false)
    {
        $this->mongo_db->where_gte('created', $start_time);
        $this->mongo_db->where_lt('created', $end_time);

        if($total){
            return $this->mongo_db->count($this->detail_table);
        }

        return $this->mongo_db->get($this->detail_table);

    }*/

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
     * @param $pid
     * @param $url
     * @param $tags
     * @param $classid
     * @return mixed
     */
    public function add($pid, $url, $tags, $data)
    {
        list($usec, $sec) = explode(" ",microtime());
        $msec = intval($sec*1000000) + intval($usec*1000000);

        $info = array(
            '_id' => md5($url), 'tagid'=>$pid, 'url' => $url, 'created' => $sec, 'mcreated'=>$msec, 'tags' => $tags, 'status'=>0
        );
        $data = array_merge($data, $info);
        return $this->mongo_db->insert($this->detail_table, $data);
    }

    /**
     * @param $id
     * @param $data
     * @return bool
     */
    public function update($id, $data)
    {
        $this->mongo_db->where(array('_id'=>$id));
        return $this->mongo_db->update($this->detail_table, $data);
    }

    /**
     * 更新抓取状态
     * @param $id
     * @param $status: 0, 1
     * @return bool
     */
    public function updateStatus($id, $status)
    {
        if (! in_array($status, $this->conf_status)) {
            return false;
        }
        $this->mongo_db->where(array('_id'=>$id));

        return $this->mongo_db->update($this->detail_table, array('status'=>$status));
    }

/*    private function switch_coll($classid)
    {
        $class_info = $this->class_model->findOne(array('classid'=>$classid));
        $this->detail_table = $class_info['name'];

        return $this->detail_table;
    }*/

}

