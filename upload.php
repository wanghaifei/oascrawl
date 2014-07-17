<?php
/**
 * Created by PhpStorm.
 * User: wanghaifei
 * Date: 14-7-16
 * Time: 下午7:28
 */
define('FILEPATH' , '/home/file/');

class Files_Tool {
    public static $path = array();
    public static $wrong = array();
    protected static$allowExt=array('.jpg','.jpeg','.png','.gif','.bmp','.svg','.chm','.pdf','.zip','.rar','.tar','.gz','.bzip2','.ppt','.doc');
    protected static $error=array(
        0=>'文件上传失败,没有错误发生,文件上传成功',
        1=>'文件上传失败,上传的文件超过了 php.ini中upload_max_filesize 选项限制的值',
        2=>'文件上传失败,上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
        3=>'文件上传失败,文件只有部分被上传',
        4=>'文件上传失败,没有文件被上传',
        5=>'文件上传失败,未允许的后缀',
        6=>'文件上传失败,找不到临时文件夹.PHP 4.3.10 和 PHP 5.0.3 引进',
        7=>'文件上传失败,文件写入失败.PHP 5.1.0 引进',
        8=>'文件上传失败,未接收到表单域的NAME',
        9=>'文件上传失败,,错误未知'
    );

    protected $url;

    public function upload($url){
        $this->url = $url;
        //后缀
        $ext=$this->get_Ext($url);
        //查看当前文件的后缀,是否允许,如果不允许,跳过当前文件
        if(!in_array($ext,self::$allowExt)){
            self::errors(self::$error[5]);
        }
        //路径
        $dir=$this->url_Dir();
        //文件名
        $name=$this->set_Name();
        //文件位置
        $filename=$dir.basename($this->url);

        //图片不存在,读取图片
        if(! file_exists($filename)){
            $img = send_http($this->url);
			file_put_contents($filename, $img);
        }
        $size = getimagesize($filename);
        $pic_width = $size[0];
        return $pic_width;
    }
    //获取后缀的方法
    protected function get_Ext(){
        return strtolower(strrchr($this->url,'.'));
    }
    //以日期生成路径
    protected function url_Dir(){
        $dir= FILEPATH . implode('/', str_split(md5($this->url), 8)) . '/';
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        return $dir;
    }
    //错误接口
    public static function errors($errors){

    }
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
function send_http($url,$post = array(),$header = array(),$connecttimeout = 5,$timeout = 5) {

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
    $rs = curl_exec($ch);
    $http_info = curl_getinfo($ch);

    if($http_info['http_code'] != 200 && $http_info['http_code'] != 302 ){
        curl_close($ch);
        return $http_info;
    }
    curl_close($ch);
    return $rs;
}

$url = trim(urldecode($_GET['url']));
if ($url) {
    $files_tools = new Files_Tool();
    $width = $files_tools->upload($url);
    echo $width;
}
