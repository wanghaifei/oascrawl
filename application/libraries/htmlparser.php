<?php
/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-6-18
 * Time: 下午3:46
 */

define('UPLOADFILE', 'http://www.komiksurat.com/upload.php');
//phpQuery.php 文件存在编码问题
//require_once dirname(__FILE__).'/phpQuery/phpQuery/phpQuery.php';
require_once dirname(__FILE__).'/phpQuery/phpQuery/phpQuery-onefile-ipatched.php';

class htmlparser {
    /** 当前编码 @var string */
    private $charset = 'utf-8';
    /** 详情页的所抓内容的最小宽度 @var string */
    private $min_width = '550';
    /** 符号 @var array */
    private $symbol = array(', ', ',','。', '?', ':', '!', ';');
    /** 抓取类型: 1 抓标签列表， 2 抓标签相关列表，3 抓详细 @var array */
    private $conf_html_type = array(1, 2, 3);
    /** 相关信息类型: 1 图文混排, 2 文字, 3 链接, @var array  */
    private $conf_rel_type = array(1, 2, 3);
    /** 抓类型所调用的计算分数的方法 @var array */
    private $method_lists = array(
        1 => array('children_similar'),
        2 => array('children_similar', 'children_same_url',),
        3 => array('tag_count', 'title', 'word', 'pic', 'symbol',),
    );
    /** 无效标题 @var array */
    private $filter_title = array('502 Bad Gateway', 'NOT FOUND', 'Not Found');

    /** 移除元素规则列表 @var array */
    private $dom_filter_rules = array('<script.*?>.*?</script.*?>', '<embed .*?>', '<iframe.*?></iframe>', '<form .*?</form>', '<!--.*?-->', '<\=".*?"', '>\=".*?"');

    /** 抓取页的相关信息 */
    private $html_type;        /** 抓取类型 @var int */
    private $with_pic;         /** 是否抓取图片 @var int */
    private $dom_lists;         /** 所要抓取的dom @var array  */
    private $host;             /** 抓取域名 @var string */
    private $html;             /** 抓取到的页面内容 @var string */
    private $crawl_url;        /** 所要抓取的url @var  string*/
    private $html_charset;     /** 抓取到的页面编码 @var string */
    private $score = array(); /** 标签lable信息 @var array */
    private $html_pic_url = array(); /** 页面的图片信息 */

    private $lable_rules = array(
        'a'=> array('<a [^<>]*?href="([^javascript].+?)".*?>(.*?)</a>', '<a [^<>]*?onclick="window.location=\'(.+?)\'".*?>(.*?)</a>'),
    );

    public $recrawl = false;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->_ci = get_instance();
        $this->_ci->load->helper('global');
        $this->_ci->config->load('parser_rules');

        $this->_ci->load->model('log_model');
        $this->_ci->load->model('cookie_model');
    }

    /**
     * 初始化
     * @param $url
     */
    private function _init($url){
        $this->crawl_url = $url;
        //保存域名
        if ($url_info = parse_url($url)) {
            $this->host = $url_info['scheme'].'://'.$url_info['host'];
        }
        //抓取站点
        $re_count = 3;
        for ($i = 1; $i <= $re_count; $i++)
        {
            $header = array();
            if($cookie = $this->_ci->cookie_model->rand_cookie($url_info['host'])){
                $header[] = 'Cookie:'.$cookie['cookie'];
            }
//            $this->html = file_get_contents(APPPATH . 'cache/detail.html');
//            break;
            $http_info = send_http(htmlspecialchars_decode($url), array(), $header);
            if($this->html = $http_info['data']) break;
        }
        if(empty($this->html)){
            $this->recrawl = true;
            return false;
        }
        //过滤干扰标签
        foreach ($this->dom_filter_rules as $rule) {
            $this->html = preg_replace('|' . $rule . '|ims', '', $this->html);
        }

        //获取站点编码
        preg_match('|charset=(.+?)"|ims', $this->html, $charset);
        $this->html_charset = strtoupper(str_replace('"','', $charset[1]));
        //编码转换
        if(str_replace('-','',$this->html_charset) != str_replace('-','',$this->charset))
            $this->html = mb_convert_encoding($this->html, $this->charset, $this->html_charset);

        //phpquery 初始化
        phpquery::newDocumentHTML($this->html);
        phpQuery::$defaultCharset = $this->charset;
        //如果抓取标签列表页，则不过滤左右边框，否则过滤
        $body = ($this->html_type == 1) ? true : false;
        if ($body == true || false == $this->dom_lists = $this->get_css_dom_lists()){
           $this->dom_lists  = array('body');
        }
        //<img src="aa.jpg"</a>  or  <img src="aa.jpg" <="" a="">
        $this->html = str_replace('"</a>', '">', $this->html);

        $this->html = preg_replace('|.+<body |ims', '<!Doctype html><html xmlns=http://www.w3.org/1999/xhtml><body ', $this->html);

        $this->html = preg_replace('|<style.*?</style>|ims', '', $this->html);
        $this->html = $this->filter_lable($this->html, array('tr'));

        phpquery::newDocumentHTML($this->html);

        return true;
    }

    /**
     * @param $url
     * @param int $type
     * @param bool $pic
     * @return array|bool
     */
    public function start($url, $html_type = 1, $with_pic = true, $rule_id = 0)
    {
        $this->with_pic = $with_pic;
        $this->html_type = $html_type;

        if(false == $this->_init($url)) return false;
        //通过配置文件规则获取信息
        if(true == $data = $this->results_byrule($rule_id)){
            return $data;
        }
        //获取每个标签的分数
        foreach ($this->dom_lists as $dom) {
            $this->calculate_dom_score($dom);
        }
        //截取分数最高的10个标签
        $score_lists = array_splice(array_sort($this->score, 'score'), 0, 10);
        //测试
//        $this->test($score_lists);
        //获取每个标签内有效的子标签列表
        foreach($score_lists as $key => $score_info){
            if ($valid_info = $this->get_children_results($score_info['obj'])) {
                $results[] = $valid_info;
            }
        }
        if(! $results) return false;
        if($this->html_type != 2) return $results[0];

        $valid_result = array();
        foreach($results as $info){
            if(count($info) > count($valid_info)) $valid_result = $info;
        }
        return $valid_result;
    }

    /**
     * 测试
     * @param $score_lists
     */
    private function test($score_lists)
    {
        header("Content-type: text/html; charset=utf-8");

        foreach($score_lists as $score_info){
            echo '总分： '.$score_info['score'] ."<br>";
            echo '统计： '.print_r($score_info['statistics'], true). "<br>";
            echo 'HTML： '.$score_info['obj']->html(). "<br>";
            echo "<br>-----------------------------------------------------------------<br>";
        }
        exit;
    }

    /**
     * 计算每个标签的分数
     * @param $dom
     * @return bool
     */
    private function calculate_dom_score($dom)
    {
        //标签内无内容，则忽略
        if(strlen(trim(strip_tags(pq($dom)->html(), '<img>'))) === 0 ){
            return false;
        }
        $dom_children = pq($dom)->children();

        //如果没有子标签，则不计算分数
        if (false == trim(pq($dom_children)->eq(0))) return false;

        foreach($dom_children as $elem){
            $this->calculate_dom_score($elem);
        }

        $currentScore = 0;
        $statistics = array();
        $arrScore = $this->score;

        $method_lists = $this->method_lists;

        foreach ($method_lists[$this->html_type] as $method) {
            $currentScore += $statistics[$method] = $this->{$method."_score"}($dom);
        }
        $arrScore[] = array('score'=>$currentScore, 'obj'=>pq($dom), 'statistics'=>$statistics);
        $this->score = $arrScore;
    }

    /**
     * 计算子标签相似度
     * @param $dom
     * @return int
     */
    private function children_similar_score($dom)
    {
        $currentScore = 0;
        $html_lists = array();
        $dom_children = pq($dom)->children();

        for ($k = 0; $k < count($dom_children); $k++) {
            $html = pq($dom_children)->eq($k);
//            $html = str_replace( array("\r\n", "\r", "\n", "\t", "<br>", "&nbsp;"), '', $html);
            //没有图片或没有内容则忽略
            if( ! strip_tags($html, '<img>')) continue;
            //去除所有的text
            $html = preg_replace('/(?<=[>])[^<>]+?(?=[<])/is', '', $html);
            $html = preg_replace('|<a .*?>|ims', '<a>', $html);
            $html = preg_replace('|<img .*?>|ims', '<img>', $html);
            $html_lists[] = trim($html);
        }
        $lable_count = count($html_lists);

        for ($i = 0; $i < $lable_count-1; $i++) {
            for ($j=$i+1; $j < $lable_count - $i; $j++) {
                similar_text($html_lists[$i], $html_lists[$j], $percent);
                $currentScore += $percent - (100 - $percent);
                //都有图片，并且相似性较高，则加分
                if (strstr($html_lists[$i], '<img>') && strstr($html_lists[$i], '<img>') && $percent >= 60) {
                    $currentScore += 100;
                }
            }
        }
        return $currentScore;
    }

    /**
     * 计算子标签内url相似度
     * @param $dom
     * @return int
     */
    private function children_same_url_score($dom)
    {
        $currentScore = 0;

        $dom_children = pq($dom)->children();
        //如果没有子标签，则不计算分数
        if (false == trim(pq($dom_children)->eq(0))) {
            return $currentScore;
        }
        $dom_count = count($dom_children);
        if($dom_count <= 1) return $currentScore;

        //标签内有相同链接地址
        for ($k = 0; $k < $dom_count; $k++) {
            $html = pq($dom_children)->eq($k);
            if (preg_match_all('|<a .*?href="(.*?)"|ims', $html, $out)) {
                $img_count = count($out[1]);
                for ($i = 0; $i < $img_count-1; $i++) {
                    for ($j=$i+1; $j < $img_count - $i; $j++) {
                        if(strcmp($out[1][$i], $out[1][$j]) === 0 ){
                            $currentScore += 10;
                        }
                    }
                }
            }
        }

        return $currentScore;
    }

    /**
     * 获取子标签数的分数
     * @param $html
     * @return int
     */
    private function tag_count_score($dom)
    {
        $currentScore = 0;
        $html = pq($dom)->html();

        //统计标签内的子标签有效标签数
        $all_tag_count = 0;
        if (preg_match_all('|<\w{1,7} [^<>]*?>|ims', $html, $out)) {
            $all_tag_count = count($out[0]);
        }
        switch($this->html_type){
            case 3 :
                $currentScore -= $all_tag_count * 10;
                break;
        }
        return $currentScore;
    }

    /**
     * 标题分数
     * @param $html
     * @return int
     */
    private function title_score($dom)
    {
        $currentScore = 0;
        $html = pq($dom)->html();

        $special_tags = array('<h1>', '<h2>');
        foreach ($special_tags as $tag) {
            $count = substr_count($html, $tag);

            switch($this->html_type){
                case 3 :
                    $currentScore -= $count*100;
                    break;
            }
        }
        return $currentScore;
    }

    /**
     * 文字分数
     * @param $html
     * @return float|int
     */
    private function word_score($dom)
    {
        $currentScore = 0;
        $html = pq($dom)->html();
        $currentHtml = strip_tags($html, '<a>');

        //文字带有url的,字符越长减分越大
        if (preg_match_all('|<a href=.*?>(.*?)</a>|ims', $currentHtml, $out)) {
            foreach($out[1] as $content){
                $currentScore -= mb_strlen(str_replace(' ', '', str_replace(' ', '', strip_tags($content)))) * 10;
            }
        }
        //统计文字长度
        $replace = preg_replace('|<a .*?</a>|ims', '', $currentHtml);
        $replace = str_replace(' ', '', $replace);
        $currentScore += mb_strlen($replace);

        return $currentScore;
    }

    private function pic_info($dom)
    {
        $currentScore = 0;
        $html = pq($dom)->html();

/*        if(preg_match('|^\<img (.*?)>|ims', trim($html), $pic_info)){
            foreach ($preg_lists as $preg_info) {
                if (preg_match('|' . $preg_info . '|ims', $pic_info[1], $score)) {
                    $currentScore += trim($score[1]);
                    return $currentScore;
                }
            }
            //匹配父a标签的高宽
            $parent = trim(pq($dom)->parent()->find('a'));

            if(preg_match('|^\<a (.*?)>|ims', $parent, $a_attr)){
                foreach ($preg_lists as $preg_info) {
                    if (preg_match('|' . $preg_info . '|ims', $a_attr[1], $score)) {
                        $currentScore += trim($score[1]);
                        break;
                    }
                }
            }
        }*/
    }
    /**
     * 图片分数
     * @param $html
     * @return float|int
     */
    private function pic_score($dom)
    {
        $currentScore = 0;
        $html = pq($dom)->html();
        //没图片则不计算分数
        if(! strstr($html, '<img ')) return $currentScore;

        //图片或标签a 有高宽属性
        $preg_lable = array('<img (.*?)>' , '<a ([^<]*?<img src=".*?".*?)</a>');
        $preg_lists = array('width="(.+?)"', 'width:(.+?)px', 'height="(.+?)"', 'height:(.+?)px');

        $img_html = strip_tags($html, '<img><a>');

        //获取标签里最大的图片的高宽
        $max_width = 0;
        foreach ($preg_lable as $lable_info) {
            if (preg_match_all('|' . $lable_info . '|ims', $img_html, $attr_lists)) {
                foreach($attr_lists[1] as $pic_info){
                    foreach ($preg_lists as $preg_info) {
                        if (preg_match('|' . $preg_info . '|ims', $pic_info, $score)) {
                            $score = intval(trim($score[1]));
                            if($score > $max_width)  $max_width = $score;
                        }
                    }
                }
            }
        }
        $currentScore += $max_width;

        //下载图片计算高宽
        if ($currentScore === 0) {
            if(preg_match('|<img .*?src="(.*?)"|ims', $html, $img_src)){
                $pic_info = $this->get_pic_info($img_src[1]);
                if(!empty($pic_info['width'])) $currentScore += $pic_info['width'];
            }
        }

        return $currentScore;
    }

    private function bak()
    {
/*        //如果图片有链接
        $all_pic_src = $pic_with_href = array();
        $img_html = strip_tags($html, '<img><a>');

        //所有图片src
        if (preg_match_all('|<img src="(.*?)"|ims', $img_html, $out)) {
            $all_pic_src = $out[1];
        }
        //有链接的图片src
        $pic_with_href = array();
        if (preg_match_all('|<a href=[^<]*?<img src="(.*?)".*?</a>|ims', $img_html, $out)) {
            $pic_with_href = $out[1];
        }
        //有链接的图片不获取图片宽度，减分
        $currentScore -= count($pic_with_href) * 200;

        //没有链接的图片的src,并获取图片宽度
        if ($pic_diff = array_diff($all_pic_src, $pic_with_href)) {
            foreach ($pic_diff as $src) {
                $currentScore += $this->pic_width($src);
            }
        }
        return $currentScore;*/

    }

    /**
     * 符号的分数
     * @return float
     */
    private function symbol_score($dom)
    {
        $currentScore = 0;
        $html = pq($dom)->html();
        $html = strip_tags($html);
        //统计符号出现次数
        $symbol_count = 0;
        foreach ($this->symbol as $symbol) {
            $symbol_count += substr_count($html, $symbol);
        }
        $currentScore += $symbol_count / 2 * 100;

        return $currentScore;
    }

    /**
     * 获取有效信息列表
     * @param $dom
     * @return array|bool
     */
    private function get_children_results($dom, $get_children = true)
    {
        $ret_info = array();
        $dom_children = $get_children ? pq($dom)->children() : pq($dom);

        switch($this->html_type){
            case 1 :
                for ($k = 0; $k < count($dom_children); $k++) {
                    $html = pq($dom_children)->eq($k);

                    if (! preg_match_all('|<a [^<>]*?href="([^#]+?)".*?>(.*?)</a>|ims', trim($html), $out)) continue;

                    if(count($out[1]) > 1 || strstr($out[2][0], '<img ')) return false;

                    $url = $out[1][0];
                    $url = $this->add_host($url);
                    $tag = trim(strip_tags($out[2][0]));

                    $ret_info[] = array('tag'=>$tag, 'url'=>htmlspecialchars_decode($url));
                }
                return $ret_info;

            case 2 :

                for ($k = 0; $k < count($dom_children); $k++) {

                    $html = trim(pq($dom_children)->eq($k));
                    //if(strpos($html, '<a ') === 0)continue;
                    if (false == str_replace(' ', '', strip_tags($html, '<img>')) && false == $this->get_valid_title(preg_replace('|<a .*?</a>|ims', '', $html))) continue; //不能只是链接地址
                    if (! preg_match_all('|<a [^<>]*?href="(.+?)".*?>(.*?)</a>|ims', trim($html), $out)) continue;
                    $url_lists = $out[1];
                    $content_lists = $out[2];
                    //获取重复地址
                    if ($same_urls = array_diff_key($url_lists, array_unique($url_lists))) {

                        $keyArr = array_keys($same_urls);
                        $key_1 = $keyArr[0];
                        $key_2 = array_search($url_lists[$key_1], $url_lists);
                        $content_lists = array($key_2=>$content_lists[$key_2], $key_1=>$content_lists[$key_1]);
                        //删除more链接
                        if(! strstr($content_lists[$key_1], '<img ') && mb_strlen($content_lists[$key_1]) < 30) $html = str_replace($content_lists[$key_1], '', $html);
                    }

                    foreach ($content_lists as $key=>$content)
                    {
                        $temp_content = trim(strip_tags($content));
                        if (false != str_replace(' ', '', $temp_content)) {
                            $title = $temp_content;
                            $html = str_replace($title, '', $html);
                            $url = $this->add_host($url_lists[$key]);
                            break;
                        }
                    }
                    //抓取url存在于列表中,则跳过
                    if($this->crawl_url == $url) return false;

                    $html = substr($html, 0);
                    $pics = $this->get_valid_pic_url($html);

                    if($this->with_pic && ! $this->isValidPic($pics)) continue;

                    $relation_info['url'] = htmlspecialchars_decode($url);
                    $relation_info['title'] = $this->get_valid_title($title);
                    $relation_info['description']['pics'] = $pics;
                    $relation_info['description']['words'] = $this->get_content_words($html);

                    $ret_info[] = $relation_info;
                }
                return $ret_info;

            case 3 :
                $html = pq($dom)->html();
                if (false == str_replace(' ', '', strip_tags(preg_replace('|<a .*?</a>|ims', '', $html)))) return false;

                $pics = $this->get_valid_pic_url($html);
                if($this->with_pic && ! $this->isValidPic($pics)) return false;

                $ret_info['content']['pics'] = $pics;
                $ret_info['content']['words'] = $this->get_content_words($html);

                if (preg_match('|<h[1-2]>(.+?)</h[1-2]>|ims', $html, $out)) {
                    $ret_info['title'] = $this->get_valid_title($out[1]);
                    $ret_info['content']['words'] = $this->get_content_words(str_replace($out[0], '', $html));
                    if(!empty($ret_info['title']) && in_array($ret_info['title'], $this->filter_title)) return false;;
                }
                return $ret_info;
        }
    }

    /**
     * @param array $pics
     * @return bool
     */
    private function isValidPic(array $pics)
    {
        $width = $this->html_type == 2 ? 100 : 250;
        $pic_info_lists = array_sort($pics, 'width');

        if (!empty($pic_info_lists[0]['width']) && $pic_info_lists[0]['width'] > $width){
            return true;
        }else{
            return false;
        }
    }

    private function get_valid_pic_url($content)
    {
        $pic_info_lists = array();
        if(preg_match_all('|<img .*?src="(.*?)"|ims', $content, $img_src)){

            foreach($img_src[1] as $pic_url){
                $pic_info = $this->get_pic_info($pic_url);
                if(is_numeric($pic_info['width']) && $pic_info['width'] > 1) $pic_info_lists[] = $pic_info;
            }

        }
        return $pic_info_lists;
    }

    /**
     * 获取有效title
     * @param $title
     * @return mixed
     */
    private function get_valid_title($title)
    {
        $arr = array("\r\n", "\r", "\n", "\t", "<br>");
        return trim(str_replace($arr, " ", strip_tags($title)));
    }

    /**
     * 获取有效的content, description
     * @param $content
     * @return mixed|string
     */
    private function get_content_words($content)
    {
        $content = str_replace(array("\r\n", "\r", "\n", "\t"), "", $content);

        if(! str_replace(array(' ', '&nbsp;'), '', strip_tags($content))) return;

        $content = strip_tags($content, '<ul><li><br><p>');
        //去除标签属性
        $filter_attr = array('id', 'class', 'style', 'width', 'height', 'onload', 'onclick', 'onsubmit', 'onchange', 'onblur', 'onkeydown', 'onkeyup', 'onmouseout', 'onmouseover');
        foreach($filter_attr as $attr){
            $content = preg_replace('| ('.$attr.'="[^<]*?")|ims', '', $content);
        }
        //保存图片，并替换图片地址
/*        if(preg_match_all('|<img .*?src="(.*?)"|ims', $content, $img_src))
        {
            foreach ($img_src[1] as $src_info) {
                $pic_info = $this->get_pic_info($src_info);

                if(!empty($pic_info['width']) && $pic_info['width'] > 0) $content = str_replace($src_info, $pic_info['url'], $content);
                else $content = preg_replace('|<img .*?src="'.$src_info.'".*?>|ims', '', $content);

                if($error_id = get_error($src_info)) $this->log_model->record($src_info, $pic_info, $error_id);
            }
        }*/
        return trim($content);
    }

    private function get_error($repose_info){
        $error_id = 0;

        if(empty($repose_info)) $error_id = IS_EMPTY;
        elseif(isset($http_info['http_code'])) $error_id = ERROR_PAGE;
        elseif($this->html_type == 3 && !empty($repose_info['width']) && $repose_info['width'] <= 200) $error_id = IMG_SIZE_SMALL;

        return $error_id;

    }

    /**
     * 过滤标签
     * @param $html
     * @param $lable_lists
     * @return mixed
     */
    private function filter_lable($html, $lable_lists)
    {
        foreach ($lable_lists as $lable) {
            $html = str_replace('</'.$lable. '>', '', $html);
            $html = preg_replace('|<'.$lable. '[^<]*?>|ims', '',$html);
        }
        return $html;
    }

    public function get_pic_info($pic_url){

        $pic_url = $this->add_host($pic_url);
        if(isset($this->html_pic_url[$pic_url])){
            return $this->html_pic_url[$pic_url];
        }
        $http_info = send_http(UPLOADFILE . '?url='. urlencode($pic_url));

        $pic_info = $http_info['data'];

        $pic_info = json_decode($pic_info, true);

        $this->html_pic_url[$pic_url] = $pic_info;

        return $pic_info;

    }

    /**
     * 删除外站广告
     */
    private function filter_advertise()
    {

    }

    /**
     * 尽可能删除左右无关的信息
     */
    private function remove_left_right_content()
    {

    }

    /**
     * 获取css文件内的元素名 example:
     * @return array|bool
     */
    private function get_css_dom_lists()
    {
        $dom_arr = $css_content = array();

        $css_rule = '<style.*?>(.*?)</style>';
        $css_file_rule = '<link [^<>]*?href="([^<>]*?\.css.*?)"';
        $css_content_rule = '\}([^}{]*?)\{[^}{]*?width:(\d{3})px';

        //页面内css
        if (preg_match_all('|'.$css_rule.'|ims', $this->html, $out)) {
            $css_content = $out[1];
        }
        //css文件中的css
        if (preg_match_all('|'.$css_file_rule.'|ims', $this->html, $out)) {
            foreach ($out[1] as $css_url) {
                $http_info = send_http(htmlspecialchars_decode($this->add_host($css_url)));
                $css_content[] = $http_info['data'];
            }
        }
        //获取有效dom
        foreach ($css_content as $content) {
            $content = preg_replace('|/\*\*.*?\*\*/|ims', '', $content);
            if (@preg_match_all('|'.$css_content_rule.'|ims', $content, $out1)) {
                foreach($out1[2] as $key => $width) {
                    if ($width >= $this->min_width) {
                        $dom_lists =explode(',', $out1[1][$key]);
                        foreach ($dom_lists as $dom) {
                            if(pq($dom)->length <= 0) break 2;
                        }
                        $dom_arr = array_merge($dom_arr, $dom_lists);
                    }
                }
            }
        }

        return $dom_arr;
    }

    /**
     * 获取翻页的url
     * @return array
     */
    public function turn_page_url(){

        $valid_url = array();

        //如果抓取url字符串中存在 ? , 则使用此规则抓取
        if (strstr($this->crawl_url, '?')){
            list($crawl_url_real, $crawl_url_param) = explode('?' , $this->crawl_url);
            //匹配规则列表
            $preg_rules = array(
                '<a [^<>]*?href="([^javascript].*?)"',
                '<a [^<>]*?onclick="window.location=\'(.*?)\'"'
            );

            foreach ($preg_rules as $rule) {
                if(false != preg_match_all('|' . $rule . '|ims', $this->html, $url_lists)){

                    foreach ($url_lists[1] as $url) {
                        $url = $this->add_host($url);

                        if (! strstr($url, '?')) continue;

                        list($url_real, $url_param) = explode('?', $url);

                        if($crawl_url_real != $url_real) continue;
                        if(empty($crawl_url_param) || empty($url_param)) continue;;

                        parse_str($url_param, $param_arr);
                        parse_str($crawl_url_param, $crawl_param_arr);
                        $param_intersect = array_intersect(array_keys($param_arr), array_keys($crawl_param_arr));
                        if(count($param_intersect) != count($crawl_param_arr)) continue;
                        $valid_url[] = $url;
                    }
                }
            }
        }

        //如果翻页url为空，或者抓取url存在于翻页url中（避免抓取到标签列表页），使用此规则
        if (empty($valid_url) || in_array($this->crawl_url, $valid_url))
        {
            $valid_url = $pages = $url_lists = array();

            foreach ($this->score as $score_info)
            {
                $html = $score_info['obj']->html();
                //没有text则无效
                if(false == str_replace(' ', '', strip_tags($html))) continue;
                //没有子标签则无效
                $dom_children = pq($score_info['obj'])->children();
                if( 0 === $count = count($dom_children)) continue;
                //获取有效的url列表列表
                for ($k = 0; $k < $count; $k++) {
                    $html = pq($dom_children)->eq($k);
                    $a_preg = '|<a [^<>#]*?href="([^#]+?)".*?>(.*?)</a>|ims';
                    if (! preg_match_all($a_preg, trim($html), $out)) continue;
                    if(count($out[1]) > 1 || ! ctype_digit(strip_tags($out[2][0]))) continue;
                    $pages[] = $this->add_host($out[1][0]);
                }
                if(!empty($pages)) $url_lists[] = $pages;
            }
            //每个标签中url最多的则是有效的
            foreach((array)$url_lists as $pages){
                if(count($pages) > count($valid_url)) $valid_url = $pages;
            }
        }
        return $valid_url;
    }

    /**
     * 增加域名
     * @param $url
     * @return string
     */
    private function add_host($url)
    {
        if (strpos($url, 'http') !== 0){
            if(strpos($url, './') === 0){
                $url_info = parse_str($this->crawl_url);
                $path = $url_info['path'] ? $url_info['path'] : '/';
                if(strpos($path, '.')) $path = substr($path, 0, strrpos($this->crawl_url, '/')+1);
                if(strpos(strrev($path), '/') !==0 ) $path .= '/';

                $url = $this->host . $path . substr($url, 2);
            }
            elseif(strpos($url, '/') === 0) $url = $this->host . $url;
        }
        return $url;
    }

    /**
     * @param $rule_id
     * @return array|bool|string
     */
    private function results_byrule($rule_id)
    {

        if($rule_id > 0 && true == $parser_rule = $this->_ci->config->config['rules'][$rule_id][$this->html_type])
        {
            $item_area = $parser_rule['dom'];
            if (!empty($item_area) && pq($item_area)->length > 0) {
                return $this->get_children_results($item_area, false);
            }
        }
        return false;
    }

    private function make_repose($recrawl, $data, $http_code=0){

        //有时返回http_code 是404, 但能返回有效数据

        $repose_code = array(
            'suss' => 200,
            'is_empty' => 201,
            'html_invalid' => 202,
            'not_found' => 404,
            'unknown' => 999,
        );

        $this->repose['http_code'] = $http_code;
        if(empty($this->html)) $this->repose['status_code'] = $this->repose_code['is_empty'];
        else $this->repose['status_code'] = $this->repose_code['unknown'];
    }
}