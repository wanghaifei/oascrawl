<?php
/**
 * 搜索引擎配置文件
 */
$config['class'] = array(1=>'humor');

//tag、relation、detail可以为空数组, 如果为空则使用默认规则获取信息


/*
$config['rules'][1] = array(
    'id'=>1,
    'class' => 1,
    //标签规则
    'tag' => array(
        'html_area' => '<div class="content">(.*?)<div class="sidebar-right">',
        'html_area_filter' => '',
        'item_area' => '<div class="article-header">(.*?)<div class="article-more">',
        'item_area_filter' => '',
        'field_need' => array('must'=>array('url'), 'no_must'=>array('tag')),
        //规则列表
        'field_rules' => array(
            array(
                //通用规则列表
                'general' => array(
                    '<a .*?href="[url]".*?>[title]</a>',
                ),
                'tag' => '',
                'url' => '',
            ),
            array(),
        ),
    ),
    //相关信息规则
	'relation'=> array(

	),
    //详情规则
	'detail' => array(

	),
);*/

$config['rules'][1] = array(
    //相关信息规则
    '2'=> array(
        'html_area'=>array('type'=>'class', 'val'=>'content'),
        'item_area'=>array('type'=>'class', 'val'=>'article'),
    ),
    //详情规则
    'detail' => array(

    ),

/*    'field'=>array(
        'filter' => array(),
        'description'=>array('type'=>'class', 'val'=>'article-content'),
    ),*/
);
