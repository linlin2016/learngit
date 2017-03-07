<?php


namespace haibao\user\business;

//use haibao\app\business\BusinessException;

use \haibao\user\business\BusinessException;

use haibao\frame\http\HttpEnvironment;
use haibao\frame\http\Cookie;
use haibao\classlibrary\Config;
use haibao\user\data\UserProfiles;

class User extends \haibao\user\business\Base{

	/**
	 * @var $_instance
	 */
	private static $_instance;

	/**
	 * instance()
	 */
	private static function instance(){
		if(!self::$_instance){
			self::$_instance = new \haibao\user\data\User();
		}
		return self::$_instance;
	}


	/**
	 * 用户登录
	 * @param \haibao\user\model\data\UserLogin $userLoginModel		用户登录实体
	 * @param bool $remberMe
	 * @throws BusinessException
	 */
	public static function login($userLoginModel,$remberMe = true){
		if(!$userLoginModel->LoginName || !$userLoginModel->Password){
			throw new BusinessException('用户名密码为空');
		}
	
		$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_NAME;
		if(self::isEmail($userLoginModel->LoginName)){
			$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_EMAIL;
		}elseif(self::isMobile($userLoginModel->LoginName)){
			$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE;
		}
		
		$userInfo = self::instance()->userLogin($userLoginModel);
		if(empty($userInfo)){
			throw new BusinessException('请检查用户名或密码');
		}
		if($userInfo['IsActive'] == \haibao\user\model\data\UserLogin::IS_ACTIVE_NO){
			throw new BusinessException('帐号已被禁用！');
		}
		
		$userLoginModel->Id = $userInfo['UserLoginId'];
		$userLoginModel->UserId = $userInfo['Id'];
		$userLoginModel->NickName = $userInfo['NickName'];
		$userLoginModel->UserName = $userInfo['UserName'];
		
		
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_WAP){
			self::checkIsWeixinUser($userLoginModel->UserId);
		}
		return self::processLogin($userLoginModel,$remberMe);
	}
	
	/**
	 * 手机快速登录
	 * @param \haibao\user\model\data\UserLogin $userLoginModel	用户登录实体
	 * @param  $smsCaptcha  短信验证码
	 * @param  $source      来源
	 * @throws BusinessException
	 */
	public static function mobileFastLogin($userLoginModel, $smsCaptcha,$webCaptcha = ''){
		if(!$userLoginModel->LoginName && self::isMobile($userLoginModel->LoginName)){
			throw new BusinessException('手机号不能为空');
		}
		self::checkSmsCaptcha($userLoginModel->LoginName, $smsCaptcha);
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC){
			self::checkCaptcha($webCaptcha,true);
		}
		$userInfo = self::instance()->getUserByLoginName($userLoginModel->LoginName, \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE);
		if(empty($userInfo)){
			if($userLoginModel->Password){
				$userLoginModel->Password = self::encrypt($userLoginModel->Password);
			}else{
				$userLoginModel->Password = self::encrypt(self::randNum(7));
			}
			self::instance()->saveUser($userLoginModel);
			if($userLoginModel->UserId){
				\haibao\user\common\ScoreRule::operateScore($userLoginModel->UserId,\haibao\user\common\ScoreRule::TYPE_REGISTER);
			}
		}else{
			if($userInfo['IsActive'] == \haibao\user\model\data\UserLogin::IS_ACTIVE_NO){
				throw new BusinessException('帐号已被禁用！');
			}
			$userLoginModel->Id = $userInfo['UserLoginId'];
			$userLoginModel->UserId = $userInfo['Id'];
			$userLoginModel->NickName = $userInfo['NickName'];
			$userLoginModel->UserName = $userInfo['UserName'];
			self::instance()->addUserLoginHistory($userLoginModel);
		}
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_WAP){
			self::checkIsWeixinUser($userLoginModel->UserId);
		}
		return self::processLogin($userLoginModel);
	}
	
	/**
	 * 验证用户名密码是否匹配
	 * @param string $loginName 用户名
	 * @param string $password	密码
	 */
	public static function checkUserPassword($loginName,$password,$type=null){
		if(!$loginName){
			throw new BusinessException('帐号不能为空');
		}
		if(!$password){
			throw new BusinessException('密码不能为空');
		}
		return self::instance()->checkUserPassword($loginName,$password,$type);
	}
	
	/**
	 * 保存用户到正式用户表中
	 * @param \haibao\user\model\data\UserLogin $userLoginModel
	 * @param string $smsCaptcha	手机号验证码
	 */
	public static function saveUserByMobile($userLoginModel, $smsCaptcha,$webCaptcha = ''){
		if($userLoginModel->LoginNameType == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE){
			self::checkMobile($userLoginModel->LoginName);
			if (! Config::getConfig ( Config::IS_DEBUG )) {
			    self::checkSmsCaptcha($userLoginModel->LoginName, $smsCaptcha);
			}
			
		}
	    if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC){
	        self::checkCaptcha($webCaptcha,true);
	    }
		if(empty($userLoginModel->Password) && empty($userLoginModel->RepPassword)){
			$userLoginModel->Password = self::randNum(7);
			$userLoginModel->RepPassword = $userLoginModel->Password;
		}
		self::checkPwd($userLoginModel->Password, $userLoginModel->RepPassword);
		$userLoginModel->Password = self::encrypt($userLoginModel->Password);
		$userLoginModel->RepPassword = self::encrypt($userLoginModel->RepPassword);
		$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_USER;

		self::instance()->saveUser($userLoginModel);
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_WAP){
			self::checkIsWeixinUser($userLoginModel->UserId);
		}
		if($userLoginModel->UserId){
			\haibao\user\common\ScoreRule::operateScore($userLoginModel->UserId,\haibao\user\common\ScoreRule::TYPE_REGISTER);
		}
		return self::processLogin($userLoginModel);
	}
	
	public static function fastRegsterBind($userLoginModel, $smsCaptcha, $webCaptcha = null){
		if($userLoginModel->LoginNameType == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE){
			self::checkMobile($userLoginModel->LoginName);
			self::checkSmsCaptcha($userLoginModel->LoginName, $smsCaptcha);
		}
		
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC){
			self::checkCaptcha($webCaptcha,true);
		}
		if(empty($userLoginModel->Password) && empty($userLoginModel->RepPassword)){
			$userLoginModel->Password = self::randNum(7);
			$userLoginModel->RepPassword = $userLoginModel->Password;
		}
		self::checkPwd($userLoginModel->Password, $userLoginModel->RepPassword);
		$userLoginModel->Password = self::encrypt($userLoginModel->Password);
		$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_USER;
		self::addbindUser($userLoginModel, $userLoginModel->UserId);
	}
	
	/**
	 * 第三方登录
	 * @param \haibao\user\model\data\UserLogin $userLoginModel
     * @param string $avatarUrl	头像
	 */
	public static function thirdLogin($userLoginModel,$avatarUrl = '',$isLogin=true){
		// UserLogin表数据
		$userInfo = self::instance()->getUserIdByOpenId($userLoginModel->LoginName, $userLoginModel->LoginNameType, $userLoginModel->OpenAppType);
		$userId = 0;
		if($userInfo){
			$userId = $userInfo['UserId'];
			$userRegModel = self::instance()->getUserInfo($userId);
			// UserReg表数据
			if($userRegModel){
				if($userRegModel->IsActive == \haibao\user\model\data\UserLogin::IS_ACTIVE_NO){
					throw new BusinessException('帐号已被禁用！');
				}
				$nickName = $userRegModel->NickName;
				 
				$userLoginModel->Id = $userInfo['Id'];
				$userLoginModel->UserId = $userId;
				$userLoginModel->IsBind = $userInfo['IsBind'];
				$userLoginModel->NickName = $nickName;
				$userLoginModel->UserName = $userRegModel->UserName;
				self::instance()->addUserLoginHistory($userLoginModel, $userLoginModel->LoginName, true);
			}
		}else{
			$nickName = self::filterUtf8Char(trim($userLoginModel->NickName));
			$dbUser = self::getUserByUsername($nickName);
			$nickName = $dbUser ? self::randUsername($nickName) : $nickName;
			$userLoginModel->NickName = $nickName;
			$userLoginModel->UserName = $nickName;
			$userLoginModel->Password = self::encrypt(self::randNum(7));
			$userLoginModel->IsBind = \haibao\user\model\data\UserLogin::IS_BIND_NO;
			$userId = self::instance()->saveUser($userLoginModel, $userLoginModel->LoginName);
			if($userId){
				\haibao\user\common\ScoreRule::operateScore($userId,\haibao\user\common\ScoreRule::TYPE_REGISTER);
			}
			if(!empty($avatarUrl)){
				$avatarUrl = ltrim(self::saveAvatar($userId, file_get_contents($avatarUrl), \haibao\classlibrary\app\Config::getConfig(\haibao\classlibrary\app\Config::AVATAR_UPLOAD_PATH)), '/');
				self::instance()->setUserAuater($userId,$avatarUrl);
			}
			
			$userLoginModel->UserId = $userId;
		}
		if($userLoginModel->LoginNameType != \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN && $userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_WAP){
			self::checkIsWeixinUser($userLoginModel->UserId);
		}
		if($isLogin){
			self::processLogin($userLoginModel);
		}
	}
	
	/**
	 * 普通帐号绑定第三方帐号
	 * @param \haibao\user\model\data\UserLogin $userLoginModel 海报帐号
	 * @param $userId 第三方帐号ID
	 */
	public static function bindThirdUser($userLoginModel, $userId){
		if(!$userLoginModel->UserId){
			throw new BusinessException('用户ID不能为空！');
		}
		$password = self::instance()->getPasswordByUserId($userLoginModel->UserId);
		$userLoginModel->Password = $password;
		self::addbindUser($userLoginModel, $userId);
		self::instance()->updateUserPassword($userId, $password);
		self::instance()->updateBindStatus($userId);
	}
	
	/**
	 * 第三方帐号帐号绑定普通帐号
	 * @param \haibao\user\model\data\UserLogin $userLoginModel 第三方
	 * @param $userId 海报帐号ID
	 */
	public static function bindGeneralUser($userLoginModel, $userId){
		if(!$userId){
			throw new BusinessException('用户ID不能为空！');
		}
		$password = self::instance()->getPasswordByUserId($userId);
		$userLoginModel->Password = $password;
		self::addbindUser($userLoginModel, $userId);
		$userModel = self::instance()->getUserInfo($userId);
		$userLoginModel->UserName = $userModel->UserName;
		$userLoginModel->NickName = $userModel->NickName;
		self::instance()->addUserLoginHistory($userLoginModel);
		self::processLogin($userLoginModel);
	}
	
	public static function addbindUser($userLoginModel, $userId){
		if(!$userLoginModel->LoginName){
			throw new BusinessException('帐号不能为空！');
		}
		if(!$userLoginModel->LoginNameType){
			throw new BusinessException('帐号类型不能为空！');
		}
		if(empty($userLoginModel->Password)){
			$password = self::instance()->getPasswordByUserId($userId);
			$userLoginModel->Password = $password;
		}
		$userLoginModel->UserId = $userId;
		$userLoginModel->IsBind =  \haibao\user\model\data\UserLogin::IS_BIND_YES;
		$userLoginModel->LastLoginTime = new \DateTime();
		$userLoginModel->CreateTime = new \DateTime();
		self::instance()->add($userLoginModel);
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_WAP){
			self::checkIsWeixinUser($userLoginModel->UserId);
		}
	}
	
	public static function spaceBindUser($userLoginModel,$unBindUid,$reLogin=false){
		if(!$userLoginModel->LoginName){
			throw new BusinessException('帐号不能为空！');
		}
		if(!$userLoginModel->LoginNameType){
			throw new BusinessException('帐号类型不能为空！');
		}
		if(!$userLoginModel->UserId){
			throw new BusinessException('帐号Id不能为空！');
		}
		$password = self::instance()->getPasswordByUserId($userLoginModel->UserId);
		$userLoginModel->Password = $password;
		$userLoginModel->IsBind =  \haibao\user\model\data\UserLogin::IS_BIND_YES;
		$userLoginModel->LastLoginTime = new \DateTime();
		$userLoginModel->CreateTime = new \DateTime();
		self::instance()->add($userLoginModel);
		self::changeBindUser($userLoginModel,$unBindUid,$password);
		if($reLogin){
			self::processLogin($userLoginModel);
		}
	}
	
	public static function changeBindUser($model,$userId,$password){
		$result = self::instance()->getUserLoginType($userId);
		foreach($result as $list){
			if($list['LoginName'] != $model->LoginName){
				$userLoginModel = new \haibao\user\model\data\UserLogin();
				$userLoginModel->LoginName = $list['LoginName'];
				$userLoginModel->LoginNameType = $list['LoginNameType'];
				$userLoginModel->UserId = $model->UserId;
				$userLoginModel->OpenAppType = $list['OpenAppType'];
				$userLoginModel->SysAppType = $list['SysAppType'];
				$userLoginModel->IsBind =  \haibao\user\model\data\UserLogin::IS_BIND_YES;
				$userLoginModel->LastLoginTime = new \DateTime();
				$userLoginModel->CreateTime = new \DateTime();
				$userLoginModel->Password = $password;
				self::instance()->add($userLoginModel);
			}
		}
		self::instance()->updateBindStatus($userId,\haibao\user\model\data\UserLogin::IS_BIND_NO);
	}
	
	public static function unAccountBind($loginId){
		if(!$loginId){
			throw new BusinessException('帐号Id不能为空！');
		}
		self::instance()->unAccountBind($loginId);
	}
	
	/**
	 * 此方法供cms后台绑定马甲新添加用户使用
	 * @param \haibao\user\model\data\UserLogin $userLoginModel
	 */
	public static function saveUserByUsername($userLoginModel){
		self::checkUsername($userLoginModel->LoginName);
		self::checkPwd($userLoginModel->Password, $userLoginModel->RepPassword);
		$userLoginModel->Password = self::encrypt($userLoginModel->Password);
		$userLoginModel->SysAppType = \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC;
		$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_USER;
		self::instance()->saveUser($userLoginModel);
		return $userLoginModel->UserId;
	}

	/**
	 * 验证邮箱（激活）
	 * 更改auth_user、sns_profile表中的邮箱验证状态
	 */
	public static function validate($token){
		$userId = self::instance()->getUserIdByToken($token);
		if(!$userId){
			throw new BusinessException('邮箱验证链接过期！');
		}
		
		$userModel = self::instance()->getUserInfo($userId);
		if($userModel && $userModel->IsActive == \haibao\user\model\data\UserLogin::IS_ACTIVE_YES){
			throw new BusinessException('邮箱已经验证！');
		}
		
		$userLoginModel = new \haibao\user\model\data\UserLogin();
		$userLoginModel->UserId = $userModel->Id;
		$userLoginModel->UserName = $userModel->UserName;
		$userLoginModel->NickName = $userModel->NickName;
		$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_EMAIL;
        self::processLogin($userLoginModel);
	}

	/**
	 * 发送手机验证码
	 *
	 * 1、判断手机验证码是否合法
	 * 2、判断手机号码今日发送次数是否超出限制
	 * 3、获取手机验证码，从redis中查询是否已经存在验证码，存在则取出直接返回，不存在则生成验证码存储到redis中，30分钟内有效
	 * 4、调用接口发送手机验证码
	 * 5、发送成功后，记录该手机号今日发送次数
	 */
	public static function sendRegSms($mobile,$isBind=false){
		if(empty($mobile) || !self::isMobile($mobile)){
			throw new BusinessException('请输入正确的手机号！');
		}
		if($isBind){
			// self::sendSms($mobile, \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::SMS_CONTENT_FOR_BIND));
		    \haibao\sms\SMS::sendSms($mobile, \haibao\sms\SMS::TEMPLATE_2004, array(
		        self::genRandom($mobile)
		    ));
		}else{
			if(self::getUserByMobile($mobile)){
				throw new BusinessException('手机号已被注册！');
			}
			// self::sendSms($mobile, \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::SMS_CONTENT_FOR_REGISTER));
			\haibao\sms\SMS::sendSms($mobile, \haibao\sms\SMS::TEMPLATE_2001, array(
			    self::genRandom($mobile)
			));
		}
	}

	/**
	 * 发送找回密码手机验证码
	 */
	public static function sendBackpwdSms($mobile){
		if(empty($mobile) || !self::isMobile($mobile)){
			throw new BusinessException('手机号有误，请重新输入');
		}
		$userInfo = self::getUserByMobile($mobile);
		if(!$userInfo){
			throw new BusinessException('手机号有误，请重新输入');
		}
		// self::sendSms($mobile, \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::SMS_CONTENT_FOR_BACKPWD));
		\haibao\sms\SMS::sendSms($mobile, \haibao\sms\SMS::TEMPLATE_2002, array(
		    self::genRandom($mobile)
		));
	}
	
    /**
     * 快速登录短信验证
     * @param unknown $mobile
     * @throws BusinessException
     */
	public static function sendLoginSms($mobile,$isTvHaibao = false){
	    if(empty($mobile) || !self::isMobile($mobile)){
	        throw new BusinessException('请输入正确的手机号！');
	    }
	    if($isTvHaibao){
	        // self::sendSms($mobile, \haibao\classlibrary\tv\Config::getConfig(\haibao\classlibrary\tv\Config::SMS_CONTENT_FOR_REGISTER),$isTvHaibao);
	    }else{
	        // self::sendSms($mobile, \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::SMS_CONTENT_FOR_LOGIN));
	        \haibao\sms\SMS::sendSms($mobile, \haibao\sms\SMS::TEMPLATE_2003, array(
	            self::genRandom($mobile)
	        ));
	    }
	    
	}
	
	public static function visitorLogin($isFromComment = true){
	    $rand = mt_rand(1000, 9999);
		$unRegId = 10000000 + $rand+mt_rand(1, 999);
		$name = '海报用户'.$rand;
		$value = $unRegId.'###'.$name."###1";
		$expire = time() + 3600*24*30;
		
		$cookie = Config::getConfig(Config::CLASSLIBRARY_CONFIG_COOKIE);
		if($isFromComment){
			Cookie::set($cookie['cookie_key'].'_visitor_id', $value, $expire, $cookie['cookie_path'], $cookie['cookie_domain']);
		}else{
			Cookie::set($cookie['cookie_key'].'_visitor_id', $value, $expire, $cookie['cookie_path'], $cookie['cookie_domain']);
		}
		return true;
	}

	/**
	 * 退出登录
	 */
	public static function logout($userId,$isTvHaibao = false){
	    if($isTvHaibao){
	        self::deleteSession(\haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV);
	    }else{
	        self::deleteSession();	        
	    }
		return self::ucLogout($userId);
	}

	/**
	 * 找回密码，验证手机
	 */
	public static function verifyMobile($mobile, $smsCaptcha){
		if(!$smsCaptcha){
			throw new BusinessException('手机验证码不能为空！');
		}
		self::checkSmsCaptcha($mobile, $smsCaptcha);

		$userInfo = self::getUserByMobile($mobile);
		return $userInfo ? self::getToken($userInfo['Id']) : null;
	}

	/**
	 * 找回密码，确认用户并发送相应通知
	 */
	public static function backPwd($hbuser, $captcha = null, $isSendMail = false){
		if(!$hbuser){
			throw new BusinessException('邮箱或手机号码不能为空！');
		}
		if(!$isSendMail){
			self::checkCaptcha($captcha);
		}

		if(self::isEmail($hbuser)){
			self::backPwdByEmail(strtolower($hbuser));
		} elseif(self::isMobile($hbuser)) {
			self::backPwdByMobile($hbuser);
		} else{
			throw new BusinessException('帐号格式输入错误！');
		}
	}

	/**
	 * 通过手机号码找回密码
	 */
	public static function backPwdByMobile($mobile){
		if(!$mobile){
			throw new BusinessException('手机号码不能为空！');
		}
		$aUser = self::getUserByMobile($mobile);
		if(!$aUser){
			throw new BusinessException('手机号码不存在！');
		}
		if(!Config::getConfig(Config::IS_DEBUG)){
			// self::sendSms($mobile, \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::SMS_CONTENT_FOR_BACKPWD));
		    \haibao\sms\SMS::sendSms($mobile, \haibao\sms\SMS::TEMPLATE_2002, array(
		        self::genRandom($mobile)
		    ));
		}
	}

	/**
	 * 发送找回密码邮件
	 */
	public static function backPwdByEmail($email){
		$email = strtolower($email);
		$aUser = self::getUserByEmail($email);
		if(!$aUser){
			throw new BusinessException('邮箱有误，请重新输入');
		}
		$userInfo = self::getUserByEmail($email);
		$token = self::getToken($userInfo['Id']);
		$url = self::getFindPwdUrlByMail($email, $token);

		$subject = "海报时尚网账号重置密码";
		$content = sprintf(\haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::MAIL_FORGET_PWD_CONTENT), $aUser['NickName'], $url, $url, $aUser['NickName']);
		self::sendMail($subject, $content, $email);

		return $token;
	}

	/**
	 * 重置密码
	 */
	public static function resetPassword($token, $password, $password2){
		self::checkPwd($password, $password2);
		if(!$token){
			throw new BusinessException('链接错误！');
		}
		$userId = self::instance()->getUserIdByToken($token);
		if(!$userId){
			throw new BusinessException('请求错误！');
		}

		self::instance()->updateUserPassword($userId, self::encrypt($password), $token);
	}

	/**
	 * WAP重置密码
	 */
	public static function resetWapPassword($userId, $password, $password2){
	    self::checkPwd($password, $password2);
		if(!$userId){
			throw new BusinessException('请求错误！');
		}
		//self::checkOldPassword($userId, $oldPassword);
		self::instance()->updateUserPassword($userId, self::encrypt($password));
		self::logout($userId);
	}
	
	public static function updateAvatarByUid($userId,$arrSet){
		$wapUserData = new \haibao\www\data\WapUser();
		$wapUserData->updateAvatarByUid($userId,$arrSet);
	}

	/**
	 * 检测旧密码
	 */
	public static function checkOldPassword($userId, $oldPassword){
	    $dbPassword = self::instance()->getPasswordByUserId($userId);
		if(!self::checkPassword($oldPassword, $dbPassword)){
			throw new BusinessException('您输入的旧密码不正确！');
		}
	}
	
	/**
	 * 设置用户滑动验证的状态
	 * @param $mobile 手机号
	 */
	public static function setUserSlideVerifyStatus($mobile){
		self::isMobile($mobile);
		$cache = new \haibao\user\cache\User();
		$cache->setUserSlideVerifyStatus($mobile);
	}
	
	/**
	 * 获取用户滑动验证的状态
	 * @param $mobile 手机号
	 */
	public static function getUserSlideVerifyStatus($mobile){
		self::checkMobile($mobile);
		$cache = new \haibao\user\cache\User();
		return $cache->getUserSlideVerifyStatus($mobile);
	}
	
	/**
	 * 微信用户绑定
	 * @param \haibao\user\model\data\UserLogin $userLoginModel
	 */
	public static function addWeixinBindUser($userLoginModel){
		if(empty($userLoginModel->LoginName)){
			throw new BusinessException('登录名称不能为空！');
		}
		if(empty($userLoginModel->LoginNameType)){
			throw new BusinessException('登录名称类型不能为空！');
		}
		$password = self::instance()->getPasswordByUserId($userLoginModel->UserId);
		$userLoginModel->Password = $password;
		$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN;
		$userLoginModel->IsBind = \haibao\user\model\data\UserLogin::IS_BIND_YES;
		$userLoginModel->IsActive = \haibao\user\model\data\UserLogin::IS_ACTIVE_YES;
		$userLoginModel->LastLoginTime = new \DateTime();
		$userLoginModel->CreateTime = new \DateTime();
		self::instance()->addWeixinBindUser($userLoginModel);
	}
	
	/**
	 * 根据loginName 获取绑定用户的uid 
	 *  @param string $loginName   openId/unionId
	 */
	public static function getBindUidByLoginName($loginName, $loginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN){
		if(!$loginName){
			throw new BusinessException('登录用户名不能为空！');
		}
		return self::instance()->getUserInfoByLoginName($loginName, $loginNameType);
	}
	
	/**
	 * 根据openid获取第三方用户信息
	 * @param string $loginName   openId/unionId
	 * @param int $loginNameType  登录帐号类型
	 * @param int $openAppType    登录平台
	 */
	public static function getThirdUserInfoByOpenId($loginName, $loginNameType, $openAppType){
		if(!$loginName){
			throw new BusinessException('登录名称不能为空！');
		}
		if(!$loginNameType){
			throw new BusinessException('帐号类型不能为空！');
		}
		return self::instance()->getUserIdByOpenId($loginName, $loginNameType, $openAppType);
	}
	
	/**
	 * 检查是否是微信工作平台用户
	 * @param $userId  用户Id
	 */
	private static function checkIsWeixinUser($userId){
		if (isset($_COOKIE['unionId']) && $_COOKIE['unionId']){
			$unionId = $_COOKIE['unionId'];
			$userLoginModel = new \haibao\user\model\data\UserLogin();
			$userLoginModel->UserId = $userId;
			$userLoginModel->LoginName = $unionId;
			$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN;
			$userLoginModel->SysAppType = \haibao\user\model\data\UserLogin::SYS_APP_TYPE_WAP;
			$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_HAIBAO_WEIXIN;
			$result = self::getBindUidByLoginName($unionId);
			if($result){
				$isBind = isset($result['IsBind']) ? $result['IsBind'] : NUll;
				$regUserId = isset($result['UserId']) ? $result['UserId'] : NUll;
				if(empty($isBind)){
					self::addWeixinBindUser($userLoginModel);
				}else{
					if($regUserId != $userId){
						self::instance()->updateUserIdByLoginName($unionId,$regUserId,$userId);
					}
				}
			}else{
				self::addWeixinBindUser($userLoginModel);
			}
			$_COOKIE['unionIdLogin'] = $unionId;
		}
	}
	
	/**
	 * 对比手机验证码是否一致
	 */
	public static function checkSmsCaptcha($mobile, $captcha){
		if(!$mobile && !self::isMobile($mobile)){
			throw new BusinessException('请输入正确的手机号！');
		}
		if(!$captcha){
			throw new BusinessException('短信验证码不能为空！');
		}
		if(!Config::getConfig(Config::IS_DEBUG)){
			$data = new \haibao\user\cache\User();
			$redisCaptcha = $data->getSmsCaptcha($mobile);
			
			if($redisCaptcha != $captcha){
				throw new BusinessException('请输入正确的短信验证码！');
			}
		}
	}
	
	/**
	 * 个人中心重置密码
	 */
	public static function userSpaceResetPassword($userId, $password, $password2){
		self::checkPwd($password, $password2);
		if(!$userId){
			throw new BusinessException('请求错误！');
		}
		self::instance()->updateUserPassword($userId, self::encrypt($password));
	}
	

	/**
	 * 从auth_user中根据手机号码查询用户信息
	 */
	public static function getUserByMobile($mobile){
		return self::instance()->getUserByLoginName($mobile, \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE);
	}

	/**
	 * 从auth_user中根据用户ID取用户数据
	 */
	public static function getUserByUserId($userId){
		if(!$userId){
			throw new BusinessException('用户ID错误！');
		}
		return self::instance()->getUserInfo($userId);
	}

	/**
	 * 评论，从  auth_user中根据ID取得用户数据名列表
	 */
	public static function getUsersByIds($Ids){
		if(!$Ids){
			throw new BusinessException('用户ID错误！');
		}
		$data = new \haibao\user\data\User();
		return $data->getUsersByIds($Ids);
	}

	public static function getUsernamesByUserIds($userIds){
		$data = new \haibao\user\data\User();
		$userNames = array();
		$userInfoList = $data->getUsersByIds($userIds);
		foreach ($userInfoList as $userInfo){
			$userNames[$userInfo['Id']] = $userInfo['UserName'];
		}
		
		return $userNames;
	}
	
	public static function getUserNickNameByUserIds($userIds){
		$data = new \haibao\user\data\User();
		$userNames = array();
		$userInfoList = $data->getUsersByIds($userIds);
		foreach ($userInfoList as $userInfo){
			$userNames[$userInfo['Id']] = $userInfo['NickName'];
		}
	
		return $userNames;
	}
	
	public static function getUserByLoginName($loginName,$loginNameType){
		return self::instance()->getUserByLoginName($loginName,$loginNameType);
	}
	
	/**
	 * 从auth_user中根据邮箱取用户数据
	 */
	public static function getUserByEmail($email){
		if(!$email && !self::isEmail($email)){
			throw new BusinessException('请输入正确的邮箱！');
		}
		$email = strtolower($email);
		return self::instance()->getUserByLoginName($email, \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_EMAIL);
	}

	/**
	 * 根据用户昵称取用户数据
	 */
	public static function getUserByNickname($nickname){
		if(!$nickname){
			throw new BusinessException('请输入用户昵称！');
		}
		return self::instance()->getUserIdByNickName($nickname);
	}

	/**
	 * 从auth_user中根据用户名取用户数据
	 */
	public static function getUserByUsername($username){
		if(!$username){
			throw new BusinessException('请输入用户名！');
		}
		return self::instance()->getUserByLoginName($username, \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_NAME);
	}

	/**
	 * 通过用户名，在auth_user中like出所以的用户名
	 */
	public static function getUserListByUserName($username){
		if(!$username){
			throw new BusinessException('请输入用户名！');
		}
		$data = new \haibao\user\data\User();
		return $data->getUserListByUserName($username);
	}
	/**
	 * 手机号码是否存在
	 */
	public static function checkMobile($mobile){
		if(!$mobile && !self::isMobile($mobile)){
			throw new BusinessException('请输入正确的手机号！');
		}
		if( self::getUserByMobile($mobile) ){
			throw new BusinessException('手机号已被注册！');
		}
	}

	/**
	 * 邮箱地址是否已经存在
	 */
	public static function checkEmail($email){
		if(mb_strlen($email) == 0){
			throw new BusinessException('请输入正确的邮箱！');
		}
		if(!self::isEmail($email)){
			throw new BusinessException('邮箱格式错误！');
		}
		if( self::getUserByEmail($email) ){
			throw new BusinessException('邮箱已被注册！');
		}
	}

	/**
	 * 用户昵称是否已经存在
	 */
	public static function checkNickname($nickname){
		$ret = true;
		if(mb_strlen($nickname, 'gb2312') == 0){
			throw new BusinessException('请输入正确的用户昵称！');
		}
		if(mb_strlen($nickname, 'gb2312') < 4){
			throw new BusinessException('用户昵称长度太短！');
		}
		if( self::getUserByNickname( $nickname ) ){
			throw new BusinessException('用户昵称已存在！');
		}
	}

	/**
	 * 用户名是否已经存在
	 */
	public static function checkUsername($username){
		$ret = true;
		if(mb_strlen($username, 'gb2312') == 0){
			throw new BusinessException('请输入正确的用户名！');
		}
		if(mb_strlen($username, 'gb2312') < 4){
			throw new BusinessException('用户名长度太短！');
		}
		if(mb_strlen($username, 'gb2312') > 20){
			throw new BusinessException('用户名长度太长！');
		}
		if(preg_match("/^[0-9]*$/i", $username)){
			throw new BusinessException('用户名不能是纯数字！');
		}
		if( self::getUserByUsername( $username ) ){
			throw new BusinessException('用户名已存在！');
		}
	}

	/**
	 * 检查密码输入
	 */
	public static function checkPwd($password, $password2){
		$len = mb_strlen($password,'utf8');
		if($len < 6 || $len > 20){
			throw new BusinessException('请输入6-20位密码！');
		}
		if($password != $password2){
			throw new BusinessException('两次输入密码不一致！');
		}
	}

	/**
	 * 验证码是否正确
	 */
	public static function checkCaptcha($captcha,$isReg = false){
		$captcha = strtolower( $captcha );

		$ret = true;
		if(mb_strlen($captcha) == 0){
			throw new BusinessException('请输入图形验证码！');
		}
		\haibao\frame\http\Cookie::$cookie = null;
		$sessionId = \haibao\frame\http\Cookie::get('__captcha');
		$rightCaptcha = \haibao\comment\cache\CommentCache::getCrontabCache($sessionId);
		if($captcha != $rightCaptcha){
			throw new BusinessException('请输入正确的图形验证码！');
		}
		if($isReg){
			\haibao\comment\cache\CommentCache::delCrontabCache($sessionId);
		}
		return $ret;
	}

	/**
	 * 验证用户重复注册
	 */
	public static function checkTourist($tourist){
		if (isset($_COOKIE['tourist'])){
			$check = $_COOKIE['tourist'];
		}else{
			$check = '';
		}
		if($tourist == $check){
			throw new BusinessException('请不要重复注册！');
		}
	}

	/**
	 * 将已注册游客写入cookie
	 * @param unknown $tourist
	 */
	public static function setTouristCookie($tourist){
	    $cookie = Config::getConfig(Config::CLASSLIBRARY_CONFIG_COOKIE);
		setcookie('tourist', null, time()-3600, $cookie['cookie_path'], $cookie['cookie_domain'] );
		setcookie ( 'tourist', $tourist, time () + 604800, $cookie ['cookie_path'], $cookie ['cookie_domain'] );
	}
	
	/**
	 * 根据手机号码生成随机验证码
	 */
	public static function genRandom($mobile){
	    return self::instance ()->getSmsCaptcha ($mobile);
	}
	
	/**
	 * 发送短信
	 */
	public static function sendSms($mobile, $content,$isTvHaibao = false) {
		$sendRecord = self::instance ()->getSendCount ( $mobile );
		if ($sendRecord && strpos ( $sendRecord, '_' )) {
			$ret = explode ( '_', $sendRecord );
			$count = $ret [0];
			$lastSendTime = $ret [1];
			$ip = isset ( $ret [2] ) ? $ret [2] : null;
			
			if (( int ) $count >= 5) {
				throw new BusinessException ( '该手机号码今日发送次数超出限制！' );
			}
			if (( int ) $count >= 5 && $ip == self::getIPaddress ()) {
				throw new BusinessException ( '当前IP今日发送次数超出限制！' );
			}
		} else {
			if (( int ) $sendRecord >= 5) {
				throw new BusinessException ( '该手机号码今日发送次数超出限制！' );
			}
		}
		
		$result = true;
		$content = sprintf ( $content, self::instance ()->getSmsCaptcha ( $mobile ) );
		if ($mobile && $content) {
			if (! Config::getConfig ( Config::IS_DEBUG )) {
				self::instance ()->setSendCount($mobile, self::getIPaddress());
				if($isTvHaibao){
				    $result = \haibao\sms\SMSClient::sendSMS($mobile, $content,'/sites/tv/logs/sms.log');
				}else{
				    $result = \haibao\sms\SMSClient::sendSMS($mobile, $content);				    
				}
			}
		}
		return $result;
	}

	public static function sendMail($subject, $content, $email){
		$email = strtolower($email);
		$data = new \haibao\user\data\AuthUser();
		$data->sendMail($subject, $content, $email, \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_MAIL_SENDER_ADDRESS));
	}

	public static function getUserByToken($token){
		$cache = new \haibao\user\cache\User();
		return $cache->getUserByToken($token);
	}

	public static function getTokenByMobile($mobile){
		$userInfo = self::getUserByMobile($mobile);
		if($userInfo['id']){
			return self::getToken($userInfo['id']);
		}
	}

	/**
	 * 添加签到
	 * @param array $data
	 */
	public static function addSign($data){
		if(!$data['userId']){
			throw new BusinessException('没有用户');
		}
		if(!$data['credit']){
			throw new BusinessException('没有积分');
		}
		$result = new \haibao\user\data\AuthUser();
		$result->addSign($data);
	}

	public static function addSystemMessage($arrData){
		$data = new \haibao\user\data\AuthUser();
		$data->addSystemMessage($arrData);
	}

	public static function getNickNameByUserIds($userIds){
	    $userIds = array_filter(array_unique($userIds));
		$data = new \haibao\user\data\User();
		
		$userNickNames = array();
		$userInfoList = $data->getUsersByIds($userIds);
		foreach ($userInfoList as $userInfo){
			$userNickNames[$userInfo['Id']] = $userInfo['NickName'];
		}
		return $userNickNames;
	}

	/**
	 * 判断是否有签到
	 * @param Array $data
	 * @return boolean
	 */
	public static function isSign($data){
		if(!$data['userId'] || !$data['credit']){
			return false;
		}
		$result = new \haibao\user\data\AuthUser();
		return $result->isSign($data,0);
	}
	/**
	 * 到一周的签到
	 * @param unknown $userId
	 * @throws BusinessException
	 */
	public static function getWeekSign($userId){
		if(!$userId){
			return array();
		}
		$data = new \haibao\user\data\AuthUser();
		return $data->getWeekSign($userId);
	}
	
	/**
	 * 保存用户头像
	 */
	private static function saveAvatar($userId, $stream, $uploadFile){
		try{
			$ext = self::getImageExt($stream);
			$orgPath = self::getAbsolutePath( $uploadFile.self::getAvatarById($userId, 'big') );
			self::createDir($orgPath);
			file_put_contents($orgPath, $stream);
	
			$sizeArr = array(
					'small' => '48*48',
					'middle' => '120*120',
					'big' => '200*200'
			);
			foreach ($sizeArr as $type=>$size){
				$path = $uploadFile.self::getAvatarById($userId, $type);
				$typePath = self::getAbsolutePath($path);
				list($width, $height) = explode('*', $size);
				self::createDir($typePath);
				self::imagecropper($orgPath, $typePath, $width, $height);
			}
		} catch (\Exception $e){
			throw new BusinessException($e);
		}
	
		return $uploadFile.self::getAvatarById($userId);
	}
	
	/**
	 * 创建文件保存路径
	 */
	private static function createDir($path){
		$path = pathinfo( $path );
		return is_writable($path['dirname']) ?: mkdir($path['dirname'], 750, true);
	}
	
	/**
	 * 剪裁用户头像
	 */
	private static function imagecropper($source_path, $dest_path, $target_width, $target_height){
		$source_info = getimagesize($source_path);
		$source_width = $source_info[0];
		$source_height = $source_info[1];
		$source_mime = $source_info['mime'];
		$source_ratio = $source_height / $source_width;
		$target_ratio = $target_height / $target_width;
	
		if ($source_ratio > $target_ratio) {
			$cropped_width = $source_width;
			$cropped_height = $source_width * $target_ratio;
			$source_x = 0;
			$source_y = ($source_height - $cropped_height) / 2;
		}elseif ($source_ratio < $target_ratio) {
			$cropped_width = $source_height / $target_ratio;
			$cropped_height = $source_height;
			$source_x = ($source_width - $cropped_width) / 2;
			$source_y = 0;
		}else {
			$cropped_width = $source_width;
			$cropped_height = $source_height;
			$source_x = 0;
			$source_y = 0;
		}
	
		switch ($source_mime) {
			case 'image/gif':
				$source_image = imagecreatefromgif($source_path);
				break;
			case 'image/jpeg':
				$source_image = imagecreatefromjpeg($source_path);
				break;
			case 'image/png':
				$source_image = imagecreatefrompng($source_path);
				break;
			default:
				return false;
				break;
		}
		 
		$target_image = imagecreatetruecolor($target_width, $target_height);
		$cropped_image = imagecreatetruecolor($cropped_width, $cropped_height);
	
		imagecopy($cropped_image, $source_image, 0, 0, $source_x, $source_y, $cropped_width, $cropped_height);
		imagecopyresampled($target_image, $cropped_image, 0, 0, 0, 0, $target_width, $target_height, $cropped_width, $cropped_height);
	
		switch ($source_mime) {
			case 'image/gif':
				imagegif($target_image, $dest_path);
				break;
			case 'image/jpeg':
				imagejpeg($target_image, $dest_path);
				break;
			case 'image/png':
				imagepng($target_image, $dest_path);
				break;
		}
		imagedestroy($source_image);
		imagedestroy($target_image);
		imagedestroy($cropped_image);
	}
	
	/**
	 * 根据二进制流判断图片格式
	 */
	private static function getImageExt($stream){
		$jfif_exif = substr($stream, 0, 100);
		if(strpos($jfif_exif, 'JFIF') || strpos($jfif_exif, 'JPEG') || strpos($jfif_exif, 'JPG') || strpos($jfif_exif, 'Exif') || strpos($jfif_exif, 'EXIF')){
			$ext = '.jpg';
		}else if(strpos($jfif_exif, 'PNG')){
			$ext = '.png';
		}else if(strpos($jfif_exif, 'GIF')){
			$ext = '.gif';
		}else{
			$ext = '.jpg';
		}
		return $ext;
	}
	
	/**
	 * 根据用户id获取用户真实头像地址
	 */
	private static function getAvatarById($userId, $size = 'middle', $type = ''){
		$size = in_array($size, array('big', 'middle', 'small')) ? $size : 'big';
		$userId = abs(intval($userId));
		$userId = sprintf("%09d", $userId);
		$dir1 = substr($userId, 0, 3);
		$dir2 = substr($userId, 3, 2);
		$dir3 = substr($userId, 5, 2);
		$typeadd = $type == 'real' ? '_real' : '';
		return  $dir1.'/'.$dir2.'/'.$dir3.'/'.substr($userId, -2).$typeadd."_avatar_$size.jpg";
	}
	
	/**
	 * 获取保存图片的绝对路径
	 */
	private static function getAbsolutePath($path){
		return \haibao\frame\Environment::path() . $path;
	}

	/**
	 * 同步系统消息到消息盒子表中，并更新消息数量
	 */
	private static function processMessage($userId,$nickname){
		\haibao\www\business\message\MessageSystem::syncMessage($userId);
		self::mergeUserData($userId,$nickname);
	}

	/**
	 * 合并用户数据
	 */
	private static function mergeUserData($userId,$nickname){
		$unRegUserId = \haibao\comment\business\Comments::mergeUserData($userId,$nickname);
		\haibao\www\business\message\MessageBox::mergeUserData($userId,$unRegUserId);
	}

	/**
	 * @param \haibao\user\model\data\UserLogin $userLoginModel
	 */
	private static function processLogin($userLoginModel, $remberMe = true){
	    self::writeSession($userLoginModel, $remberMe);
	    if($userLoginModel->SysAppType != \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
            self::processMessage($userLoginModel->UserId, $userLoginModel->NickName);
            if($userLoginModel->UserId){
            	\haibao\user\common\ScoreRule::operateScore($userLoginModel->UserId,\haibao\user\common\ScoreRule::TYPE_LOGIN);
            }
            $uti = self::ucLogin($userLoginModel->UserId);
            return $uti;
	    }
	}

	/**
	 * 写session到redis中（7天内有效，与存储在客户端的cookie保持一致）
	 * @param \haibao\user\model\data\UserLogin $userLoginModel
	 */
	public static function writeSession($userLoginModel, $remberMe = true){
		self::deleteSession($userLoginModel->SysAppType);

		$userId = $userLoginModel->UserId;
		
	    $cookie = Config::getConfig(Config::CLASSLIBRARY_CONFIG_COOKIE);
		$expire = time() + $cookie['cookie_expire']; //过期的时间
		
		$key = self::getSessionId($userId);
		
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
		    $cookie = \haibao\classlibrary\tv\Config::getConfig(\haibao\classlibrary\tv\Config::TV_CONFIG_TVCOOKIE);
		    $expire = time() + $cookie['cookie_expire']; //tvhaibao过期的时间
		}
		
		$avator = Config::getConfig(Config::CLASSLIBRARY_CONFIG_HOST_BBS).'/uc_server/avatar.php?uid='.$userId.'&size=middle';

		$str = 'j2(4b05c_4((o+0penbd5x6w^2*lx3ol)pk!my#vzr+&964ln1';
		$value = '_auth_user_id = L' . $userId . 'L.';
		$value .= '_auth_nick_name = L' . $userLoginModel->NickName . 'L.';
		$value .= '_auth_user_name = L' . $userLoginModel->UserName . 'L.';
		$value .= '_auth_login_name = L' . $userLoginModel->LoginName . 'L.';
		$value .= '_auth_user_login_type = L' . $userLoginModel->LoginNameType . 'L.';
		$value .= '_auth_user_avator = L' . $avator ."L.";
		
		$value = base64_encode($value . md5($value.$str));

		$cache = new \haibao\user\cache\User();
		if($userLoginModel->SysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
		    $cache->setSession($key, $value, $expire,true);
		}else{
		    $cache->setSession($key, $value, $expire);
		}

		if($remberMe){
			Cookie::set($cookie['cookie_key'], $key, $expire, $cookie['cookie_path'], $cookie['cookie_domain']);
			if($userLoginModel->SysAppType != \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
			    setcookie($cookie['old_cookie_key'], $key, $expire, $cookie['cookie_path'], $cookie['cookie_domain']);
			}
		}

		// 旧版前台系统部分频道不试用新系统，需要做登录兼容（********正式上线后打开********）
		if( extension_loaded('tokyo_tyrant') ){
		    $ttserver = Config::getConfig(Config::CLASSLIBRARY_CONFIG_TTSERVER);
			$tt = new \TokyoTyrantTable($ttserver['ttserver_host'], $ttserver['ttserver_port']);
			$str = "j2(4b05c_4((o+0penbd5x6w^2*lx3ol)pk!my#vzr+&964ln1";
			$value = "(dp1\nS'_auth_user_backend'\np2\nS'django.contrib.auth.backends.ModelBackend'\np3\nsS'_auth_user_id'\np4\nL{$userId}L\ns.";
			$value_md5 = md5($value.$str);
			$value = base64_encode($value.$value_md5);
			$values = array(
					'data' => $value,
					'expire' => $expire
			);
			$result = $tt->put($key, $values);
		}
	}

	/**
	 * 发送验证邮件
	 * @param \haibao\user\model\data\AuthUser $model
	 */
	private static function sendValidateEmail($model){
		$validateUrl = self::getValidateUrl($model->Id);

		$subject = "海报时尚网账号激活";
		$content = \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::MAIL_CONTENT);
		$content = sprintf($content, $model->username, $validateUrl, $validateUrl);
		self::sendMail($subject, $content, $model->email);
	}

	/**
	 * 根据激活码token查询存储在redis中的用户id
	 */
	private static function getUserIdByToken($token){
		if(!$token){
			throw new BusinessException('token不能为空！');
		}
		$data = new \haibao\user\data\AuthUser();
		return $data->getUserIdByToken($token);
	}

	/**
	 * 获取激活链接
	 */
	private static function getValidateUrl($userId){
		$data = new \haibao\user\data\AuthUser();
		$token = $data->getToken($userId);

		return \haibao\frame\http\HttpEnvironment::domain().'validate.html?token='.$token;
	}

	/**
	 * 过滤字符串,保留UTF8字母数字中文及部份符号
	 */
	private static function filterUtf8Char($ostr){
		preg_match_all('/[\x{FF00}-\x{FFEF}|\x{0000}-\x{00ff}|\x{4e00}-\x{9fff}]+/u', $ostr, $matches);
		$str = join('', $matches[0]);
		if ($str == '') { // 含有特殊字符需要逐個處理
			$returnstr = '';
			$i = 0;
			$str_length = strlen($ostr);
			while ($i <= $str_length) {
				$temp_str = substr($ostr, $i, 1);
				$ascnum = Ord($temp_str);
				if ($ascnum >= 224) {
					$returnstr = $returnstr . substr($ostr, $i, 3);
					$i = $i + 3;
				} elseif ($ascnum >= 192) {
					$returnstr = $returnstr . substr($ostr, $i, 2);
					$i = $i + 2;
				} elseif ($ascnum >= 65 && $ascnum <= 90) {
					$returnstr = $returnstr . substr($ostr, $i, 1);
					$i = $i + 1;
				} elseif ($ascnum >= 128 && $ascnum <= 191) { // 特殊字符
					$i = $i + 1;
				} else {
					$returnstr = $returnstr . substr($ostr, $i, 1);
					$i = $i + 1;
				}
			}
			$str = $returnstr;
			preg_match_all('/[\x{FF00}-\x{FFEF}|\x{0000}-\x{00ff}|\x{4e00}-\x{9fff}]+/u', $str, $matches);
			$str = join('', $matches[0]);
		}
		return $str;
	}

	/**
	 * 校验密码是否正确
	 * @return boolean
	 */
	private static function checkPassword($password, $dbPassword){
		$pwd_prefix = explode('$', $dbPassword);
		$password = $pwd_prefix[1] . $password;
		$password = 'sha1$' . $pwd_prefix[1] . '$' . sha1($password);
		return $password == $dbPassword;
	}

	/**
	 * 用户密码加密
	 */
	public static function encrypt($password){
		$str = rand(0, 1);
		$pwd_prefix = sha1($str);
		$pwd_prefix = substr($pwd_prefix, 0,5);
		$pwd = sha1($pwd_prefix . $password);
		$pwd = 'sha1$' . $pwd_prefix . '$' . $pwd;
		return $pwd;
	}

	/**
	 * 同步ucenter登录
	 */
	public static function ucLogin($userId){
		self::loadClient();
		return uc_api_post('user', 'synlogin', array('uid' => $userId));
	}

	/**
	 * 同步ucenter退出
	 */
	private static function ucLogout($userId){
		self::loadClient();
		return uc_api_post('user', 'synlogout', array());
	}

	private static function loadClient(){
		require_once(HttpEnvironment::path().'/sites/common/third/uc_client/config_global.php');
		require_once(HttpEnvironment::path().'/sites/common/third/uc_client/config_ucenter.php');
		require_once(HttpEnvironment::path().'/sites/common/third/uc_client/client.php');
	}

	public static function isEmail($email){
		$email = strtolower($email);
		return filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	public static function isMobile($mobile){
		return preg_match("/^1[3|4|5|7|8][0-9]\d{8,8}$/", $mobile);
	}

	/**
	 * 邮箱方式找回密码的链接
	 */
	private static function getFindPwdUrlByMail($userId, $token){
		return \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HOST_USER).'/reset.html?token='.$token;
	}

	/**
	 * 根据用户id生成token存储在redis中
	 */
	private static function getToken($userId){
	    return self::instance()->getToken($userId);
	}

	/**
	 * 用户名后加4为随机数
	 */
	private static function randUsername($username){
		$name = $username.self::randNum(4);
		$dbUser = self::getUserByUsername($name);
		if(!$dbUser){
			return $name;
		}else{
			self::randUsername($username);
		}
	}

	/**
	 * 随机4位数字
	 */
	private static function randNum($len){
		$arr = range(0, 9);
		srand((float)microtime() * 1000000);
		shuffle($arr);
		$str = join('', $arr);
		return empty($len) ? $str : substr($str, 0, $len);
	}

	/**
	 * 通过userIds获取用户信息
	 */
	public static function getProfileByUserIds($userIds){
		$data = new \haibao\user\data\User();
		$userNames = array();
		$userInfoList = $data->getUsersByIds($userIds);
		foreach ($userInfoList as $userInfo){
			$userNames[$userInfo['Id']]['UserName'] = $userInfo['UserName'];
		}
		
		return $userNames;
	}

	/**
	 * 得到用户资料百分比
	 * @param unknown $userId
	 * @return number
	 */
	public static function getUserDatumScale($userId){
	    $hasProfile = $moreProfile = false;
	    $infoLimit = $percents = 0;
		if(!$userId){
			return 0;
		}
// 		$data = new \haibao\user\data\authuser();
// 		return $data->getUserDatumScale($userId);

	   $currentUserProfile = \haibao\user\business\UserInfo::getUserProfileById((int)$userId);
	    $currentUserInfo = \haibao\user\business\User::getUserByUserId((int)$userId);
		    
		 
	    if($currentUserProfile){
	        if ($currentUserProfile->Sign) {
	            $infoLimit ++;
	            $percents += 15;
	        }
	        if ($currentUserProfile->Province || $currentUserProfile->City) {
	            $infoLimit ++;
	            $percents += 10;
	        }
	        if ($currentUserProfile->LikeTag) {
	            $infoLimit ++;
	            $currentUserProfile->LikeTag = explode ( ',', $currentUserProfile->LikeTag );
	            $percents += 10;
	        }
	        if ($currentUserProfile->Birth) {
	            if ($infoLimit >= 3) {
	                $currentUserProfile->Birth = null;
	                $moreProfile = true;
	            } else {
	                $currentUserInfo->Birth = \haibao\www\sites\common\UserInfo::formatBirth ( $currentUserInfo->Birth );
	                $infoLimit ++;
	            }
	            $percents += 10;
	        }
	        if ($currentUserProfile->Profession) {
	            if ($infoLimit >= 3) {
	                $currentUserProfile->Profession = null;
	                $moreProfile = true;
	            } else {
	                $infoLimit ++;
	            }
	            $percents += 10;
	        }
	
	        if ($infoLimit) {
	            $hasProfile = true;
	        }
	    }
	    if($currentUserInfo){
	
	        if ($currentUserInfo->AvatarUrl) {
	            $percents += 20;
	        }
	
	        if ($currentUserInfo->Gender != \haibao\user\model\data\UserReg::Gender_TYPE_UN) {
	            $percents += 10;
	        }
	
	        if ($currentUserInfo->NickName) {
	            $percents += 15;
	        }
	    }
		
	    return $percents; 
	}

	/**
	 * 得到积分
	 * @param unknown $userId
	 */
	public static function getUserCredits($userId){
	    $score = 0;
		if(!$userId){
			return 0;
		}
// 		$data = new \haibao\user\data\authuser();
// 		return $data->getUserCredits($userId);

		$score = \haibao\user\business\UserInfo::getUserScore((int)$userId);
		return $score; 
	}

	/**
	 * 得到用户评论数
	 * @param unknown $userId
	 * @return number
	 */
	public static function getUserCommentsNum($userId){
		//return 0;
		if(!$userId){
			return 0;
		}
// 		$data = new \haibao\user\data\authuser();
// 		return $data->getUserCommentsNum($userId);

		return self::packMessageNum($userId, \haibao\www\model\data\message\MessageBox::MESSAGE_TYPE_COMMENT);
	}

	/**
	 * 得到用户站内信消息
	 */
	public static function getUserPmNum($userId){
		if(!$userId){
			return 0;
		}
// 		$data = new \haibao\user\data\authuser();
// 		return $data->getUserPmNum($userId);
		return self::packMessageNum($userId, \haibao\www\model\data\message\MessageBox::MESSAGE_TYPE_PRIVATE);
		
	}

	private static function packMessageNum($userId,$type){
	    $dataList = array();
	    $messageBoxFilter = new \haibao\www\model\filter\message\MessageBox();
	    $messageBoxFilter->select(array(
	        \haibao\www\model\filter\message\MessageBox::CONDITION_FIELD_NAME_ID,
	        \haibao\www\model\filter\message\MessageBox::CONDITION_FIELD_NAME_MESSAGE_TYPE,
	        \haibao\www\model\filter\message\MessageBox::CONDITION_FIELD_NAME_TO_USER_ID,
	        \haibao\www\model\filter\message\MessageBox::CONDITION_FIELD_NAME_STATUS
	    ));
	    $messageBoxFilter->where(\haibao\www\model\filter\message\MessageBox::CONDITION_FIELD_NAME_TO_USER_ID, \haibao\frame\data\query\Condition::CONDITION_EQUAL, (int)$userId);
	    $messageBoxFilter->where(\haibao\www\model\filter\message\MessageBox::CONDITION_FIELD_NAME_MESSAGE_TYPE, \haibao\frame\data\query\Condition::CONDITION_EQUAL, $type);
	    $messageBoxFilter->where(\haibao\www\model\filter\message\MessageBox::CONDITION_FIELD_NAME_STATUS, \haibao\frame\data\query\Condition::CONDITION_EQUAL, \haibao\www\model\data\message\MessageBox::STATUS_ENABLE);
	    $dataList = \haibao\www\business\message\MessageBox::getMessageListByType($messageBoxFilter);
	    return count($dataList);
	}
	/**
	 * 得到用户相册内图片数
	 * @param unknown $userId
	 * @return Ambigous <string, number, NULL, \DateTime>
	 */
	public static function getUserAlbumNum($userId){
		if(!$userId){
			return 0;
		}
		$data = new \haibao\user\data\authuser();
		return $data->getUserAlbumNum($userId);
	}

	/**
	 * 得到用户分级
	 * @param unknown $userId
	 * @return multitype:NULL Ambigous <NULL, \DateTime>
	 */
	public static function getUserGroup($userId){
		if(!$userId){
			return array();
		}
		$data = new \haibao\user\data\authuser();
		return $data->getUserGroup($userId);
	}

	public static function getUserImarker($userId){
	    $markList = array();
		if(!$userId){
			return array();
		}
// 		$data = new \haibao\user\data\authuser();
// 		return $data->getUserImarker($userId);
		$favoriteFilter = new \haibao\www\model\filter\Favorite();
		$favoriteFilter->select(array(
		    \haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_ID,
		    \haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_AID,
		    \haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_DATELINE,
		    \haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_IDTYPE,
		    \haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_TITLE,
		    \haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_UID,
		    \haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_ISREAD
		));
		$favoriteFilter->order(\haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_DATELINE,false);
		$favoriteFilter->where(\haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_UID, \haibao\frame\data\query\Condition::CONDITION_EQUAL, (int)$userId);
		$favoriteFilter->where(\haibao\www\model\filter\Favorite::CONDITION_FIELD_NAME_IDTYPE, \haibao\frame\data\query\Condition::CONDITION_EQUAL, \haibao\www\model\data\Favorite::ID_TYPE_AID);
		$markList = \haibao\www\business\Favorite::getUseriMark($favoriteFilter);
		return count($markList);
	}

	public static function getUserLoginInfo($userId){
		$data = new \haibao\user\data\User();
		return $data->getUserLoginType($userId);
	}
	
	public static function addPkLike($userId,$threadId,$pkId){
		if(!$userId){
			return false;
		}
		if(!$threadId){
			return false;
		}
		if(!$pkId){
			return false;
		}
		$data = new \haibao\user\data\authuser();
		return $data->addPkLike($userId,$threadId,$pkId);
	}

	public static function getPkLike($pkId){
		if(!$pkId){
			return 0;
		}
		$data = new \haibao\user\data\authuser();
		return $data->getPkLike($pkId);
	}
	
	public static function getUserByFilter($filter){
		$data = new \haibao\user\data\User();
		return $data->getAll($filter);
	}
	
	public static function updateUserProfile($filter,$arrSet){
		$data = new \haibao\user\data\UserProfiles();
		$data->update($arrSet, $filter);
	}
	
	public static function updateUserInfo($filter,$arrSet){
		$data = new \haibao\user\data\User();
		$data->update($arrSet, $filter);
	}
	
	public static function updateUserLoginName($userId,$loginName,$loginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE){
		self::instance()->updateUserLoginName($userId,$loginName,$loginNameType);
	}
	
	/**
	 * 找回密码，确认用户
	 */
	public static function checkWapMobile($mobile){
		if(!$mobile && !self::isMobile($mobile)){
			throw new BusinessException('请输入正确的手机号！');
		}
		$aUser = self::getUserByMobile($mobile);
		if(!$aUser){
			throw new BusinessException('手机号码不存在！');
		}
	}

	/**
	 * WAP修改密码
	 */
	public static function resetWapPwd($userId, $password, $password2 ,$captcha ,$mobile){
		self::checkWapMobile($mobile);
		self::checkSmsCaptcha($mobile, $captcha);
		self::checkPwd($password, $password2);
		if(!$userId){
			throw new BusinessException('请求错误！');
		}
		self::instance()->updateUserPassword($userId, self::encrypt($password));
	}

	/**
	 * 论坛修改密码
	 */
	public static function resetBbsPassword($userId,$password){
	    self::instance()->updateUserPassword($userId, self::encrypt($password));
	}

	private static function getSessionId($userId){
		$str="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$n=8;
		$len = strlen($str)-1;
	
		$s = '';
		for($i=0;$i<$n;$i++) {
			$s .= $str[mt_rand(0,$len)];
		}
		$timesap = time();
		$s = $s . $userId . $timesap . rand(1000, 10000);
		return md5($s);
	}
	
	private static function deleteSession($sysAppType = 1){
	    $cookie = Config::getConfig(Config::CLASSLIBRARY_CONFIG_COOKIE);
	    if($sysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
	        $cookie = \haibao\classlibrary\tv\Config::getConfig(\haibao\classlibrary\tv\Config::TV_CONFIG_TVCOOKIE);
	    }
		$sessionId = $cookie['cookie_key'];
		$cache = new \haibao\user\cache\User();
		if($sysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
		    $cache->delSession($sessionId,true);
		}else{
		    $cache->delSession($sessionId);
		}
		Cookie::remove($cookie['cookie_key'], $cookie['cookie_path'], $cookie['cookie_domain']);
		if($sysAppType != \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
		    Cookie::remove($cookie['old_cookie_key'], $cookie['cookie_path'], $cookie['cookie_domain']);
		}
		Cookie::remove('unionId', $cookie['cookie_path'], $cookie['cookie_domain']);
		Cookie::remove('unionIdLogin', $cookie['cookie_path'], $cookie['cookie_domain']);
		self::delVisitorCookie($sysAppType);
		if( extension_loaded('tokyo_tyrant') ){
		    $ttserver = Config::getConfig(Config::CLASSLIBRARY_CONFIG_TTSERVER);
			$tt = new \TokyoTyrantTable($ttserver['ttserver_host'], $ttserver['ttserver_port']);
			$tt->out($sessionId);
		}
	}
	
	private static function delVisitorCookie($sysAppType = 1){
	    $cookie = Config::getConfig(Config::CLASSLIBRARY_CONFIG_COOKIE);
	    if($sysAppType == \haibao\user\model\data\UserLogin::SYS_APP_TYPE_TV){
	        $cookie = \haibao\classlibrary\tv\Config::getConfig(\haibao\classlibrary\tv\Config::TV_CONFIG_TVCOOKIE);
	    }
		$key = $cookie['cookie_key'].'_visitor_id';
		Cookie::set($key, null, time()-1, $cookie['cookie_path'], $cookie['cookie_domain']);
	}
}
