<?php
/**
 * 数据 文件缓存类
 * 默认的超时时间为3600秒
 * 用于缓存局部使用的数据组 对象 字符串等数据内容
 * 缓存目录请定义 DOC_ROOT or APP_PATH
 * @author unspace <jlnuwn@gmail.com>
 */
class FileCache {
    private $_cache_root = '/tmp';
    private $_tag = "default";
    private static $_pool = array();

    private function __construct($tag) {
        if (!empty($tag)) {
            $this->_tag = $tag;
        }
        if(defined('DOC_ROOT')){
            $this->_cache_root = DOC_ROOT.'/cache/filecache';
        } elseif(defined('APP_PATH')) {
            $this->_cache_root = APP_PATH.'/cache/filecache';
        }
    }

    /**
     * @return FileCache
     */
    public static function getIns($tag = 'default') {
        if (isset(self::$_pool[$tag])) {
            return self::$_pool[$tag];
        }
        $tag = strval($tag);
        if (!preg_match("/^\w+$/is", $tag)) {
            return false;
        }
        self::$_pool[$tag] = new self($tag);
        return self::$_pool[$tag];
    }

    /**
     * 使用file读取数据，按写入时原样返回，不存在的返回null
     * 一旦读取超时的key，将删掉原有的缓存文件
     * @param type $key
     * @param type $timeout 有效超时时间
     * @return mixed 
     */
    public function get($key, $timeout = 3600) {
        $filename = $this->getFileName($key);
        if (!$filename) {
            return null;
        }

        if ($this->isTimeout($key, $timeout)) {
            $this->delete($key);
            return null;
        }

        if (file_exists($filename)) {
            @include($filename);
            if (isset($data)) {
                return $data;
            }
            return null;
        } else {
            return null;
        }
    }

    public function set($key, $value) {
        $data_str = var_export($value, true);
        $data_str = null == $data_str ? '' : $data_str;
        $cache_str = "<?php\n//".str_replace("\n",'\n',$key)."\n\$data=" . $data_str . ";\n";
        $filename = $this->getFileName($key);
        if (!$filename) {
            return false;
        }
        if(!$this->mkdirByFile($filename)){
            return false;
        }

        $_tmp_file = @tempnam($dirname, 'tmp');
        if (!($fp = @fopen($_tmp_file, 'wb'))) {
            $pathinfo = pathinfo($filename);
            $path = $pathinfo ['dirname'];
            $_tmp_file = $path . '/' . uniqid('tmp');
            if (!($fp = @fopen($_tmp_file, 'wb'))) {
                return false;
            }
        }
        @fwrite($fp, $cache_str);
        @fclose($fp);
        if (!@rename($_tmp_file, $filename)) {
            @unlink($filename);
            @rename($_tmp_file, $filename);
        }
        @chmod($filename, 0777);
        return true;
    }

    public function delete($key) {
        $filename = $this->getFileName($key);
        if (file_exists($filename)) {
            $res = @unlink($filename);
            return $res ? true : false;
        } else {
            return true;
        }
    }

    public function getFileName($key) {
        $key = strval($key);
        $md5 = md5($key);
        $path = $this->_cache_root . '/' . $this->_tag . '/' . substr($md5, 0, 2);
        $filename = $path . "/" . $md5 . '.php';
        return $filename;
    }
    
    public function mkdirByFile($filename){
        $path = dirname($filename);
        if (!is_dir($path)) {
            //$old = umask(0);
            mkdir($path, 0777, true);
            //umask($old);
        }
        if (!is_writable($path)) {
            return false;
        }
        return true;
    }
    

    function isTimeout($key, $timeout = 3600) {
        $filename = $this->getFileName($key);
        $timeout  = intval($timeout);

        if (!file_exists($filename)) {
            return true;
        }
        if ($timeout <= 0) {
            return false;
        }
        if (time() - filemtime($filename) > $timeout) {
            return true;
        } else {
            return false;
        }
    }
}

/*
$res = FileCache::getIns('index')->get('test');
var_dump($res);
$res = FileCache::getIns('index')->set('test','aaa');
var_dump($res);
*/
