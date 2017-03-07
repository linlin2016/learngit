<?php
/**
* 发送钉钉消息
* @date: 2016-10-24 下午6:27:06
* @author: xiweijie
*/
namespace haibao\sms;

class SendDingTalk extends \haibao\sms\DingTalk{
	
	/**
	 * 发送企业消息
	 */
	public static function sendTextMsg($toUser,$content){
		if(empty($toUser)){
			throw new \Exception('用户ID不能为空');
		}
		$option = array(
				'touser'=>$toUser,
				'agentid'=>self::AGENTID,
				'msgtype'=>'text',
				'text'=>array(
						'content'=>$content
				)
		);
		$response = self::send($option);
		$json = json_decode($response,true);
		if (!$json || !empty($json['errcode'])) {
				$error = '发送钉钉消息异常：'.$json['errcode'].'_'.$json['errmsg'].'——— Param：'.json_encode(array($toUser,$content));
				throw new \Exception($error);
		}
		return $response;
	}
	
}
