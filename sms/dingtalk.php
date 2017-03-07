<?php
namespace haibao\sms;

class DingTalk{
	
	const OAPI_HOST = 'https://oapi.dingtalk.com';
	const CORPID = 'dingf2d161c2ed466e9c';
	const SECRET = 'CX-sJEHAqtTUk2o87gF2YNGmF7n2p0K_9PVaBvc-OePMrGYyM5lgvVZ_zfg_cg5_';
	const AGENTID = '45094939';
	
	public static function getAccessToken()
	{
		/**
		 * 缓存accessToken。accessToken有效期为两小时，需要在失效前请求新的accessToken（注意：以下代码没有在失效前刷新缓存的accessToken）。
		 */
		$accessToken = self::getCache('corp_access_token');
		if (!$accessToken)
		{
			$result = self::http_get(self::OAPI_HOST.'/gettoken?corpid='.self::CORPID.'&corpsecret='.self::SECRET);
			if ($result)
			{
				$json = json_decode($result,true);
				if (!$json || $json['errcode'] != 0) {
					return false;
				}
				$accessToken = $json['access_token'];
				self::setCache('corp_access_token', $accessToken);
			}
		}
		return $accessToken;
	}
	
	public static function getTicket($accessToken)
	{
		$jsticket = self::getCache('js_ticket');
		if (!$jsticket)
		{
			$response = self::http_get(self::OAPI_HOST.'/get_jsapi_ticket?type=jsapi&access_token='.$accessToken);
			$json = json_decode($response);
			self::check($json);
			$jsticket = $json->ticket;
			self::setJsTicket($jsticket);
		}
		return $jsticket;
	}
	
	public static function check($res)
	{
		if ($res->errcode != 0)
		{
			throw new \Exception("Failed: " . json_encode($res));
		}
	}
	
	public static function curPageURL()
	{
		$pageURL = 'http';
	
		if (array_key_exists('HTTPS',$_SERVER)&&$_SERVER["HTTPS"] == "on")
		{
			$pageURL .= "s";
		}
		$pageURL .= "://";
	
		if ($_SERVER["SERVER_PORT"] != "80")
		{
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		}
		else
		{
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}
		return $pageURL;
	}
	
	public static function getConfig()
	{
		$corpId = self::CORPID;
		$agentId = self::AGENTID;
		$nonceStr = 'hbcmsdd';
		$timeStamp = time();
		$url = self::curPageURL();
		$corpAccessToken = self::getAccessToken();
		$ticket = self::getTicket($corpAccessToken);
		$signature = self::sign($ticket, $nonceStr, $timeStamp, $url);
	
		$config = array(
				'url' => $url,
				'nonceStr' => $nonceStr,
				'agentId' => $agentId,
				'timeStamp' => $timeStamp,
				'corpId' => $corpId,
				'signature' => $signature);
		return json_encode($config, 64);
	}
	
	public static function sign($ticket, $nonceStr, $timeStamp, $url)
	{
		$plain = 'jsapi_ticket=' . $ticket .
		'&noncestr=' . $nonceStr .
		'&timestamp=' . $timeStamp .
		'&url=' . $url;
		return sha1($plain);
	}
	
	public static function send($option){
		$accessToken = self::getAccessToken();
		$response = self::http_post(self::OAPI_HOST.'/message/send?access_token='.$accessToken,json_encode($option));
		return $response;
	}
	
	public static function getUserInfo($code)
	{
		$accessToken = self::getAccessToken();
		$response = self::http_get(self::OAPI_HOST."/user/getuserinfo?access_token=".$accessToken."&code=".$code);
		return $response;
	}
	
	/**
	 * POST 请求
	 * @param string $url
	 * @param array $param
	 * @return string content
	 */
	public static function http_post($url,$param){
		$oCurl = curl_init();
		if(stripos($url,"https://")!==FALSE){
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
		}
		if (is_string($param)) {
			$strPOST = $param;
		} else {
			$aPOST = array();
			foreach($param as $key=>$val){
				$aPOST[] = $key."=".urlencode($val);
			}
			$strPOST =  join("&", $aPOST);
		}
		$headers = array(
				"Content-type: application/json;charset='utf-8'",
				"Accept: application/json",
				"Cache-Control: no-cache",
				"Pragma: no-cache",
		);
		curl_setopt($oCurl, CURLOPT_URL, $url);
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($oCurl, CURLOPT_POST,true);
		curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
		curl_setopt($oCurl, CURLOPT_HTTPHEADER, $headers);
		$sContent = curl_exec($oCurl);
		$aStatus = curl_getinfo($oCurl);
		curl_close($oCurl);
		if(intval($aStatus["http_code"])==200){
			return $sContent;
		}else{
			return false;
		}
	}
	
	/**
	 * GET 请求
	 * @param string $url
	 */
	public static function http_get($url){
		$oCurl = curl_init();
		if(stripos($url,"https://")!==FALSE){
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
		}
		curl_setopt($oCurl, CURLOPT_URL, $url);
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
		$sContent = curl_exec($oCurl);
		$aStatus = curl_getinfo($oCurl);
		curl_close($oCurl);
		if(intval($aStatus["http_code"])==200){
			return $sContent;
		}else{
			return false;
		}
	}
	
	public static function getCache($key)
	{
		return self::getFilecache()->get($key);
	}
	
	public static function setCache($key, $value)
	{
		self::getFilecache()->set($key, $value);
	}
	
	public static function setJsTicket($ticket)
	{
		self::getFilecache()->set("js_ticket", $ticket, 0, time() + 7000);
	}
	
	private static function getFilecache()
	{
		return new FileCache;
	}
	
}

class FileCache
{
	
	public function set($key, $value)
	{
		if($key&&$value){
			$folder = $this->getCacheFolder();
			$fileName = $folder ."filecache.php";
			$data = json_decode($this->get_file($fileName),true);
			$item = array();
			$item["$key"] = $value;

			$keyList = array('isv_corp_access_token','suite_access_token','js_ticket','corp_access_token');
			if(in_array($key,$keyList)){
				$item['expire_time'] = time() + 7000;
			}else{
				$item['expire_time'] = 0;
			}
			$item['create_time'] = time();
			$data["$key"] = $item;
			$this->set_file($fileName,json_encode($data));
		}
	}

	public function get($key)
	{
		if($key){
			$folder = $this->getCacheFolder();
			$fileName = $folder ."filecache.php";
			$data = json_decode($this->get_file($fileName),true);
			if($data&&array_key_exists($key,$data)){
				$item = $data["$key"];
				if(!$item){
					return false;
				}
				if($item['expire_time']>0&&$item['expire_time'] < time()){
					return false;
				}

				return $item["$key"];
			}else{
				return false;
			}

		}
	}

	public function get_file($filename) {
		if (!file_exists($filename)) {
			$fp = fopen($filename, "w");
			fwrite($fp, "<?php exit();?>" . '');
			fclose($fp);
			return false;
		}else{
			$content = trim(substr(file_get_contents($filename), 15));
		}
		return $content;
	}

	public function set_file($filename, $content) {
		$fp = fopen($filename, "w");
		fwrite($fp, "<?php exit();?>" . $content);
		fclose($fp);
	}
	
	private function getCacheFolder(){
		$folder = PHP_OS == 'Linux' ? \haibao\classlibrary\cms\Config::getConfig(\haibao\classlibrary\cms\Config::TAG_CACHE_FILES) : \haibao\frame\Environment::path() . '/cachefiles/';
		if (!file_exists($folder)){
			$old = umask(0);
			mkdir($folder, 0777);
			umask($old);
		}
		return $folder;
	}
}
