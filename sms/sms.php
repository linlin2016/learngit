<?php
/**
 * @file sms.php
 * @author LJ (liangjian@haibao.com)
 * @date 2016年11月4日 上午10:10:09
 * @brief 新版短信发送，调用python接口
 */
namespace haibao\sms;

class SMS{
    
    /**
     * 【网站注册】验证码为%s，你正在使用手机号注册海报帐号，感谢关注海报时尚网。
     */
    const TEMPLATE_2001 = 2001;
    
    /**
     * 【找回密码】验证码为%s，你正在使用手机号找回密码，感谢关注海报时尚网。
     */
    const TEMPLATE_2002 = 2002;
    
    /**
     * 【快速登录】验证码为%s，你正在使用手机号快速登录，感谢关注海报时尚网。
     */
    const TEMPLATE_2003 = 2003;
    
    /**
     * 【绑定账号】验证码为%s，你正在使用手机号绑定海报帐号，感谢关注海报时尚网。
     */
    const TEMPLATE_2004 = 2004;
    
    /**
     * 【客户端注册】欢迎注册《街拍》APP，你的验证码是 %s，请在10分钟内使用。
     */
    const TEMPLATE_2005 = 2005;
    
    /**
     * 【客户端找回密码】你正在使用手机号找回《街拍》APP的帐号密码，你的验证码是%s，请在10分钟内使用。
     */
    const TEMPLATE_2006 = 2006;
    
    /**
     * 发送短信接口地址
     */
    const API_URL = 'http://c26-hb-python01.haibao.com.cn:5006/send/';
    
    /**
     * 短信接口密钥
     */
    const SECRET_KEY = 'YzI2NjBlOGZmYzk5MmZhYzU4YzA1MGVk';
    
    /**
     * 发送短信
     */
    public static function sendSms($mobile, $templateId, array $data){
        self::checkSendInfo($mobile, $data);
        
        $payload = array(
            'mobile' => trim($mobile),
            'templateId' => intval($templateId),
            'data' => json_encode($data),
            'expires' => time()+600
        );
        
        $token = self::getToken($payload);
        $apiUrl = self::API_URL.$token;
        
        // self::saveLog(json_encode($payload));
        $response = self::httpRequest($apiUrl, $payload);
        return $response;
    }
    
    private static function checkSendInfo($mobile, array $data){
        if(empty($mobile)){
            throw new \Exception('手机号不能为空');
        }
        if(empty($data)){
            throw new \Exception('发送短信内容有误');
        }
    }
    
    /**
     * 获取发送短信的Token
     */
    private static function getToken(array $payload){
        $jsonData = json_encode($payload);
        $sig = hash_hmac('sha256', $jsonData, self::SECRET_KEY);
        return rawurlencode(base64_encode($jsonData.$sig));
    }
    
    /**
     * 执行一个HTTP请求
     */
    public static function httpRequest($url, $params, $cookie = '', $method = 'post', $protocol='http'){
        $query_string = self::makeQueryString($params);
        if($cookie){
            $cookie_string = self::makeCookieString($cookie);
        }
        
        $ch = curl_init();
        if('GET' == strtoupper($method)){
            curl_setopt($ch, CURLOPT_URL, "$url?$query_string");
        }else{
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
        }
        
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        
        if (!empty($cookie_string)){
            curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
        }
        
        if ('https' == $protocol){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        $ret = curl_exec($ch);
        $err = curl_error($ch);
        
        if (false === $ret || !empty($err)){
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            return array('result' => false, 'errno' => $errno, 'msg' => $err, 'info' => $info);
        }
        
        curl_close($ch);
        
        return array('result' => true, 'msg' => $ret);
    }
    
    public static function makeQueryString($params){
        if (is_string($params)){
            return $params;
        }
        
        $query_string = array();
        foreach ($params as $key=>$value){
            array_push($query_string, rawurlencode($key) . '=' . rawurlencode($value));
        }
        $query_string = join('&', $query_string);
        return $query_string;
    }
    
    public static function makeCookieString($params){
        if (is_string($params)){
            return $params;
        }
        
        $cookie_string = array();
        foreach ($params as $key => $value){
            array_push($cookie_string, $key . '=' . $value);
        }
        $cookie_string = join('; ', $cookie_string);
        return $cookie_string;
    }
    
    public static function saveLog($content, $filename='sms.log'){
        $fp = fopen('/tmp/'.$filename, "a");
        flock($fp, LOCK_EX) ;
        fwrite($fp, $content."\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    
}