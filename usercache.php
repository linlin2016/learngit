<?php
/**
 * @file $HeadURL: user.php $
 * @author $Author: LinLin (linlin@haibao.com) $
 * @date $Date: 2015-4-1 下午8:44:34 $
 * @brief 
 */
namespace haibao\user\cache;

class User extends Base{
    
    const PIPELINE = 2;
	
	private $cacheKeyPre = 'TOKEN';
	
	private $sessionPre = 'SESSION';
	
	private $smsCaptchaPre = 'SMS_CAPTCHA';
	
	private $smsSendCountPre = 'SMS_SEND_COUNT';
	
	private $blackList = 'BLACKLIST_SENDSMS';
	
	private $blackUpdateSign = 'BLACKLIST_UPDATE_SIGN';
	
	private $avatarMd5 = 'USER_PHOTO_MD5';
	
	private $weixinPre = 'WEIXIN_UNIONID_UID';
	
	private $slideVerifyPre = 'SLIDE_VERIFY_MOBILE';
	
	private $userIntegral  = 'USER_INTEGRAL';
	
	private $sessionTvPre = 'TV_SESSION';
	
	public function getUserByToken($token){
		if(!$token){
			return;
		}
		$cacheKey = $this->getCacheKey($token);
		return $this->getRedis()->get($cacheKey);
	}
	
	/**
	 * 激活码在redis中保留三天
	 */
	public function setToken($token, $userId, $expire = 259200){
		if (!$userId || !$token){
			return;
		}
		$this->getRedis()->setex($this->getCacheKey($token), $userId, $expire);
	}
	
	public function deleteToken($token){
		$this->getRedis()->delete($this->getCacheKey($token));
	}
	
	/**
	 * session存储在redis中
	 */
	public function setSession($key, $value, $expire = 259200,$isTvHaibao = false){
		if(!$key || !$value) return;
		
		$cacheKey = $this->getSessionCacheKey($key,$isTvHaibao);
		$this->getRedis()->setex($cacheKey, $value, $expire,$isTvHaibao);
	}
	
	public function getSession($key,$isTvHaibao = false){
		if(!$key) return;
		
		$cacheKey = $this->getSessionCacheKey($key,$isTvHaibao);
		return $this->getRedis()->get($cacheKey);
	}
	
	public function delSession($key,$isTvHaibao = false){
		if(!$key) return;
		
		$cacheKey = $this->getSessionCacheKey($key,$isTvHaibao);
		return $this->getRedis()->delete($cacheKey);
	}
	
	public function getUserPhotoMd5($key){
		$cacheKey = $this->getKey($this->avatarMd5, $key);
		return $this->getRedis()->get($cacheKey);
	}
	
	public function setUserPhotoMd5($key,$value,$expires = 7200){
		$cacheKey = $this->getKey($this->avatarMd5, $key);
		$this->getRedis()->setex($cacheKey, $value, $expires);
	}
	
	/**
	 * 手机验证码存储在redis中
	 */
	public function setSmsCaptcha($mobile, $value, $expire = 600){
		if(!$mobile) return;
		
		$cacheKey = $this->getSmsCaptchaKey($mobile);
		$this->getRedis()->setex($cacheKey, $value, $expire);
	}
	
	public function getSmsCaptcha($mobile){
		if(!$mobile) return;
		
		$cacheKey = $this->getSmsCaptchaKey($mobile);
		return $this->getRedis()->get($cacheKey);
	}
		
	/**
	 * 手机短信发送次数限制
	 */
	public function setSmsSendCount($mobile, $ip){
		if(!$mobile) return;
		
		$expire = $this->getTodayRemainSecond();
		
		$count = (int)$this->getSmsSendCount($mobile);
		$count++;
		
		$cacheKey = $this->getSmsSendCountKey($mobile);
		$this->getRedis()->setex($cacheKey, $count.'_'.time().'_'.$ip, $expire);
	}
	
	/**
	 * 手机短信发送次数
	 */
	public function getSmsSendCount($mobile){
		if(!$mobile) return;
		
		$cacheKey = $this->getSmsSendCountKey($mobile);
		return $this->getRedis()->get($cacheKey);
	}
	
	public function deleteSmsSendCount($mobile){
		if(!$mobile) return;
		
		$cacheKey = $this->getSmsSendCountKey($mobile);
		return $this->getRedis()->delete($cacheKey);
	}
	
	public function updateBlackList($blacklist){
        $count = count($this->getRedis()->zRange($this->blackList));
        $this->getRedis()->zRemRangeByScore($this->blackList, 0, intval($count)-1);
        
	    foreach ($blacklist as $key=>$black){
	        $this->getRedis()->zAdd($this->blackList, $key, $black);
	    }
	    $this->getRedis()->setex($this->blackUpdateSign, time(), 60*60*24);
	}
	
	public function inBlackList($userId){
	    return $this->getRedis()->zScore($this->blackList, $userId) != '' ? true : false;
	}
	
	public function getBlackSig(){
	    return $this->getRedis()->get($this->blackUpdateSign);
	}
	
	/**
	 * 保存微信绑定用户
	 */
	public function setWeixinBindUid($unionId, $userId){
		$cacheKey = $this->getKey($this->weixinPre, $unionId);
		$this->getRedis()->set($cacheKey, $userId);
	}
	
	public function getWeixinBindUid($unionId){
		$cacheKey = $this->getKey($this->weixinPre, $unionId);
		return $this->getRedis()->get($cacheKey);
	}
	
	/**
	 * 设置用户滑动验证的状态
	 * @param $mobile 手机号
	 */
	public function setUserSlideVerifyStatus($mobile){
		$cacheKey = $this->getKey($this->slideVerifyPre, $mobile);
		$this->getRedis()->setex($cacheKey, 1, 60*10);
	}
	
	/**
	 * 获取用户滑动验证的状态
	 * @param $mobile 手机号
	 */
	public function getUserSlideVerifyStatus($mobile){
		$cacheKey = $this->getKey($this->slideVerifyPre, $mobile);
		$this->getRedis()->get($cacheKey);
	}
	
	/**
	 * 获取今天剩余秒数
	 */
	private function getTodayRemainSecond(){
		$time = time();
		$todayEndTime = strtotime(date('Y-m-d',strtotime('+1 day')).' 00:00:00');
		return (int)($todayEndTime - $time);
	}
	
	private function getCacheKey($id){
		return $this->getKey($this->cacheKeyPre, $id);
	}
	
	private function getSessionCacheKey($id,$isTvHaibao = false){
	    if($isTvHaibao){
	        return $this->getKey($this->sessionTvPre, $id);
	    }else{
	        return $this->getKey($this->sessionPre, $id);
	    }

	}
	
	private function getSmsCaptchaKey($id){
		return $this->getKey($this->smsCaptchaPre, $id);
	}
	
	private function getSmsSendCountKey($id){
		return $this->getKey($this->smsSendCountPre, $id);
	}
	
	/**
	 * 用户登录/签到送积分
	 */
	public function getUserIntegral($userId){
		$cacheKey = $this->getKey($this->userIntegral, $userId);
		return $this->getRedis()->get($cacheKey);
	}
	
	public function setUserIntegral($userId,$data){
		$cacheKey = $this->getKey($this->userIntegral, $userId);
		$this->getRedis()->set($cacheKey,$data);
	}
	
}