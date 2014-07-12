<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-7-1
 * Time: 下午3:01
 */

class Test extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('class_model');
        $this->load->model('feeds_model');
        $this->load->model('relation_model');
    }
    public function index(){
        phpinfo();
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
        //$this->feeds_model->add('http://www.999gag.com/en/tag', 1, array('gaoxiao'), 1);
        //$this->feeds_model->add('http://www.komikdunya.com/komikresimler/', 1, array('gaoxiao'), 1);
        //$this->feeds_model->add('http://www.komikdunya.com/karikaturler/', 1, array('gaoxiao'), 1);
        $this->feeds_model->add('http://www.komikler.com/komikresim/', 1, array('gaoxiao'), 1);
    }

    public function connect_redis(){
        $this->redis = new Redis();
        $this->channel_queue = 'staffs';
        $this->redis->connect('127.0.0.1', '6379');
        $this->redis->set('current_index','aaa');
    }

}