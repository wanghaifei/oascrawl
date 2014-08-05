<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Api extends CI_Controller {

    private $limit = 0;

    function __construct() {
        parent::__construct();
    }

    /**
     * @param $coll 要查找的集合
     * @param int $offset
     * @param int $limit 返回数目
     */
    public function get_data($coll, $offset=0, $limit=20)
	{
        $this->limit = $limit;

        $this->load->model('detail_model');

        $this->detail_model->setTableName($coll);

        $data = $this->detail_model->find(array(), $offset, $limit);

        echo json_encode($data);
	}

    /**
     * @param $coll 要查找的集合
     * @param int $next_cursor 毫秒
     * @param int $limit 返回数目
     * @usage : next_cursor('humor', 1405385114351, 20)
     */
    public function next_cursor($coll, $next_cursor = 0, $limit = 20){

        $this->load->model('detail_model');

        $this->detail_model->setTableName($coll);

        $results = $this->detail_model->findByGtMs($next_cursor, $status = 1,  $limit);

        $this->response($results, $limit);
    }

    /**
     * @param $coll 要查找的集合
     * @param int $previous_cursor 毫秒
     * @param int $limit 返回数目
     * @usage : next_cursor('humor', 1405385114.351, 20)
     */
    public function previous_cursor($coll, $previous_cursor =0, $limit = 20)
    {
        $this->load->model('detail_model');

        $this->detail_model->setTableName($coll);

        $results = $this->detail_model->findByLtMs($previous_cursor, $status = 1, $limit);

        $this->response($results, $limit);
    }

    /**
     * 响应请求
     * @param $data
     * @param $limit
     */
    private function response($data, $limit)
    {
        $respose = array('data'=>array(), 'hasvisible'=>false, 'previous_cursor'=>0, 'next_cursor'=>0, 'total_number'=>0);

        if(! empty($data))
        {
            $count = count($data);
            $hasvisible = $count < $limit ? false : true;

            $respose['data'] = $data;
            $respose['hasvisible'] = $hasvisible;
            $respose['previous_cursor'] = $data[0]['mcreated'];
            $respose['next_cursor'] = $data[$count-1]['mcreated'];
            $respose['total_number'] = $count;
        }

        echo json_encode($respose);
    }
    /**
     * 调试
     */
    function test(){
        $article_list = $this->get_article_date_range(time()-3600,time());
        print_r($article_list);
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */