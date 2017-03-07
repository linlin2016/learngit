<?php

namespace haibao\user\data;
use \haibao\user\cache\User as UserCache;
class User extends \haibao\user\data\BaseMysql{
    
	const USER_INTEGRAL_LOGIN = 1;
	const USER_INTEGRAL_SIGN = 2;
	const USER_INTEGRAL_COMMENT = 3;
	public function __construct(){
		parent::__construct('\haibao\user\model\data\UserReg');
		$this->setConfig( \haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_MYSQL_HAIBAO_USER );
	}
	
	/**
	 * 得用户注册信息
	 * @param int $userId
	 */
	public function getUserInfo($userId){
		return $this->dbUser()->getOneById($userId);
	}
	

	/**
	 * 通过用户ID得用户注册信息
	 * @param unknown_type $Ids
	 * @return array
	 */
	public function getUsersByIds($userIds){
		$str = preg_replace('/(\d+)/', '%s', implode(',', $userIds));
		$sql = "SELECT * from UserReg where Id in (".$str.")";
		$result = $this->query($sql, $userIds);
		$returnUserName = array();
		while ($row = $result->fetch_assoc()){
			$returnUserName[$row['Id']] = $row;
	
		}
		return $returnUserName;
	}

	/**
	 * 得与用户名相似的用户信息
	 * @param unknown_type $userName
	 * @return array
	 */
	public function getUserListByUserName($userName){
		$sql = "SELECT * from UserReg where UserName like %s";
		$result = $this->query($sql, array('%'.$userName.'%'));
			
		$userNameList = array();
		while ($list = $result->fetch_assoc()){
			$userNameList[$list['id']] = $list;
		}
		return $userNameList;
	}
	
	/**
	 * 通过用户Id查询用户密码
	 */
	public function getPasswordByUserId($userId){
	    $sql = 'SELECT l.Password from UserReg r LEFT JOIN UserLogin l on r.Id=l.UserId where l.UserId=%s limit 1';
	    $result = $this->dbUser()->query($sql, array($userId));
	    $userInfo = $result->fetch_assoc();
	    return $userInfo['Password'];
	}
	
	/**
	 * 通过登录名查询用户信息
	 * @param string $loginName
	 * @param string $loginNameType
	 */
	public function getUserByLoginName($loginName,$loginNameType = \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE){
		$sql = "SELECT r.*,l.Id as UserLoginId from UserReg r LEFT JOIN UserLogin l on r.Id=l.UserId where l.LoginName=%s and l.LoginNameType=%s order by IsBind desc limit 1";
		$result = $this->dbUser()->query($sql, array($loginName,$loginNameType));
		$userInfo = $result->fetch_assoc();
		return $userInfo;
	}
	
	/**
	 * 通过昵称得用户ID
	 * @param string $nickName
	 */
	public function getUserIdByNickName($nickName){
		$userId = 0;
		$sql = "select Id from UserReg where NickName=%s limit 1";
		$result = $this->dbUser()->query($sql, array($nickName));
		$userInfo = $result->fetch_assoc();
		if(!empty($userInfo)){
			$userId = $userInfo['Id'];
		}
		
		return $userId;
	}
	
	/**
	 * 得用户所有登录帐号
	 * @param int $userId
	 */
	public function getUserLoginType($userId){
		$sql = 'select l.Id,l.UserId,l.LoginName,r.Mobile,r.NickName,l.LoginNameType,l.SysAppType,l.OpenAppType,l.IsBind,l.CreateTime from UserLogin as l LEFT JOIN UserReg r ON l.UserId=r.Id where l.UserId=%s';
		$result =  $this->dbUser()->query($sql,array($userId));
		$userLoginList = array();
		while ($list = $result->fetch_assoc()){
			$userLoginList[$list['LoginNameType']] = $list;
		}
		return $userLoginList;
	}
	
	/**  
	 * 更新用户绑定状态
	 */
	public function updateBindStatus($userId,$isBind = \haibao\user\model\data\UserLogin::IS_BIND_YES){
		$sql = "update UserLogin set IsBind=%s where UserId=%s";
		$this->dbUser()->query($sql,array($isBind,$userId));
	}
	
	/**
	 * 更新微信公众平台绑定用户Id
	 * @param $loginName  UnionId;
	 * @param $userId     原绑定UserId
	 * @param $bindUserId 新的绑定UserId
	 */
	public function updateUserIdByLoginName($loginName,$userId,$bindUserId){
		$sql = "update UserLogin set UserId=%s where UserId=%s and LoginName=%s and IsBind=%s";
		$this->dbUser()->query($sql,array($bindUserId,$userId,$loginName,\haibao\user\model\data\UserLogin::IS_BIND_YES));
	}
	
	public function updateUserLoginName($userId,$loginName,$loginNameType){
		$sql = "update UserLogin set LoginName=%s where UserId=%s and LoginNameType=%s order by IsBind desc limit 1";
		$this->dbUser()->query($sql,array($loginName,$userId,$loginNameType));
	}
	
	/**
	 * 根据loginName(UnionId/openId) 获取绑定用户的绑定状态
	 */
	public function getUserInfoByLoginName($loginName, $loginNameType){
		$sql = "select UserId,IsBind,LoginName from UserLogin where LoginName=%s and LoginNameType=%s order by IsBind desc limit 1";
		$result = $this->dbUser()->query($sql, array($loginName, $loginNameType));
		$userInfo = $result->fetch_assoc();
		
		return $userInfo;
	}
	
	/**
	 * 微信公众平台用户绑定海报帐号
	 */
	public function addWeixinBindUser($model){
		$this->add($model);
		$unionId = $model->LoginName;
		$userId = $model->UserId;
		$cache = new \haibao\user\cache\User();
		$cache->setWeixinBindUid($unionId, $userId);
	}
	
	/**
	 * 用户登录
	 * @param \haibao\user\model\data\UserLogin $userLoginModel		用户登录实体
	 */
	public function userLogin($userLoginModel){
		$sql = "SELECT r.*,l.Password,l.Id as UserLoginId,l.IsBind from UserReg r LEFT JOIN UserLogin l on r.Id=l.UserId where l.LoginName=%s order by IsBind desc limit 1";
		$result = $this->dbUser()->query($sql, array($userLoginModel->LoginName));
		$userInfo = $result->fetch_assoc();
		
		if(!empty($userInfo)){
			if(!$this->checkPassword($userLoginModel->Password, $userInfo['Password'])){
				return null;
			}
			$userLoginModel->UserId = $userInfo['Id'];
			$userLoginModel->Id = $userInfo['UserLoginId'];
			$this->addUserLoginHistory($userLoginModel);
		}
		
		return $userInfo;
	}
	
	public function checkUserPassword($loginName,$password,$type=null){
		$userId = null;
		if($type){
			$sql = "SELECT UserId,Password from UserLogin where LoginName=%s and LoginNameType=%s order by IsBind desc limit 1";
			$result = $this->dbUser()->query($sql, array($loginName,$type));
		}else{
			$sql = "SELECT UserId,Password from UserLogin where LoginName=%s order by IsBind desc limit 1";
			$result = $this->dbUser()->query($sql, array($loginName));
		}
		$userInfo = $result->fetch_assoc();
		if(!empty($userInfo)){
			$result = $this->checkPassword($password, $userInfo['Password']);
			if($result){
				$userId = $userInfo['UserId'];
			}
		}
		return $userId;
	}
	
	/**
	 * 通过用户ID得头像md5值
	 * @param unknown_type $userId
	 */
	public function getUserAvatarMd5($userId){
		$cache = new \haibao\user\cache\User();
		$cachePhotoMd5 = $cache->getUserPhotoMd5($userId);
		if(empty($cachePhotoMd5)){
			$cachePhotoMd5 = '';
			$userInfo = $this->getUserInfo($userId);
			if(!empty($userInfo) && !empty($userInfo->AvatarMd5)){
				$cachePhotoMd5 = $userInfo->AvatarMd5;
				$cache->setUserPhotoMd5($userId, $cachePhotoMd5);
			}
		}
			
		return $cachePhotoMd5;
	}
	
	/**
	 * 通过用户ID得用户昵称
	 * @param array $userIdArr
	 * @return array()
	 */
	public function getNicknameByUserIdArr(array $userIdArr){
		if(!$userIdArr){
			return false;
		}
		$retArr = array();
		$str = preg_replace('/(\d+)/', '%s', implode(',', $userIdArr));
		$sql = "select Id,NickName from UserReg where Id in(".$str.')';
		$result = $this->dbUser()->query($sql, $userIdArr);
		while($result->fetch_assoc()){
            $retArr[$result->getData('Id')] = $result->getData('NickName');
        }
		return $retArr;
	}
	
	/**
	 * 得第三方登录用户ID
	 * @param string $openId
	 * @param int $loginNameType
	 * @param int $openAppType
	 * @return int
	 */
	public function getUserIdByOpenId($openId,$loginNameType,$openAppType){
		$sql = "select Id,UserId,IsBind from UserLogin where LoginName=%s and LoginNameType=%s order by IsBind desc limit 1";
		$result = $this->dbUser()->query($sql, array($openId,$loginNameType));
		return $result->fetch_assoc();
	}
	
	/**
	 * 保存用户注册信息
	 * @param \haibao\user\model\data\UserLogin $userLoginModel
	 * @param string $openId	第三方登录OpenId
	 */
	public function saveUser($userLoginModel,$openId = null){
	    try {
	        $regModel = new \haibao\user\model\data\UserReg();
        	$userLoginModel->LastLoginTime = new \DateTime();
        	$userLoginModel->CreateTime = new \DateTime();
        	$userLoginModel->IsActive = \haibao\user\model\data\UserLogin::IS_ACTIVE_YES;
        	$userLoginModel->IsBind = \haibao\user\model\data\UserLogin::IS_BIND_NO;
        	
            $regModel->Gender = \haibao\user\model\data\UserReg::Gender_TYPE_UN;
            if(!empty($userLoginModel->Gender)){
            	$regModel->Gender = $userLoginModel->Gender;
            }
            $regModel->IsActive = $userLoginModel->IsActive;
            $regModel->CreateTime = new \DateTime();
            if($userLoginModel->LoginNameType == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE){
            	$regModel->Mobile = $userLoginModel->LoginName;
            	if(!empty($userLoginModel->NickName)){
            		$regModel->NickName = $userLoginModel->NickName;
            	}
            } elseif($userLoginModel->LoginNameType == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_NAME){
            	$regModel->UserName = $regModel->NickName = $userLoginModel->LoginName;
            	if(!empty($userLoginModel->NickName)){
            		$regModel->NickName = $userLoginModel->NickName;
            	}
            } elseif(in_array($userLoginModel->LoginNameType, array(\haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_QQ,\haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIBO,\haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN)) && !empty($userLoginModel->NickName)){
            	$regModel->UserName = $regModel->NickName = $userLoginModel->NickName;
            }
            
            $this->dbUser()->add($regModel);
          
            $userLoginModel->UserId = $regModel->Id;
            
            if($userLoginModel->LoginNameType == \haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_MOBILE || (in_array($userLoginModel->LoginNameType, array(\haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_QQ,\haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIBO,\haibao\user\model\data\UserLogin::LOGIN_NAME_TYPE_WEIXIN)) && empty($userLoginModel->NickName))){
                $regModel->UserName = $userLoginModel->UserName = '报友'.$regModel->Id;
                if(empty($regModel->NickName)){
                	$userLoginModel->NickName = $regModel->NickName = $regModel->UserName;
                }
                $this->setReadPerformance(true);
                $this->updateById($regModel->Id, array('UserName'=>$userLoginModel->UserName, 'NickName'=>$regModel->NickName));
            }
            
            $this->add($userLoginModel);
            
            $userProfileModel = new \haibao\user\model\data\UserProfiles();
            $userProfileModel->Birth = \haibao\user\model\data\UserProfiles::BIRTH_TYPE_DEFAULT;
            $userProfileModel->ReaderCount = 0;
            $userProfileModel->UpdateTime = new \DateTime();
            $userProfileModel->UserId = $regModel->Id;
            $this->add($userProfileModel);

            $this->addUserLoginHistory($userLoginModel,$openId,false);
	        
	        $this->addUcenterData($regModel, $userLoginModel);
	        
	        //添加用户查看消息日志
	        //$data = new \haibao\app\data\Account();
	        //$data->addUserMessageLog($regModel->Id);
	        return $regModel->Id;
	        
	    } catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
	}
	
	/**
	 * 添加用户登录记录
	 * @param \haibao\user\model\data\UserLogin $userModel
	 * @param string $openId	第三方登录OpenId
	 * @param bool $isLogin	是否登录操作调用
	 */
	public function addUserLoginHistory($userModel,$openId = null,$isLogin = true){
		$userIp = $this->getIPaddress();
		$userLoginHistory = new \haibao\user\model\data\UserLoginHistory();
		$userLoginHistory->UserLoginId = $userModel->Id;
		$userLoginHistory->SysAppType = $userModel->SysAppType;
		$userLoginHistory->OpenAppType = $userModel->OpenAppType;
		$userLoginHistory->LoginIp = $userIp;
		$userLoginHistory->CreateTime = new \DateTime();
		$userLoginHistory->AppOpenId = $openId;
		$this->dbUser()->add($userLoginHistory);
		
		$lastLoginTime = date('Y-m-d H:i:s');
		if($isLogin){
			$this->query('update UserLogin set LastLoginTime=%s where UserId=%s and LoginNameType=%s', array(
					$lastLoginTime, $userModel->UserId,$userModel->LoginNameType
			));
		}
		$this->addUserIntegral($userModel->UserId,$lastLoginTime,1);
		
	}
	
	/**
	 * 设置用户头像地址
	 * @param unknown_type $userId
	 * @param unknown_type $avatarUrl
	 */
	public function setUserAuater($userId,$avatarUrl){
		$avatarMd5 = md5($avatarUrl);
		$this->dbUser()->query('update UserReg set AvatarUrl=%s,AvatarMd5=%s where Id=%s', array($avatarUrl,$avatarMd5,$userId));
		//修改BBS中的用户是否有头像
		$this->dbDz()->query("update hb_common_member set avatarstatus=1 where uid=%s", array($userId));
	}
	
	/**
	 * 设置用户头像、性别、昵称
	 * @param int $userId	用户ID
	 * @param \haibao\user\model\data\UserReg $userModel
	 * @throws BusinessException
	 */
	public function setUserInfo($userId,$userModel){
		$setString = ' Gender=%s,NickName=%s';
		$setValueArr = array($userModel->Gender,$userModel->NickName);
		if($userModel->AvatarUrl){
			$setString .= ',AvatarUrl=%s,AvatarMd5=%s';
			array_push($setValueArr, $userModel->AvatarUrl);
			array_push($setValueArr, $userModel->AvatarMd5);
		}
		array_push($setValueArr, $userId);
		$this->dbUser()->query('update UserReg set '.$setString.' where Id=%s', $setValueArr);
		
		//修改BBS中的用户性别
		 $this->dbDz()->query("update hb_common_member_profile set gender=%s where uid=%s", array($userModel->Gender, $userId));
		//修改BBS中的用户是否有头像
		if($userModel->AvatarUrl){
			 $this->dbDz()->query("update hb_common_member set avatarstatus=%s where uid=%s", array(1, $userId));
		}
	}
	
	/**
	 * 重置用户密码
	 * @param int $userId
	 * @param string $password
	 */
	public function updateUserPassword($userId, $password, $token = null){
		if($userId && $password){
            $this->dbUser()->query('update UserLogin set Password=%s where UserId=%s', array($password, $userId));
            $this->dbDz()->query('update hb_ucenter_members set password=%s where uid=%s limit 1', array($password, $userId));
		}
        
        if($token){
        	$data = new \haibao\user\cache\User();
        	$data->deleteToken($token);
        }
	}
	
	/**
	 * 走数据库方式发送邮件
	 */
	public function sendMail($subject, $content, $mailto, $from = null){
	    if(!$from){
	        $from = \haibao\classlibrary\www\Config::getConfig(\haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_MAIL_SENDER_ADDRESS);
	    }
		$this->dbDz()->query('insert into z_email_queue(`fromname`, `mailto`, `subject`, `content`, `contenttype`, `addtime`) values(%s,%s,%s,%s,%s,%s)', array(
			$from, $mailto, $subject, $content, 1, date('Y-m-d H:i:s'),
		));
	}
	
	public function unAccountBind($loginId){
		$sql = 'delete from UserLogin where Id=%s';
		$this->dbUser()->query($sql,array($loginId));
	}
	
	/**
	 * 根据token获取用户ID
	 */
	public function getUserIdByToken($token){
		$cache = new \haibao\user\cache\User();
		return $cache->getUserByToken($token);
	}
	
	/**
	 * 根据用户ID获取邮箱手机找回密码的激活码（默认保留3天）
	 */
	public function getToken($userId){
		$token = time().'_'.$userId.'_'.rand(1000, 10000);
		$token = sha1($token);
	
		$cache = new \haibao\user\cache\User();
		$cache->setToken($token, $userId);
		return $token;
	}
	
	/**
	 * 获取手机验证码
	 */
	public function getSmsCaptcha($mobile){
		$cache = new \haibao\user\cache\User();
		$code = $this->generateCode();
		$cache->setSmsCaptcha($mobile, $code, 60*10);
		return $code;
	}
	
	/**
	 * 获取某手机发送验证码次数
	 */
	public function getSendCount($mobile){
		$cache = new \haibao\user\cache\User();
		return $cache->getSmsSendCount($mobile);
	}
	
	/**
	 * 发送次数加1
	 */
	public function setSendCount($mobile, $ip){
		$cache = new \haibao\user\cache\User();
		$cache->setSmsSendCount($mobile, $ip);
	}
	
	private function updateAuthUserLastLoginTime($userId){
		$userIp = $this->getIPaddress();
		$data = new \haibao\user\data\authuser();
		$data->updateLastLoginTime($userId, $userIp);
	}
	
	/**
	 * 生成6位随机数字
	 */
	private function generateCode($length = 6){
		return rand(pow(10,($length-1)), pow(10,$length)-1);
	}
	
	/**
	 * 添加ucenter注册用户初始信息
	 * @param unknown $model
	 */
	private function addUcenterData($regModel, $loginModel){
	    $userId = $regModel->Id;
	    $userName = $regModel->UserName;
	    $password = $loginModel->Password;
	    $email = $regModel->Email ? $regModel->Email : '';
	    $mobile = $regModel->Mobile ? $regModel->Mobile : '';
		$groupId = $loginModel->GroupId ?  $loginModel->GroupId : 11;
	    
	    $emailStatus = 1;
	    $avatarStatus= 0;
	    $timeNow = time();
	    
	    $sql = 'replace into hb_ucenter_members(uid,username,`password`,email,regdate) values(%s,%s,%s,%s,%s)';
	    $this->dbDz()->query($sql, array(
	        $userId, $userName, $password, $email, $timeNow
	    ));
	    
	    $sql = 'replace into hb_common_member(uid,email,username,`password`,groupid,emailstatus,avatarstatus,regdate,credits) values(%s,%s,%s,%s,%s,%s,%s,%s,%s)';
	    $this->dbDz()->query($sql, array(
	        $userId, $email, $userName, $password, $groupId, $emailStatus, $avatarStatus, $timeNow, '0',
	    ));
	    
	    $sql = 'replace into hb_common_member_count(uid,extcredits1,extcredits2) values(%s,%s,%s)';
	    $this->dbDz()->query($sql, array(
	        $userId, '0', '0',
	    ));
	    
	    $sql = 'replace into hb_common_member_status(uid) values(%s)';
	    $this->dbDz()->query($sql, array($userId));
	    
	    $sql ='replace into hb_common_member_profile(uid,mobile) values(%s,%s)';
	    $this->dbDz()->query($sql, array($userId,$mobile));
	    
	    $blockpostion = addslashes('a:3:{s:5:"block";a:1:{s:12:"frame`frame1";a:3:{s:4:"attr";a:4:{s:4:"name";s:6:"frame1";s:8:"moveable";s:5:"false";s:9:"className";s:8:"frame cl";s:6:"titles";s:0:"";}s:18:"column`frame1_left";a:3:{s:4:"attr";a:2:{s:4:"name";s:11:"frame1_left";s:9:"className";s:8:"z column";}s:13:"block`profile";a:1:{s:4:"attr";a:3:{s:4:"name";s:7:"profile";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:6:"头像";s:4:"href";s:63:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=profile";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:13:"block`visitor";a:1:{s:4:"attr";a:3:{s:4:"name";s:7:"visitor";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:12:"最近访客";s:4:"href";s:75:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=friend&view=visitor";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}}s:20:"column`frame1_center";a:10:{s:4:"attr";a:2:{s:4:"name";s:13:"frame1_center";s:9:"className";s:8:"z column";}s:11:"block`album";a:1:{s:4:"attr";a:3:{s:4:"name";s:5:"album";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:6:"相册";s:4:"href";s:80:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=album&view=me&from=space";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:12:"block`block3";a:1:{s:4:"attr";a:3:{s:4:"name";s:6:"block3";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:12:"迷你博客";s:4:"href";s:0:"";s:5:"color";s:0:"";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:10:"block`feed";a:1:{s:4:"attr";a:3:{s:4:"name";s:4:"feed";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:12:"小道消息";s:4:"href";s:79:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=home&view=me&from=space";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:10:"block`blog";a:1:{s:4:"attr";a:3:{s:4:"name";s:4:"blog";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:6:"日志";s:4:"href";s:79:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=blog&view=me&from=space";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:12:"block`block1";a:1:{s:4:"attr";a:3:{s:4:"name";s:6:"block1";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:18:"我喜欢的明星";s:4:"href";s:0:"";s:5:"color";s:0:"";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:12:"block`block2";a:1:{s:4:"attr";a:3:{s:4:"name";s:6:"block2";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:18:"我喜欢的品牌";s:4:"href";s:0:"";s:5:"color";s:0:"";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:12:"block`thread";a:1:{s:4:"attr";a:3:{s:4:"name";s:6:"thread";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:12:"最新帖子";s:4:"href";s:81:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=thread&view=me&from=space";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:12:"block`friend";a:1:{s:4:"attr";a:3:{s:4:"name";s:6:"friend";s:9:"className";s:15:"block move-span";s:6:"titles";a:3:{i:0;a:8:{s:4:"text";s:15:"我的好盆友";s:4:"href";s:81:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=friend&view=me&from=space";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}s:10:"block`wall";a:1:{s:4:"attr";a:3:{s:4:"name";s:4:"wall";s:9:"className";s:15:"block move-span";s:6:"titles";a:4:{i:0;a:8:{s:4:"text";s:9:"留言板";s:4:"href";s:60:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=wall";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:0:"";s:3:"src";s:0:"";}i:1;a:8:{s:4:"text";s:6:"全部";s:4:"href";s:60:"http://bbs.haibao.com/home.php?mod=space&uid=1170642&do=wall";s:5:"color";s:11:" !important";s:5:"float";s:0:"";s:6:"margin";s:0:"";s:9:"font-size";s:0:"";s:9:"className";s:5:"y xw0";s:3:"src";s:0:"";}s:9:"className";a:1:{i:0;s:16:"blocktitle title";}s:5:"style";s:0:"";}}}}}}s:13:"currentlayout";s:3:"1:3";s:10:"parameters";a:9:{s:6:"friend";a:2:{s:5:"title";s:15:"我的好盆友";s:7:"shownum";i:10;}s:5:"music";a:3:{s:7:"mp3list";a:2:{i:0;a:3:{s:6:"mp3url";s:43:"http://stream15.qqmusic.qq.com/35036372.mp3";s:7:"mp3name";s:15:"爸爸去哪儿";s:4:"cdbj";s:0:"";}i:1;a:3:{s:6:"mp3url";s:43:"http://stream13.qqmusic.qq.com/30815558.mp3";s:7:"mp3name";s:13:"I Remember Me";s:4:"cdbj";s:0:"";}}s:6:"config";a:8:{s:7:"showmod";s:7:"default";s:7:"autorun";s:4:"true";s:7:"shuffle";s:4:"true";s:12:"crontabcolor";s:7:"#D2FF8C";s:11:"buttoncolor";s:7:"#1F43FF";s:9:"fontcolor";s:7:"#1F43FF";s:9:"crontabbj";s:0:"";s:6:"height";i:100;}s:5:"title";s:9:"音乐盒";}s:6:"block1";a:2:{s:5:"title";s:18:"我喜欢的明星";s:7:"content";s:0:"";}s:6:"block2";a:2:{s:5:"title";s:18:"我喜欢的品牌";s:7:"content";s:0:"";}s:4:"feed";a:2:{s:5:"title";s:12:"小道消息";s:7:"shownum";i:10;}s:6:"block3";a:2:{s:5:"title";s:12:"迷你博客";s:7:"content";s:0:"";}s:6:"block4";a:2:{s:5:"title";s:18:"迷你博客广播";s:7:"content";s:0:"";}s:6:"block5";a:2:{s:5:"title";s:12:"最新帖子";s:7:"content";s:0:"";}s:4:"blog";a:3:{s:5:"title";s:6:"日志";s:7:"shownum";i:6;s:11:"showmessage";i:150;}}}');
	    $sql = 'replace into hb_common_member_field_home(uid,blockposition) values(%s,%s)';
	    $this->dbDz()->query($sql, array(
	        $userId, $blockpostion
	    ));
	    
	    $sql = 'replace into hb_common_member_field_forum(uid,customshow,groupterms) values(%s,%s,%s)';
	    $this->dbDz()->query($sql, array(
	        $userId, '26', 'a:0:{}'
	    ));
	}
	
	private function dbDz(){
	    $this->setConfig( \haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_MYSQL_HAIBAO_DZ3 );
	    return $this;
	}
	
	private function dbUser(){
	    $this->setConfig( \haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_MYSQL_HAIBAO_USER );
	    return $this;
	}
	
	private function getIPaddress(){
		$ip='';
		if (isset($_SERVER)){
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
				$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
			} else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
				$ip = $_SERVER["HTTP_CLIENT_IP"];
			} else {
				$ip = $_SERVER["REMOTE_ADDR"];
			}
		} else {
			if (getenv("HTTP_X_FORWARDED_FOR")){
				$ip = getenv("HTTP_X_FORWARDED_FOR");
			} else if (getenv("HTTP_CLIENT_IP")) {
				$ip = getenv("HTTP_CLIENT_IP");
			} else {
				$ip = getenv("REMOTE_ADDR");
			}
		}
		return $ip;
	}
	
	private function checkPassword($password, $dbPassword){
	    $pwd_prefix = explode('$', $dbPassword);
	    $password = $pwd_prefix[1] . $password;
	    $password = 'sha1$' . $pwd_prefix[1] . '$' . sha1($password);
	    return $password == $dbPassword;
	}
	
	/**
	 * 给用户添加积分
	 * @param : Integer $userId 用户ID
	 * @param : DateTime $lastTime 上一个时间节点
	 * @param : Integer $integralType 1 表示登录 2表示签到 3 评论
	 */
	public function addUserIntegral($userId,$lastTime,$integralType = self::USER_INTEGRAL_LOGIN){
		$userIntegral = 0;
		$isSetCache = false;
		if($integralType == self::USER_INTEGRAL_COMMENT){
			$userIntegral = 1;
		}else{
			$userCache = new UserCache();
			$userIntegralCache = $userCache->getUserIntegral($userId);
			if($userIntegralCache){
				
				$userCacheData = json_decode($userIntegralCache,true);
				if($integralType == 1){
					if(isset($userCacheData['LastLoginTime'])){
						$lastLoginTime = $userCacheData['LastLoginTime'];
						$date = date('Y-m-d',strtotime('-1 days'));
						if($date == date('Y-m-d',strtotime($lastLoginTime))){
							$isSetCache = true;
							$userCacheData['LastLoginTime'] = $lastTime;
							$userCacheData['ContinuityLogin'] = $userCacheData['ContinuityLogin'] + 1;
						}elseif(date('Y-m-d') != date('Y-m-d',strtotime($lastLoginTime))){
							$isSetCache = true;
							$userCacheData['LastLoginTime'] = $lastTime;
							$userCacheData['ContinuityLogin'] = 1;
						}
					}else{
						$isSetCache = true;
						$userCacheData['LastLoginTime'] = $lastTime;
						$userCacheData['ContinuityLogin'] = 1;
					}
				}else{
					if(isset($userCacheData['LastSignTime'])){
						$lastSignTime = $userCacheData['LastSignTime'];
						$date = date('Y-m-d',strtotime('-1 days'));
						if($date == date('Y-m-d',strtotime($lastSignTime))){
							$isSetCache = true;
							$userCacheData['LastSignTime'] = $lastTime;
							$userCacheData['ContinuitySign'] = $userCacheData['ContinuitySign'] + 1;
						}elseif(date('Y-m-d') != date('Y-m-d',strtotime($lastSignTime))){
							$isSetCache = true;
							$userCacheData['LastSignTime'] = $lastTime;
							$userCacheData['ContinuitySign'] = 1;
						}
					}else{
						$isSetCache = true;
						$userCacheData['LastSignTime'] = $lastTime;
						$userCacheData['ContinuitySign'] = 1;
					}
					
				}
				
			}else{
				$isSetCache = true;
				if($integralType == 1){
					$userCacheData = array('ContinuityLogin'=>1,'LastLoginTime'=>$lastTime);
				}else{
					$userCacheData = array('ContinuitySign'=>1,'LastSignTime'=>$lastTime);
				}
			}
			if($isSetCache){
				$userCache->setUserIntegral($userId,json_encode($userCacheData));
				if($integralType == 1){
					if($userCacheData['ContinuityLogin']>=5){
						$userIntegral = 5;
					}else{
						$userIntegral = 2;
					}
				}else{
					switch($userCacheData['ContinuitySign']){
						case 1:
						case 2:
							$userIntegral = 5;
							break;
						case 3:
						case 4:
						case 5:
							$userIntegral = 10;
							break;				
						case 6:
						case 7:
							$userIntegral = 15;
							break;
						default:
							$userIntegral = 20;
							break;
					}
				}
			}
		}

		if($userIntegral){
			$this->setConfig( \haibao\classlibrary\www\Config::CLASSLIBRARY_CONFIG_MYSQL_HAIBAO_DZ3 );
			$sql="update hb_common_member_count set extcredits2=extcredits2+$userIntegral where uid = %s";
			$this->query($sql,array($userId));
		}
		return $userIntegral;
	}
}