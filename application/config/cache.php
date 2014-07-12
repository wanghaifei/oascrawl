<?php
/**
 * 缓存名称 与 时间配置
 * str_replace(array('[key]','[mdkey]'),array($value,md5($value)),$key])
 * expire表示缓存时间，永久缓存写0
 */

$config['cache_keys']['url_relation'] = array('key'=>'site_info_#[mdkey]', 'expire'=>60*60*24*30);
