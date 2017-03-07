<?php
/**
 * 短信接口客户端
 * Create on 2015-04-01 13:33:51
 * Create by yuebin
 */
namespace haibao\sms;
class SMSClient{
	
	private static $smsAddr = '测试12'; 
	private static $pass = '123456789'; 
    
	private static $SMS_CODE_PHONE_EMPTY = 1;
	private static $SMS_CODE_CONTENT_EMPTY = 2;
	private static $SMS_CODE_ALL_EMPTY = 3;
	private static $SMS_CODE_SEND_FAIL = 4;
	private static $SMS_CODE_IP_ILLEGAL = 5;
	private static $SMS_CODE_NO_PERMISSION = 6;
	private static $SMS_CODE_NO_BALANCE = 7;
	private static $SMS_CODE_COMMUNICATE_TIME_OUT = 8;
	private static $SMS_CODE_ILLEGAL_WORD = 9;
	
	
	private static $SMS_URL = 'http://123.56.233.226/send?';
	
	public function __construct(){
		
	}
	
	/**
	 * 发送短信
	 * @param String $phoneNum
	 * @param String $content
	 */
	public static function sendSMS($phoneNum,$content,$logFile = '/sites/web/logs/sms.log'){
		self::checkSMSInfo($phoneNum,$content);
		try{
			$curl = curl_init();
        	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($curl, CURLOPT_URL, self::createUrlString($phoneNum,$content));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true) ; 
			curl_setopt($curl, CURLOPT_BINARYTRANSFER, true) ;
			$req = curl_exec($curl);
			
		}catch(\Exception $e){
			self::recordLog($phoneNum,$content,self::$SMS_CODE_COMMUNICATE_TIME_OUT,$logFile);
			throw new \Exception('通信超时',self::$SMS_CODE_COMMUNICATE_TIME_OUT);
		}
		
		return self::parseSMSResult(trim($req),$phoneNum,$content,$logFile);
	}

	/**
	 * 校验数据
	 * @param String $phoneNum
	 * @param String $content
	 */
	private static function checkSMSInfo($phoneNum,$content){
		if(empty($phoneNum)){
			throw new \Exception('手机号不能为空',self::$SMS_CODE_PHONE_EMPTY);
		}
		if(empty($content)){
			throw new \Exception('短信内容不能为空',self::$SMS_CODE_CONTENT_EMPTY);
		}
	}
	
	/**
	 * 封装url
	 * @param String $phoneNum
	 * @param String $content
	 * @return : String $url 通信URL
	 */
	private static function createUrlString($phoneNum,$content) {
		$url = self::$SMS_URL.'smsaddr='.self::$smsAddr.'&pass='.self::$pass.'&phonenum='.$phoneNum.'&content='.$content;
		return $url;
	}
	
	/**
	 * 处理短信返回结果
	 */
	private static function parseSMSResult($code,$phoneNum,$content,$logFile = '/sites/web/logs/sms.log'){
		if(strlen($code) < 10){
			self::recordLog($phoneNum,$content,$code,$logFile);
			throw new \Exception(self::getSMSCode($code),$code);
		}
		self::recordLog($phoneNum,$content,$code,$logFile);
		return true;
	}
	
	/**
	 * 短信通知返回code
	 */
	private static function getSMSCode($code){
		/* 
		$codeArr =  array(
				'短信正常发送'=>self::$SMS_CODE_SUCCESS,
				'手机号码空'=>self::$SMS_CODE_PHONE_EMPTY,
				'内容空'=>self::$SMS_CODE_CONTENT_EMPTY,
				'手机号码和内容均为空'=>self::$SMS_CODE_ALL_EMPTY,
				'短信发送失败'=>self::$SMS_CODE_SEND_FAIL,
				'IP非法'=>self::$SMS_CODE_IP_ILLEGAL,
				'无权限'=>self::$SMS_CODE_NO_PERMISSION,
				'格式错误'=>self::$SMS_CODE_NO_FORMAT_ERROR
		);
		return array_search($code, $codeArr); */
		
		 $codeArr =  array(
		 		self::$SMS_CODE_PHONE_EMPTY=>'手机号码空',
		 		self::$SMS_CODE_CONTENT_EMPTY=>'内容空',
		 		self::$SMS_CODE_ALL_EMPTY=>'手机号码和内容均为空',
		 		self::$SMS_CODE_SEND_FAIL=>'短信发送失败',
		 		self::$SMS_CODE_IP_ILLEGAL=>'IP非法',
		 		self::$SMS_CODE_NO_PERMISSION=>'无权限',
		 		self::$SMS_CODE_NO_BALANCE=>'格式错误',
		 		self::$SMS_CODE_COMMUNICATE_TIME_OUT=>'通信超时',
		 		self::$SMS_CODE_ILLEGAL_WORD =>'短信内容有非法词',
		 );
		 if(!isset($codeArr[$code])){
		 	$codeArr[$code] = '发送成功';
		 	if(strlen($code) < 10){
		 		$codeArr[$code] = '编码未识别，返回编码为：'.$code;
		 	}		 	
		 }
		return $codeArr[$code];
	}
	
	/**
	 * 写日志
	 */
	private static function recordLog($phoneNum,$content,$code,$logFile = '/sites/web/logs/sms.log'){
		$msg = $phoneNum.'发送的信息='.$content.'返回的状态='.self::getSMSCode($code)."\n";
		file_put_contents(\haibao\frame\Environment::path().$logFile, $msg,FILE_APPEND);
		
		$data = new \haibao\cms\data\Article();
		$data->query('insert into SmsLog(`Mobile`,`Content`,`Return`,`Code`,`CreateTime`) values(%s,%s,%s,%s,%s)', array(
		    $phoneNum, $content, self::getSMSCode($code), $code, date('Y-m-d H:i:s')
		));
	}
}