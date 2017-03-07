<?php
/**
 * @file $HeadURL: register.php $
 * @author $Author: LinLin (linlin@haibao.com) $
 * @date $Date: 2015-4-1 下午1:55:57 $
 * @brief 
 */
namespace haibao\www\sites\web\ajax;

use \haibao\frame\http\Request;
use haibao\www\Config;
use haibao\user\business\User as UserBusiness ;

class User extends \haibao\www\sites\web\ajax\Base{
	const HAIBAOBIND = 1;
	/**
	 * 手机注册（保存到临时用户表中）
	 */
	public function regByMobile(){		
		$userLoginModel = new \haibao\user\model\data\UserLogin();
		$userLoginModel->LoginName = Request::post('mobile');
		$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE;
		$userLoginModel->Password = Request::post('password');
		$userLoginModel->RepPassword = Request::post('password2');
		$userLoginModel->SysAppType = \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC;
		$smsCaptcha = Request::post('sms_captcha');
		$webCaptcha = Request::post('captcha');
		$source = Request::post('source');
		if($source){
			$userId = \haibao\www\sites\common\Auth::getUserIdByCookie();
			$userLoginModel->UserId = $userId;
			\haibao\user\business\User::fastRegsterBind($userLoginModel,$smsCaptcha,$webCaptcha);
		}else{
			\haibao\user\business\User::saveUserByMobile($userLoginModel,$smsCaptcha,$webCaptcha);
		}
		$this->response($userLoginModel->UserId);
		
	}
	public function pcRegByMobile(){
		$source = false;
		if(Request::post('source')){
			$source =  true;   //来源为第三方
		}
		$userLoginModel = new \haibao\user\model\data\UserLogin();
		if(\haibao\www\sites\common\Auth::isLogin() && !$source){
			$userId = \haibao\www\sites\common\Auth::getUserIdByCookie();
			$data = array(
					'code' => 1,
					'forward' => \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HOST_WAP),
			);
		}else{
			$userLoginModel->LoginName = Request::post('mobile');
			if(Request::post('password')){
				$userLoginModel->Password = Request::post('password');
			}
			$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE;
			$userLoginModel->SysAppType = \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC;
			$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_USER;
			$smsCaptcha = Request::post('sms_captcha');
			$webCaptcha = '';
			if(Request::post('captcha')){
				$webCaptcha = Request::post('captcha');
			}
			\haibao\user\business\User::mobileFastLogin($userLoginModel,$smsCaptcha,$webCaptcha);
			$redirect = !empty($_COOKIE['forward']) ? rawurldecode($_COOKIE['forward']) : \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HOST_WAP);
			$data = array(
					'code' => 1,
					'forward' => $redirect
			);
			if($source){
				/* 绑定 */
				$hbUserId = $userLoginModel->UserId;
				$userInfo = \haibao\www\sites\common\Auth::getUserByCookie();
				$userLoginModel = new \haibao\user\model\data\UserLogin();
				$userLoginModel->SysAppType = \haibao\user\model\data\UserLogin::SYS_APP_TYPE_WAP;
				$userLoginModel->LoginNameType = $userInfo['_auth_user_login_type'];
				$userLoginModel->LoginName = $userInfo['_auth_login_name'];
				$userLoginModel->NickName = $userInfo['_auth_nick_name'];
				$userLoginModel->UserName = $userInfo['_auth_user_name'];
				$userLoginModel->UserId = $userInfo['_auth_user_id'];
				$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_WEB_QQ;
				if($userInfo['_auth_user_login_type'] == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIBO){
					$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_WEB_WEIBO;
				}
				\haibao\user\business\User::bindGeneralUser($userLoginModel,$hbUserId);
			}
		}
		$this->response($data);
	}
	
	public function thirdBindUser(){
		if(Request::isPOST()){
			$userInfo = \haibao\www\sites\common\Auth::getUserByCookie();
	
			$userLoginModel = new \haibao\user\model\data\UserLogin();
			$userLoginModel->SysAppType = \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC;
			if(Request::post('type') == self::HAIBAOBIND){
				$userLoginModel->LoginNameType = $userInfo['_auth_user_login_type'];
				$userLoginModel->LoginName = $userInfo['_auth_login_name'];
				$userLoginModel->UserId = $userInfo['_auth_user_id'];
				$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_WEB_QQ;
				if(Request::post('thirdType') == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIBO){
					$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_WEB_WEIBO;
				}elseif (Request::post('thirdType') == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN){
					$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN;
				}
				/*选择海报进行绑定   $userLoginModel 第三方Model  $userId 海报帐号ID*/
				\haibao\user\business\User::bindGeneralUser($userLoginModel,Request::post('userId'));
	
			}elseif (Request::post('type') > self::HAIBAOBIND){
				$userLoginModel->LoginName = Request::post('username');
				$userLoginModel->Password = Request::post('password');
				$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_USER;
				$userLoginModel->UserId = Request::post('userId');
				$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_NAME;
				if(filter_var(strtolower($userLoginModel->LoginName), FILTER_VALIDATE_EMAIL)){
					$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_EMAIL;
				}elseif(preg_match("/^1[3|4|5|7|8][0-9]\d{8,8}$/", $userLoginModel->LoginName)){
					$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE;
				}
				/*选择第三方进行绑定,第三方包括QQ和微博 $userLoginModel 海报Model $userId 第三方用户ID*/
				\haibao\user\business\User::bindThirdUser($userLoginModel, $userInfo['_auth_user_id']);
			}
			$this->response(true);
		}
	}
	
	public function checkCaptchaComment(){
		$responseCode = 0;
	
		if(Request::isPOST()){
			$captcha = Request::post('captcha');
			try{
				$token = \haibao\comment\business\Comments::checkCaptcha($captcha);
				$responseCode = 1;
			}catch(\haibao\comment\business\BusinessException $e){
			}
		}
	
		$this->response($responseCode);
	}
	
	/**
	 * 用户输入数据校验
	 */
	public function validate(){
		if(Request::isPOST()){
			$rule = strtolower( Request::post('rule') );
			$data = Request::post('data');
			
			if($rule == 'email'){
				\haibao\user\business\User::checkEmail($data);
			} elseif ($rule == 'username'){
				\haibao\user\business\User::checkUsername($data);
			} elseif ($rule == 'captcha'){
				\haibao\user\business\User::checkCaptcha($data);
			} elseif ($rule == 'mobile'){
				\haibao\user\business\User::checkMobile($data);
			} elseif ($rule == 'sms_captcha'){
				list($mobile, $captcha) = explode('-', $data);
				\haibao\user\business\User::checkSmsCaptcha($mobile, $captcha);
			}
		}
		$this->response();
	}
	
	/**
	 * 手机是否已经被注册
	 * @return boolean
	 */
	public function isRegMobile(){
		if(Request::isPOST()){
			$mobile = Request::post('mobile');
			$mobileInfo = \haibao\user\business\User::getUserByMobile($mobile);
			$ret = $mobileInfo ? 1 : 0;
			$this->response($ret);
		}
	}
	
	public function bindLogin(){
		$data = '';
		if(Request::isPOST()){
			$data = \haibao\user\business\User::checkUserPassword(Request::post('username'),Request::post('password'));
		}
		return $this->response($data);
	}
	
	/**
	 * 发送手机短信验证码
	 */
	public function sendSmsCaptcha(){
		if(Request::isPOST()){
			$mobile = Request::post('mobile');
			$captcha = Request::post('captcha');
			if(\haibao\user\business\User::checkCaptcha($captcha)){
				\haibao\user\business\User::sendRegSms($mobile);
			}else{
				$this->response(2);
			}
		}
		$this->response();
	}
	
	/**
	 * 发送找回密码手机验证码
	 */
	public function sendBackpwdSms(){
		if(Request::isPOST()){
			$mobile = Request::post('mobile');
			\haibao\user\business\User::sendBackpwdSms($mobile);
		}
		$this->response();
	}
	
	/**
	 * 用户登录
	 */
	public function login(){
		$data = null;
		if(Request::isPOST() && isset($_SERVER['HTTP_USER_AGENT'])){
			// ajax判断当前是否已经登录
			if(\haibao\www\sites\common\Auth::isLogin()){
				$userId = \haibao\www\sites\common\Auth::getUserIdByCookie();
				$data = array(
						'code' => 1,
						'forward' => sprintf(\haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::BBS_SPACE_URL), $userId),
				);
			} else {
				$userLoginModel =  new \haibao\user\model\data\UserLogin();
				$userLoginModel->LoginName = Request::post('username');
				$userLoginModel->Password = Request::post('password');
				$userLoginModel->SysAppType = $userLoginModel::SYS_APP_TYPE_PC;
				$userLoginModel->OpenAppType = $userLoginModel::OPEN_APP_TYPE_USER;
				
				$redirect = !empty($_COOKIE['forward']) ? rawurldecode($_COOKIE['forward']) : \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HAIBAO_URL);
				$ret = \haibao\user\business\User::login($userLoginModel);
				$data = array(
					'code' => 1,
					'forward' => $redirect,
					'sync' => $ret
				);
			}
		}
		$this->response($data);
	}
	
	public function mobileFastlogin(){
		$data = null;
		if(Request::isPOST()){
			$redirect = !empty($_COOKIE['forward']) ? rawurldecode($_COOKIE['forward']) : \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HAIBAO_URL);
			if(\haibao\www\sites\common\Auth::isLogin()){
				$userId = \haibao\www\sites\common\Auth::getUserIdByCookie();
			} else {
				$userLoginModel =  new \haibao\user\model\data\UserLogin();
				$userLoginModel->LoginName = Request::post('mobile');
				$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE;
				$userLoginModel->SysAppType = \haibao\user\model\data\UserLogin::SYS_APP_TYPE_PC;
				$userLoginModel->OpenAppType = \haibao\user\model\data\UserLogin::OPEN_APP_TYPE_USER;
				$smsCaptcha = Request::post('smsCaptcha');
			    $webCaptcha = Request::post('randomcaptcha');
				\haibao\user\business\User::mobileFastLogin($userLoginModel,$smsCaptcha,$webCaptcha);
			}
			$data = array(
					'code' => 1,
					'forward' => $redirect,
			);
		}
		$this->response($data);
	}
	
	/**
	 * ajax检查当前登录状态
	 */
	public function checkLogin(){
		$userInfo = \haibao\www\sites\common\Auth::getUserByCookie();
		
		// 为了兼容旧系统的各种登录，变量改成跟之前一样的方式
		if($userInfo){
			$sso = 'var sso_user_id = "' . $userInfo['_auth_user_id'] . '",';
			$sso .= 'sso_user_avator = "' . $userInfo['_auth_user_avator'] . '",';
			$sso .= 'sso_user_avator_b = \'<a href="/user/'.$userInfo['_auth_user_id'].'/" class="user_link" title="去到'.$userInfo['_auth_user_name'].'的个人空间" name="'.$userInfo['_auth_user_id'].'" target="_blank"><img src="'.$userInfo['_auth_user_avator'].'" width="20" height="20" alt=""/></a>\',';
			$sso .= 'sso_user_nickname = "' . $userInfo['_auth_nick_name'] . '",';
			$sso .= 'sso_user_name = "' . $userInfo['_auth_user_name'] . '",';
			$sso .= 'sso_user_login_type = "' . $userInfo['_auth_user_login_type'] . '",';
			$sso .= 'sso_user_i = 0,';
			$sso .= 'sso_unreg_user_id=0;';
		}else{
			
			$userInfo = \haibao\www\sites\common\Auth::getVisitorInfoByCookie();
			if($userInfo){
				$sso = 'var sso_user_id = 0, sso_user_avator = "", sso_user_avator_b = "", sso_user_nickname = "", sso_user_name = "", sso_user_login_type="", sso_user_i = 0, sso_unreg_user_id="' . $userInfo['_auth_user_id'] . '";';
			}else{
				$sso = 'var sso_user_id = 0, sso_user_avator = "", sso_user_avator_b = "", sso_user_nickname = "", sso_user_name = "", sso_user_login_type="", sso_user_i = 0, sso_unreg_user_id=0;';
			}
		}
		header('Content-type: application/x-javascript');
		$this->response($sso);
	}
	
	/**
	 * 找回密码确认用户
	 */
	public function confirmUser(){
		$data = null;
		if(Request::isPOST()){
			$hbuser = Request::post('hbuser');
			$captcha = Request::post('captcha');
			$isSendMail = Request::post('issendmail');
			$smscaptcha = Request::post('smscaptcha');
			if(\haibao\user\business\User::isMobile($hbuser)){
				\haibao\user\business\User::checkCaptcha($captcha);
				$token = \haibao\user\business\User::verifyMobile($hbuser, $smscaptcha);
				$data = array('token' => $token);
			} elseif (\haibao\user\business\User::isEmail($hbuser)){
				\haibao\user\business\User::backPwd($hbuser, $captcha, $isSendMail);
			}
		}
		$this->response($data);
	}
	
	/**
	 * 手机找回密码发送短信
	 */
	public function sendBackpwdCaptcha(){
		$data = null;
		if(Request::isPOST()){
			$mobile = Request::post('mobile');
			\haibao\user\business\User::sendBackpwdSms($mobile);
		}
		$this->response($data);
	}
	
	/**
	 * 快速登录短信验证
	 */
	public function sendSmsCaptchaForLogin(){
		if(Request::isPOST()){
			$mobile = Request::post('mobile');
			\haibao\user\business\User::sendLoginSms($mobile);
		}
		$this->response();
	}
	
	/**
	 * 手机找回密码确认验证码
	 */
	public function confirmMobile(){
		$data = null;
		if(Request::isPOST()){
			$mobile = Request::post('mobile');
			$smsCaptcha = Request::post('sms_captcha');
			$token = \haibao\user\business\User::verifyMobile($mobile, $smsCaptcha);
			$data = array('token' => $token);
		}
		$this->response($data);
	}
	
	/**
	 * 重置密码
	 */
	public function resetPwd(){
		if(Request::isPOST()){
			$token = Request::post('token');
			$password = Request::post('password');
			$password2 = Request::post('password2');
			\haibao\user\business\User::resetPassword($token, $password, $password2);
		}
		$this->response();
	}
	
	/**
	 * 弹出层登录
	 */
	public function quickLoginForm(){
		$template = new \haibao\frame\http\Template();
		$domainHost = \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_CDN_DOMAIN);
		if(\haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_CDN_DEFAULT)){
			$domainHost = \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_CDN_DOMAIN);
		}
		
		$forward = Request::get('forward');
		if( !empty($forward) ){
		    $goUrl = rawurldecode(trim( $forward ));
		}elseif( isset($_SERVER['HTTP_REFERER']) && !strpos($_SERVER['HTTP_REFERER'], 'reset.html?token') ){
		    $goUrl = $_SERVER['HTTP_REFERER'];
		}else{
		    $goUrl = \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HAIBAO_URL);
		}
		
		if($goUrl){
		    setcookie('forward', null, time()-3600, Config::$cookie['cookie_path'], Config::$cookie['cookie_domain']);
		    setcookie('forward', rawurlencode($goUrl), null, Config::$cookie['cookie_path'], Config::$cookie['cookie_domain']);
		}
		
		$template->assign('staticHost', $domainHost);
		$template->assign('wwwHost', \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HAIBAO_URL));
		$template->assign('userHost', \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_HOST_USER));
		$this->response($template->getHtml('/user/_quick_login.html'));
	}
	
	/**
	 * 海报帐号升级
	 */
	public function confirm(){
	    if(Request::isPOST()){
	        $tourist = \haibao\comment\common\Auth::getUnRegUserId();
	    	\haibao\user\business\User::checkTourist($tourist);
	    	$userLoginModel = new \haibao\user\model\data\UserLogin();
			$userLoginModel->LoginName = Request::post('username');
			$userLoginModel->Password = Request::post('password');
			$userLoginModel->UserName = Request::post('username');
			$userLoginModel->RepPassword = Request::post('confirm');
			$userLoginModel->LoginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_NAME;
			$userId = \haibao\user\business\User::saveUserByUsername($userLoginModel);
	        \haibao\user\business\User::setTouristCookie($tourist);
	        if($userId){
	        	\haibao\www\business\message\MessageUser::updateMessageUser($userId,\haibao\www\model\data\message\MessageBox::MESSAGE_TYPE_SYSTEM);
	        	$userInfo['_auth_user_id'] = $userId;
	        	\haibao\www\business\message\MessageUser::addMessageInfoForSystem($userInfo,$messageContent = '感谢您注册成为海报网正式用户，为了账号安全和方便您忘记密码时找回，建议您<span class="name"><a href="javascript:;" id="jsPerfectUser">补充完善个人信息</a></span>，邮箱或手机号均可。',false);
	        }
	    }
	    $this->response();
	}
	
	public function searchMessageList(){
	    $data = '';
	    if (Request::isPOST()){
	        $control = new \haibao\www\sites\web\view\control\user\Message();
	        $control->setParameter('conditions', $_POST);
	        if(Request::post('type') == \haibao\www\sites\web\view\user\Message::TYPE_COMMENT_AND_PRAISE){
    	        $templateFile = '/control/user/message/comment.html';
	        }elseif(Request::post('type') == \haibao\www\sites\web\view\user\Message::TYPE_FRIEND_DYNAMIC){
	            $templateFile = '/control/user/message/frienddynamic.html';
	        }elseif(Request::post('type') == \haibao\www\sites\web\view\user\Message::TYPE_SYSTEM_MESSAGE){
	            $templateFile = '/control/user/message/system.html';
	        }
            $data = $control->getHtml($templateFile);
	    }
	    
	    $this->response($data);
	}
	
	public function getVipUser(){
		$data = null;
		if(Request::isPOST()){
			$userId = Request::post('userid');
			$userVip = new \haibao\user\business\payment\web\UserVip();
			$data = $userVip->getUserVipInfo($userId);
		}
		$this->response($data);
	}
}