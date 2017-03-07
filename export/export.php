<?php
/**
 * @file export.php $
 * @author LinLin (linlin@haibao.com) $
 * @date 2016-1-21 下午3:50:15 $
 * @brief  $
 */
namespace haibao\cms\web\view\star;

use haibao\app\business\BusinessException;
use haibao\cms\model\filter\star\StarDefine as StarDefineFilter;
use haibao\cms\model\filter\star\StarSecret as StarSecretFilter;
use haibao\cms\model\filter\star\StarProperty as StarPropertyFilter;
use haibao\cms\model\filter\star\StarCooperate as StarCooperateFilter;
use haibao\cms\model\filter\star\StarPlatInfo as StarPlatInfoFilter;
use haibao\cms\business\star\StarDefine as StarDefineBusiness;
use \haibao\frame\http\Request ;
use haibao\frame\data\query\Condition;

require_once  '../common/phpexcel/phpexcel.php';
class Export extends \haibao\cms\web\view\Base{
	protected function preRender(){
		if(Request::get('StarId')){
		    $this->checkPagePermission(\haibao\cms\business\Base::FUNC_CODE_STAR_EXPORT);
			$idStr = Request::get('StarId');
			if($idStr == 'all'){
			    $idArr = StarDefineBusiness::getAllStarId();
			}else {
			   $idArr = explode(',', $idStr);
			}
			if (Request::isGET()){
				$starDefineOneInfo = $this->getStarInfoList($idArr);
				$starAccount = \haibao\cms\business\star\StarDefine::$starAccountType;
				$starPlatInfo = \haibao\cms\business\star\StarDefine::$starPlatForm;
				if(count($starDefineOneInfo) > 0){
					$list = $acccoutNum = $Platform = array();
					$listName = array('A'=>'名字','B'=>'所在地','C'=>'微博','D'=>'微博粉丝数','E'=>'微博最大费用','F'=>'微信','G'=>'微信粉丝数','H'=>'微信最大费用','I'=>'个人介绍','J'=>'其他平台相关信息','K'=>'合作品牌','L'=>'费用');
					if($starDefineOneInfo){
						foreach($starDefineOneInfo as $key=>$model){
							$list[$key][] = $model->RealName;
							$list[$key][] = $model->Location;
							if(isset($model->PropertyInfo) && $model->PropertyInfo[0]->StarId != ''){
							    $weiboAccountLink = $weiboFansNum = $weiboCost = '';
							    $weixinAccountLink = $weixinFansNum = $weixinCost = '';
								foreach($model->PropertyInfo as $k=>$v){
									if($v->StarAccountType == 1){
										$weiboAccountLink = $v->AccountLink;
										$weiboFansNum = $v->FansNum;
										$weiboCost = $v->CooperateMaxCost;
									}
									if($v->StarAccountType == 2){
									    $weixinAccountLink = $v->AccountLink;
									    $weixinFansNum = $v->FansNum;
									    $weixinCost = $v->CooperateMaxCost;
									}
								}
								$list[$key][] = $weiboAccountLink;
								$list[$key][] = $weiboFansNum;
								$list[$key][] = $weiboCost;
								$list[$key][] = $weixinAccountLink;
								$list[$key][] = $weixinFansNum;
								$list[$key][] = $weixinCost;
							}else{
								$list[$key][] = '';
								$list[$key][] = '';
								$list[$key][] = '';
								$list[$key][] = '';
								$list[$key][] = '';
								$list[$key][] = '';
							}
							$list[$key][] = strip_tags($model->Description);
							if(isset($model->PlatInfo) && $model->PlatInfo[0]->StarId != ''){
							    $Platform = array();
								foreach($model->PlatInfo as $k=>$v){
									if(isset($starPlatInfo[$v->PlatType])){
										$Platform[] = $starPlatInfo[$v->PlatType]."\r\n";
									}
								}
								$PlatformStr = implode('',$Platform);
								$list[$key][] = $PlatformStr;
							}else {
								$list[$key][] = '';
							}
							if(isset($model->CooperateInfo) && $model->CooperateInfo[0]->StarId){
							    $cooperateBrand = $cooperateCost = array();
								foreach($model->CooperateInfo as $k=>$v){
									$cooperateBrand[] = $v->CooperateBrand."\r\n";
									$cooperateCost[] = $v->CooperateCost;
								}
								$cooperateBrandStr = implode('',$cooperateBrand);
								$cooperateCostStr = max($cooperateCost);
								$list[$key][] = $cooperateBrandStr;
								$list[$key][] = $cooperateCostStr;
							}else {
								$list[$key][] = '';
								$list[$key][] = '';
							}
							
						}
						$this->downLoadData ($list,$listName);
					}
				} else {
					header("Content-type: text/html; charset=utf-8");
					echo '无导出结果';exit;
				}
			}
		}
	}

	private function getStarInfoList($idArr){
		$starDefineFilter = new StarDefineFilter();
		$starDefineFilter->select(array(
				StarDefineFilter::CONDITION_FIELD_NAME_ID,
				StarDefineFilter::CONDITION_FIELD_NAME_REALNAME,
				StarDefineFilter::CONDITION_FIELD_NAME_DESCRIPTION,
				StarDefineFilter::CONDITION_FIELD_NAME_PLATFORM,
				StarDefineFilter::CONDITION_FIELD_NAME_LOCATION,
		));

		$starSecretFilter = new StarSecretFilter();
		$starSecretFilter->select(array(
				StarSecretFilter::CONDITION_FIELD_NAME_ID,
				StarSecretFilter::CONDITION_FIELD_NAME_STAR_ID,
		));
		
		$starCooperateFilter = new StarCooperateFilter();
		$starCooperateFilter->select(array(
				StarCooperateFilter::CONDITION_FIELD_NAME_ID,
				StarCooperateFilter::CONDITION_FIELD_NAME_STAR_ID,
				StarCooperateFilter::CONDITION_FIELD_NAME_COOPERATE_BRAND,
				StarCooperateFilter::CONDITION_FIELD_NAME_COOPERATE_COST,
		));
		
		$starPropertyFilter = new StarPropertyFilter();
		$starPropertyFilter->select(array(
				StarPropertyFilter::CONDITION_FIELD_NAME_ID,
				StarPropertyFilter::CONDITION_FIELD_NAME_STAR_ID,
				StarPropertyFilter::CONDITION_FIELD_NAME_FANS_NUM,
				StarPropertyFilter::CONDITION_FIELD_NAME_STAR_ACCOUNT_TYPE,
				StarPropertyFilter::CONDITION_FIELD_NAME_ACCOUNT_LINK,
		        StarPropertyFilter::CONDITION_FIELD_NAME_COOPERATE_MAX_COST
		));
		
		$starPlatInfoFilter = new StarPlatInfoFilter();
		$starPlatInfoFilter->select(array(
				StarPlatInfoFilter::CONDITION_FIELD_NAME_ID,
				StarPlatInfoFilter::CONDITION_FIELD_NAME_PLAT_INFO,
				StarPlatInfoFilter::CONDITION_FIELD_NAME_PLAT_TYPE,
				StarPlatInfoFilter::CONDITION_FIELD_NAME_STAR_ID,
		));
		$starDefineFilter->where(StarDefineFilter::CONDITION_FIELD_NAME_ID, Condition::CONDITION_IN, $idArr);
		$starDefineFilter->where(StarDefineFilter::CONDITION_FIELD_NAME_STATUS, Condition::CONDITION_EQUAL, \haibao\cms\model\data\star\StarDefine::STATUS_ENABLE);
		$starDefineFilter->leftJoin($starSecretFilter,null,StarDefineFilter::CONDITION_FIELD_NAME_ID,StarSecretFilter::CONDITION_FIELD_NAME_STAR_ID,StarDefineFilter::CONDITION_FIELD_NAME_SECRET_INFO,false);
		$starDefineFilter->leftJoin($starCooperateFilter,null,StarDefineFilter::CONDITION_FIELD_NAME_ID,StarCooperateFilter::CONDITION_FIELD_NAME_STAR_ID,StarDefineFilter::CONDITION_FIELD_NAME_COOPERATE_INFO);
		$starDefineFilter->leftJoin($starPropertyFilter,null,StarDefineFilter::CONDITION_FIELD_NAME_ID,StarPropertyFilter::CONDITION_FIELD_NAME_STAR_ID,StarDefineFilter::CONDITION_FIELD_NAME_PROPERTY_INFO);
		$starDefineFilter->leftJoin($starPlatInfoFilter,null,StarDefineFilter::CONDITION_FIELD_NAME_ID,StarPlatInfoFilter::CONDITION_FIELD_NAME_STAR_ID,StarDefineFilter::CONDITION_FIELD_NAME_PLAT_INFO);
		$starDefineInfo = StarDefineBusiness::getAllStarDefine($starDefineFilter);
		
		return $starDefineInfo;
	}
	
	private function downLoadData($list,$listName=null){
		try {
			//添加php下载Excel
			$inputFileType = 'Excel5';
			$objExcel = new \PHPExcel();
			$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
			// 输入文件标签
			$downFile = date('YmdHis').".xls";
			//设置文档基本属性
			$userName = \haibao\cms\business\star\StarDefine::getCurrentUser()->UserName;
			$objProps = $objExcel->getProperties();
			$objProps->setCreator($userName);
			$objProps->setLastModifiedBy($userName);
			$objProps->setTitle("Office XLS Test Document");
			$objProps->setSubject("Office XLS Test Document, Demo");
			$objProps->setDescription("Test document, generated by PHPExcel.");
			$objProps->setKeywords("office excel PHPExcel");
			$objProps->setCategory("Test");
			
			$objExcel->setActiveSheetIndex(0);
			$objActSheet = $objExcel->getActiveSheet();
			$objActSheet->getColumnDimension('F')->setWidth(300);
			$objActSheet->setTitle($downFile);
				
			//设置单元格的值
			if($listName){
				foreach($listName as $key=>$name){
					$objActSheet->setCellValue($key."1", $name);
					//设置宽度
					$objActSheet->getColumnDimension($key)->setAutoSize(true);
				}
			}
			$chars = array_keys($listName);
			if($list && $listName){
				foreach($list as $key=>$value){
					foreach($chars as $k=>$name){
						$objActSheet->setCellValueExplicit($name.($key+2), $value[$k],\PHPExcel_Cell_DataType::TYPE_STRING);
					}
				}
			}
			if(ob_get_length()){
				ob_clean();
			}
			header('Content-Type: application/vnd.ms-excel');
			header("Content-Disposition: attachment;filename={$downFile}");
			header('Cache-Control: max-age=0');
			header('Cache-Control: max-age=1');
			header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
			header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header ('Pragma: public'); // HTTP/1.0
			$objWriter = \PHPExcel_IOFactory::createWriter($objExcel, 'Excel5');
			$objWriter->save('php://output');
			ob_end_flush();
			exit;
		}catch (\Exception $e) {
			throw new BusinessException('下载失败');
			exit;
		}
	}

}