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


