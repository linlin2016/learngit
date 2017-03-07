<?php
/**
 * @file $HeadURL: download.php $
 * @author $Author: LinLin (linlin@haibao.com) $
 * @date $Date:  $
 * @brief 批量下载图片
 */
namespace haibao\cms\web\view\image;

use haibao\frame\http\Request;
use haibao\frame\FrameException;
use haibao\cms\business\BusinessException;

class DownloadFashionwear extends \haibao\cms\web\view\Base{
	protected function preRender(){
		$this->checkPagePermission(\haibao\cms\business\Base::FUNC_CODE_IMAGE_LIBRARY_DOWNLOAD);
		$id = Request::get('id');
		$type = Request::get('type') ? Request::get('type') : 'default';
		$downloadFileame = 'HAIBAO-'.date('YmdHis').'.zip';
		$saveFilname = $this->downloadFashionWearImages($id);
		if(file_exists($saveFilname)){
			header("Cache-Control: public");
			header("Content-Description: File Transfer");
			header('Cache-Control: private, must-revalidate,post-check=0, pre-check=0, max-age=1');//这句兼容低版本ie
			header('Content-disposition: attachment; filename='.$downloadFileame);
			header("Content-Type: application/zip");
			header("Content-Transfer-Encoding: binary");
			readfile($saveFilname);
			unlink($saveFilname);
		}else{
			header("Content-type: text/html; charset=utf-8");
			echo '没有找到相应图片,或者此图片没有选择库中单品';
		}
		exit;
	}
	
    public function downloadFashionWearImages($imageIds){
		//self::checkOperation(self::FUNC_CODE_IMAGE_LIBRARY_DOWNLOAD);
        list($fashionWearInfo,$productId) = \haibao\cms\business\fashionwear\FashionWear::getFashionProductInfoByImgIds($imageIds);
        $imageIdArr = explode(',',$imageIds);
        $leftImage = \haibao\cms\business\ImageLibrary::getImagesById($imageIdArr);
        foreach($leftImage as $key=>$val){
           $imgInfo[$val->Id] = $val->Filename;
        }
        $productId = array_filter($productId);
        if($productId){
            $idStr = implode(',',$productId);
            $productInfo = \haibao\cms\business\Product::getProductInfoByIds($idStr);
            foreach($productInfo as $key=>$val){
                $brandIds[] = $val['BrandId'];
                $productIds[] = $val['Id'];
            }
            $productIdStr = implode(',', $productIds);
            $productPrice = \haibao\cms\business\Product::getProductValueByIds($productIdStr,true);
            if($brandIds){
                $brandInfo = \haibao\cms\business\Brand::getBrandByIds($brandIds,false,true);
                foreach($productInfo as $k=>$v){
                    if(isset($brandInfo[$v['BrandId']])){
                        $productInfo[$k]['Description'] = isset($brandInfo[$v['BrandId']]->NameEN) ? $brandInfo[$v['BrandId']]->NameEN : $brandInfo[$v['BrandId']]->NameCN;
            
                    }
                }
            }
            foreach($fashionWearInfo as $key=>$val){
                foreach($val['rightimage'] as $k=>$v){
                    if(isset($productInfo[$v['ProductId']])){
                        $fashionWearInfo[$key]['rightimage'][$k]['FileName'] = $productInfo[$v['ProductId']]['FileName'];
                        $fashionWearInfo[$key]['rightimage'][$k]['Description'] = $productInfo[$v['ProductId']]['Description'];
                        $fashionWearInfo[$key]['rightimage'][$k]['Price'] = isset($productPrice[$v['ProductId']]) ? $productPrice[$v['ProductId']] : '';
                    }
                }
            }
            foreach($fashionWearInfo as $key=>$val){
                if(isset($imgInfo[$key])){
                    $fashionWearInfo[$key]['leftimage'] = $imgInfo[$key];
                }
            }
            if(!$fashionWearInfo){
                throw new BusinessException('下载失败');
            }
            $saveFilename = \haibao\classlibrary\cms\Config::getConfig(\haibao\classlibrary\cms\Config::IMAGE_DOWN_BASE_PATH);
            if(file_exists($saveFilename)){
                chmod($saveFilename, 0777);
                unlink($saveFilename);
            }
            $zip = new \ZipArchive();
            $res = $zip->open($saveFilename, \ZIPARCHIVE::CREATE);
            if ( $res === TRUE) {
                foreach($fashionWearInfo as $key=>$model){
                    foreach($model['rightimage'] as $k=>$val){
                        if(!isset($val['FileName'])){
                            unset($fashionWearInfo[$key]['rightimage'][$k]);
                        }
                    }
                }
                foreach ($fashionWearInfo as $model){
                    if(!empty($model['rightimage'])){
                        $compose = new \haibao\cms\web\common\ComposeImage();
                        $filePath = $compose->getComposeImage($model['leftimage'],$model['rightimage'],true);
                        $filePath = $this->getSaveImagePath($filePath);
                        if(file_exists($filePath)){
                            $zip->addFile($filePath, basename($filePath));
                        }
                    }
                }
                $zip->close();
                //self::saveOperateHistory(self::FUNC_CODE_IMAGE_LIBRARY_DOWNLOAD, $models);
            }
            return $saveFilename;
        }
       
	}
	
	/**
	 * 获取文件保存路径
	 */
	public function getSaveImagePath($path){
	    $path = '/'. ltrim($path,'/');
	    return \haibao\frame\Environment::path() . $path;
	}
	
}