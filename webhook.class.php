<?php
/**
 * fanhaobai.com 网站 webhooks 类
 * @author fanhaobai & i@fanhaobai.com
 * @date 2017/1/20
 */

class Webhook {
	public $config;
	private $start;

	public $post;

	/**
	 * 构造方法
	 */
	public function __construct($config)
	{
		$config['log_path'] = isset($config['log_path']) ? $config['log_path'] : __DIR__ . '/';
		$config['log_name'] = isset($config['log_name']) ? $config['log_name'] : 'update_git.log';
		if (!isset($config['token_field'])) {
			$this->errorLog('token field not found');
		}
		if (!isset($config['access_token'])) {
			$this->errorLog('access token not found');
		}
		if (!isset($config['bash_path'])) {
			$this->errorLog('bash file path not found');
		}
		$this->config = $config;
		//初始化
		$this->start = $this->microtime();
		$this->accessLog('-');
		$this->accessLog('start');
		
		$this->post = json_decode($_POST['payload'] ? : '[]', true);
	}

	/**
	 * 入口
	 */
	public function run()
	{
		//校验token
		$this->checkToken();
	    	
		if ($this->checkBranch() && empty($this->exec())) {
			echo 'error';
		}

		echo 'ok';
	}

	/**
	 * 校验access_token
	 */
	public function checkToken()
	{
		$field = 'HTTP_' . str_replace('-', '_', strtoupper($this->config['token_field']));
		//获取token
		if (!isset($_SERVER[$field])) {
			$this->errorLog('access token not be in header', true);
		}
		$token = $_SERVER[$field];
		$payload = file_get_contents('php://input');
		list($algo, $hash) = explode('=', $token, 2);
		//计算签名
		$payloadHash = hash_hmac($algo, $payload, $this->config['access_token']);
		//token错误
		if (strcmp($hash, $payloadHash) != 0) {
			$this->errorLog('access token check failed', true);
		}
		$this->accessLog('access token is ok');
	}
    
	/**
	 * 校验分支
	 */
	public function checkBranch($branch = 'master')
	{
		$current = substr(strrchr($this->post['ref'], "/"), 1);
		$this->accessLog("branch is $current");
		
		return $current == $branch;
	}

	/**
	 * 执行shell
	 */
	public function exec()
	{
		$path = $this->config['bash_path'];
		if (!file_exists($path)) {
			$this->errorLog('bash file not found');
		}
		$result = shell_exec("sh $path 2>&1");
		$this->accessLog($result);
		return $result;
	}

	/**
	 * 运行日志,记录运行步骤
	 */
	private function accessLog($accessMessage)
	{
		//添加数据
		$accessMessage = date(DATE_RFC822)." -access- ".$accessMessage."\r\n";
		//调用写入函数
		$this->addToFile($this->config['log_path'] . $this->config['log_name'], $accessMessage);
	}

	/**
	 * 错误日志
	 */
	private function errorLog($errorMessage, $exit_msg_enabled = false)
	{
		//添加必要数据
		$errorMessage = date(DATE_RFC822)." -error- ".$errorMessage."\r\n";
		//调用写入函数
		$this->addToFile($this->config['log_path'] . $this->config['log_name'], $errorMessage);
		//退出脚本
		$str = null;
		if ($exit_msg_enabled) {
			$str = $errorMessage;
		}
		exit($str);
	}

	/**
	 * 写入文件函数
	 */
	private function addToFile($filePath, $logMessage, $replace = false)
	{
		//判断存储文件策略
		$this->fileConf($filePath);
		//更新文件内容
		if ($replace) {
			$result = file_put_contents($filePath, $logMessage);
		} else {
			$result = file_put_contents($filePath, $logMessage, FILE_APPEND);
		}
		return $result;
	}

	/**
	 * 析构函数
	 */
	public function __destruct()
	{
		$date = $this->microtime() - $this->start;
		$this->accessLog('used time:' . $date);
		$memory = $this->memory();
		$this->accessLog('used memory:' . $memory);
		$this->accessLog('end');
	}

	/**
	 * 时间计算
	 */
	private function microtime()
	{
		list($usec, $sec) = explode(" ", microtime(), 2);
		return ((float)$usec + (float)$sec);
	}

	/**
	 * 内存计算
	 */
	private function memory()
	{
		$memory  = (!function_exists('memory_get_usage')) ? '0' : round(memory_get_usage()/1024/1024, 5).'MB';
		return $memory;
	}

	/**
	 * 存储文件策略
	 */
	private function fileConf($filePath)
	{
		$path = pathinfo($filePath);
		//路径中目录是否存在
		if (!file_exists($path['dirname'])) {
			mkdir($path['dirname'], 0777, true);
		}
		//文件是否存在
		if (!file_exists($filePath)) {
			touch($filePath);
		}
	}
}
