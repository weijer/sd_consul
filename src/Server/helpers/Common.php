<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 上午11:38
 */
/**
 * 获取实例
 * @return \Server\SwooleDistributedServer
 */
function &get_instance()
{
    return \Server\SwooleDistributedServer::get_instance();
}

/**
 * 获取服务器运行到现在的毫秒数
 * @return int
 */
function getTickTime()
{
    return getMillisecond() - \Server\Start::getStartMillisecond();
}

/**
 * 获取当前的时间(毫秒)
 * @return float
 */
function getMillisecond()
{
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
}

function shell_read()
{
    $fp = fopen('php://stdin', 'r');
    $input = fgets($fp, 255);
    fclose($fp);
    $input = chop($input);
    return $input;
}

/**
 * http发送文件
 * @param $path
 * @param $response
 * @return mixed
 */
function httpEndFile($path, $request, $response)
{
    $path = urldecode($path);
    if (!file_exists($path)) {
        return false;
    }
    $lastModified = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
    //缓存
    if (isset($request->header['if-modified-since']) && $request->header['if-modified-since'] == $lastModified) {
        $response->status(304);
        $response->end('');
        return true;
    }
    $extension = get_extension($path);
    $normalHeaders = get_instance()->config->get("fileHeader.normal", ['Content-Type: application/octet-stream']);
    $headers = get_instance()->config->get("fileHeader.$extension", $normalHeaders);
    foreach ($headers as $value) {
        list($hk, $hv) = explode(': ', $value);
        $response->header($hk, $hv);
    }
    $response->header('Last-Modified', $lastModified);
    $response->sendfile($path);
    return true;
}

/**
 * 获取后缀名
 * @param $file
 * @return mixed
 */
function get_extension($file)
{
    $info = pathinfo($file);
    return strtolower($info['extension'] ?? '');
}

/**
 * php在指定目录中查找指定扩展名的文件
 * @param $path
 * @param $ext
 * @return array
 */
function get_files_by_ext($path, $ext)
{
    $files = array();
    if (is_dir($path)) {
        $handle = opendir($path);
        while ($file = readdir($handle)) {
            if ($file[0] == '.') {
                continue;
            }
            if (is_file($path . $file) and preg_match('/\.' . $ext . '$/', $file)) {
                $files[] = $file;
            }
        }
        closedir($handle);
    }
    return $files;
}

function getLuaSha1($name)
{
    return \Server\Asyn\Redis\RedisLuaManager::getLuaSha1($name);
}

/**
 * 检查扩展
 * @return bool
 */
function checkExtension()
{
    $check = true;
    if (!extension_loaded('swoole')) {
        secho("STA", "[扩展依赖]缺少swoole扩展");
        $check = false;
    }
    if (extension_loaded('xhprof')) {
        secho("STA", "[扩展错误]不允许加载xhprof扩展，请去除");
        $check = false;
    }
    if (extension_loaded('xdebug')) {
        secho("STA", "[扩展错误]不允许加载xdebug扩展，请去除");
        $check = false;
    }
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        secho("STA", "[版本错误]PHP版本必须大于7.0.0\n");
        $check = false;
    }
    if (version_compare(SWOOLE_VERSION, '4.0.3', '<')) {
        secho("STA", "[版本错误]Swoole版本必须大于4.0.3\n");
        $check = false;
    }

    if (!class_exists('swoole_redis')) {
        secho("STA", "[编译错误]swoole编译缺少--enable-async-redis,具体参见文档http://docs.sder.xin/%E7%8E%AF%E5%A2%83%E8%A6%81%E6%B1%82.html");
        $check = false;
    }
    if (!extension_loaded('redis')) {
        secho("STA", "[扩展依赖]缺少redis扩展");
        $check = false;
    }
    if (!extension_loaded('pdo')) {
        secho("STA", "[扩展依赖]缺少pdo扩展");
        $check = false;
    }

    if (get_instance()->config->has('consul_enable')) {
        secho("STA", "consul_enable配置已被弃用，请换成['consul']['enable']");
        $check = false;
    }
    if (get_instance()->config->has('use_dispatch')) {
        secho("STA", "use_dispatch配置已被弃用，请换成['dispatch']['enable']");
        $check = false;
    }
    if (get_instance()->config->has('dispatch_heart_time')) {
        secho("STA", "dispatch_heart_time配置已被弃用，请换成['dispatch']['heart_time']");
        $check = false;
    }
    if (get_instance()->config->get('config_version', '') != \Server\SwooleServer::config_version) {
        secho("STA", "配置文件有不兼容的可能，请将vendor/tmtbe/swooledistributed/src/config目录替换src/config目录，然后重新配置");
        $check = false;
    }
    return $check;
}

/**
 * 是否是mac系统
 * @return bool
 */
function isDarwin()
{
    if (PHP_OS == "Darwin") {
        return true;
    } else {
        return false;
    }
}

function displayExceptionHandler(\Throwable $exception)
{
    get_instance()->log->error($exception->getMessage(), ["trace" => $exception->getTrace()]);
    secho("EX", "------------------发生异常：" . $exception->getMessage() . "-----------------------");
    $string = $exception->getTraceAsString();
    $arr = explode("#", $string);
    unset($arr[0]);
    foreach ($arr as $value) {
        secho("EX", "#" . $value);
    }
}

/**
 * 代替sleep
 * @param $ms
 * @return mixed
 */
function sleepCoroutine($ms)
{
    \co::sleep($ms / 1000);
}

/**
 * @param string $dev
 * @return string
 */
function getServerIp($dev = 'eth0')
{
    return exec("ip -4 addr show $dev | grep inet | awk '{print $2}' | cut -d / -f 1");
}

/**
 * @return string
 */
function getBindIp()
{
    return get_instance()->getBindIp();
}

/**
 * @return array|false|mixed|string
 */
function getNodeName()
{
    global $node_name;
    if (!empty($node_name)) {
        return $node_name;
    }
    $env_SD_NODE_NAME = getenv("SD_NODE_NAME");
    if (!empty($env_SD_NODE_NAME)) {
        $node_name = $env_SD_NODE_NAME;
    } else {
        if (!isset(get_instance()->config['consul']['node_name'])
            || empty(get_instance()->config['consul']['node_name'])) {
            $node_name = exec('hostname');
        } else {
            $node_name = get_instance()->config['consul']['node_name'];
        }
    }
    return $node_name;
}

/**
 * @return mixed|string
 */
function getServerName()
{
    return get_instance()->config['name'] ?? 'SWD';
}

/**
 * @return string
 */
function getConfigDir()
{
    $env_SD_CONFIG_DIR = getenv("SD_CONFIG_DIR");
    if (!empty($env_SD_CONFIG_DIR)) {
        $dir = CONFIG_DIR . '/' . $env_SD_CONFIG_DIR;
        if (!is_dir($dir)) {
            secho("STA", "$dir 目录不存在\n");
            exit();
        }
        return $dir;
    } else {
        return CONFIG_DIR;
    }
}

/**
 * @param string $prefix
 * @return string
 */
function create_uuid($prefix = "")
{    //可以指定前缀
    $str = md5(uniqid(mt_rand(), true));
    $uuid = substr($str, 0, 8) . '-';
    $uuid .= substr($str, 8, 4) . '-';
    $uuid .= substr($str, 12, 4) . '-';
    $uuid .= substr($str, 16, 4) . '-';
    $uuid .= substr($str, 20, 12);
    return $prefix . $uuid;
}

function print_context($context)
{
    secho("EX", "运行链路:");
    foreach ($context['RunStack'] as $key => $value) {
        secho("EX", "$key# $value");
    }
}

function secho($tile, $message)
{
    ob_start();
    if (is_string($message)) {
        $message = ltrim($message);
        $message = str_replace(PHP_EOL, '', $message);
    }
    print_r($message);
    $content = ob_get_contents();
    ob_end_clean();
    $could = false;
    if (empty(\Server\Start::getDebugFilter())) {
        $could = true;
    } else {
        foreach (\Server\Start::getDebugFilter() as $filter) {
            if (strpos($tile, $filter) !== false || strpos($content, $filter) !== false) {
                $could = true;
                break;
            }
        }
    }

    $content = explode("\n", $content);
    $send = "";
    foreach ($content as $value) {
        if (!empty($value)) {
            $echo = "[$tile] $value";
            $send = $send . $echo . "\n";
            if ($could) {
                echo " > $echo\n";
            }
        }
    }
    try {
        if (get_instance() != null) {
            get_instance()->pub('$SYS/' . getNodeName() . "/echo", $send);
        }
    } catch (Exception $e) {

    }
}

function setTimezone()
{
    date_default_timezone_set('Asia/Shanghai');
}

function format_date($time)
{
    $day = (int)($time / 60 / 60 / 24);
    $hour = (int)($time / 60 / 60) - 24 * $day;
    $mi = (int)($time / 60) - 60 * $hour - 60 * 24 * $day;
    $se = $time - 60 * $mi - 60 * 60 * $hour - 60 * 60 * 24 * $day;
    return "$day 天 $hour 小时 $mi 分 $se 秒";
}

function sd_call_user_func($function, ...$parameter)
{
    if (is_callable($function)) {
        return $function(...$parameter);
    }
}

function sd_call_user_func_array($function, $parameter)
{
    if (is_callable($function)) {
        return $function(...$parameter);
    }
}

/**
 * @param $arr
 * @throws \Server\Asyn\MQTT\Exception
 */
function sd_debug($arr)
{
    Server\Components\SDDebug\SDDebug::debug($arr);
}


/**
 * echo替代方案
 * @param $array
 * @return array|bool
 */
function fun_adm_each(&$array){
    $res = array();
    $key = key($array);
    if($key !== null){
        next($array);
        $res[1] = $res['value'] = $array[$key];
        $res[0] = $res['key'] = $key;
    }else{
        $res = false;
    }
    return $res;
}


function read_dir_queue($dir)
{
    $files = array();
    $queue = array($dir);
    while ($data = fun_adm_each($queue)) {
        $path = $data['value'];
        if (is_dir($path) && $handle = opendir($path)) {
            while ($file = readdir($handle)) {
                if ($file == '.' || $file == '..') continue;
                $files[] = $real_path = realpath($path . '/' . $file);
                if (is_dir($real_path)) $queue[] = $real_path;
            }
        }
        closedir($handle);
    }
    $result = [];
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == "php") {
            $result[] = $file;
        }
    }
    return $result;
}

if (!function_exists('encode_aes')) {
    /**
     * @param $data mixed 需要加密的数据(除资源类型外的所有类型)
     * @param $publicKey  string 公钥
     * @param bool $serialize 是否序列化(除Str外的都需序列化,如果是String可不序列化,节省时间)
     *      https://wiki.swoole.com/wiki/page/p-serialize.html
     * @param string $method
     * @return array
     */
    function encode_aes($data, $publicKey, $serialize = false, $method = 'aes-256-cbc')
    {
        if ($serialize) $data = serialize($data);
        $key = password_hash($publicKey, PASSWORD_BCRYPT, ['cost' => 12]);
        $iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);
        secho('data', $data);
        $encrypted = base64_encode(openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv));
        return [
            'hash_key' => $key,
            'encrypted' => $encrypted,
        ];
    }
}

if (!function_exists('decode_aes')) {
    /**
     * @param $data
     * @param $key
     * @param bool $serialize
     * @param string $method
     * @return mixed|string
     */
    function decode_aes($data, $key, $serialize = false, $method = 'aes-256-cbc')
    {
        $iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);
        $decrypted = openssl_decrypt(base64_decode($data), $method, $key, OPENSSL_RAW_DATA, $iv);
        if ($serialize) $decrypted = unserialize($decrypted);
        return $decrypted;
    }
}

if (!function_exists('generate_response_data')) {
    /**
     * 生成返回数据
     * @param int $code
     * @param string $msg
     * @param string $type
     * @param array $data
     * @return array
     */
    function generate_response_data($code = -1, $msg = '', $data = [], $type = '')
    {
        $responseData = [
            'code' => $code,
            'msg' => $msg
        ];

        if (!empty($type)) {
            // 增加类型
            $responseData['type'] = $type;
        }

        //if (!empty($data)) {
            // 增加返回数据
            $responseData['data'] = $data;
       // }

        return $responseData;
    }
}

if (!function_exists('throw_api_exception')) {
    /**
     * @param int $code
     * @param string $msg
     * @param string $type
     * @throws Exception
     */
    function throw_api_exception($code = -1, $msg = '', $type = '')
    {
        $errorData = generate_response_data($code, $msg, [], $type);

        throw new \Exception(json_encode($errorData));
    }
}

if (!function_exists('string_random')) {
    /**
     * 随机字符串加数字
     * @param $length
     * @return string
     * @throws Exception
     */
    function string_random($length)
    {
        $int = $length / 2;
        $bytes = random_bytes($int);
        $string = bin2hex($bytes);
        return $string;
    }
}

if (!function_exists('create_directory')) {
    /**
     * 判断目录是否存在,不存在则创建
     * @param $path
     * @param int $mode
     * @return bool|string
     */
    function create_directory($path, $mode = 0777)
    {
        if (is_dir($path)) {
            //判断目录存在否，存在不创建
            return true;
        } else {
            //不存在则创建目录
            $re = mkdir($path, $mode, true);
            if ($re) {
                return true;
            } else {
                return false;
            }
        }
    }
}

if (!function_exists('validate_param')) {
    /**
     * 验证参数
     * @param $obj
     * @param $model
     * @param $scene
     * @param $data
     * @throws Exception
     */
    function validate_param($model, $scene, $data)
    {
        $className = "\\app\Validate\\{$model}";
        $validate = new $className();
        $check = $validate->scene($scene)->check($data);

        if (!$check) {
            throw_api_exception(-40001, $validate->getError());
        };
    }
}

if (!function_exists('is_many_dimension_array')) {
    /**
     * 是否是多维数组
     * @param $array
     * @return bool
     */
    function is_many_dimension_array($array)
    {
        if (count($array) == count($array, 1)) {
            return false;
        } else {
            return true;
        }
    }
}

if (!function_exists('object_to_array')) {
    /**
     * 对象转数组
     * @param $object
     * @return mixed
     */
    function object_to_array(&$object)
    {
        $object = json_decode(json_encode($object), true);
        return $object;
    }
}
