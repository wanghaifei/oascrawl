<?php

function array_sort($arr,$keys,$type='desc'){
    $keysvalue = $new_array = array();
    foreach ($arr as $k=>$v){
        $keysvalue[$k] = $v[$keys];
    }
    if($type == 'desc'){
        arsort($keysvalue);
    }else{
        asort($keysvalue);
    }
    reset($keysvalue);
    foreach ($keysvalue as $k=>$v){
        $new_array[$k] = $arr[$k];
    }
    return $new_array;
}

function convert_encoding($from_encoding, $to_encoding, $data){
    if (is_array($data)) {
        foreach ($data as $key=>$val) {
            $data[$key]= convert_encoding($from_encoding, $to_encoding, $val);
        }
    }else{
        $data = iconv($from_encoding, $to_encoding, $data);
    }
    return $data;
}

/**
 * 发送http请求
 *
 * @access public
 * @param string $url xxxxx [Must]
 * @param array $post xxxxxx [Optional]
 * @param array $header xxxxxxxxx [Optional]
 * @return string $mixed
 */
function send_http($url,$post = array(),$header = array(),$connecttimeout = 15,$timeout = 25) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connecttimeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    if($post){
        if(is_array($post)){
            $post = http_build_query($post);
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }

    if ($header){
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    $data = curl_exec($ch);
    $http_info = curl_getinfo($ch);
/*    if($http_info['http_code'] != 200 && $http_info['http_code'] != 302 ){
        curl_close($ch);
        return $http_info;
    }*/
    curl_close($ch);

    $rs['data'] = $data;
    $rs['http'] = $http_info;

    return $rs;
}

/**
 * 数组转成XML
 * @param $arr
 * @param int $dom
 * @param int $item
 * @return string
 *
 */
function arrtoxml($arr,$dom=0,$item=0){
    if (!$dom){
        $dom = new DOMDocument("1.0");
    }
    if(!$item){
        $item = $dom->createElement("root");
        $dom->appendChild($item);
    }
    foreach ($arr as $key=>$val){
        $itemx = $dom->createElement(is_string($key)?$key:"item");
        $item->appendChild($itemx);
        if (!is_array($val)){
            $text = $dom->createTextNode($val);
            $itemx->appendChild($text);

        }else {
            arrtoxml($val,$dom,$itemx);
        }
    }
    return $dom->saveXML();
}

/**
 * 打印执行过程
 * @param $title
 * @param $content
 * @return bool
 */
function pr_exe_process($title, $content='')
{
    if(! ACCESS_BROWSER) return false;

    if($title == '抓取开始'){
        header("Content-type: text/html; charset=utf-8");
    }
    echo $title.":<br>";
    echo $content."<br>";
    echo "---------------------------------------------------------------------<br>";
    flush();
}