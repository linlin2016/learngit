<?php
/**
	 *生成文件
	 * @param 文章内容 $content
	 * @param 分页 $page
	 * @param 文章id $articleId
	 */
	private function makeHtml($articleInfoArr,$articleModel,$isFtp = true){
	    if($articleModel->PublishId > 1000000){
	        $localChannelPath = substr($articleModel->PublishId, 0,3).'/'.substr($articleModel->PublishId, 3,3).'/';
	    }elseif($articleModel->PublishId <= 1000){
	        $localChannelPath = 'min/';
	    }else{
	        $localChannelPath = substr($articleModel->PublishId, 0,3).'/';
	    }
	    $filePath = dirname(dirname(dirname(__FILE__))).\haibao\classlibrary\cms\Config::getConfig(\haibao\classlibrary\cms\Config::CONTROL_HTML_PATH).'articledata/'.$localChannelPath;
	    if(!is_dir($filePath)){
	        mkdir($filePath,0777,true);
	    }
	    $fileName = $articleModel->PublishId.'.php';
	    $file = $filePath.$fileName;
	    
	    $text = '<?php '."\n".'$articleInfoArr = '.var_export($articleInfoArr,true).';'."\n";
	    $text .= '$channelName = '.var_export($articleModel->ChannelName,true).';'."\n";
	    $text .= '$primaiyTopTagId = '.var_export($articleModel->PrimaryTopTagId,true).';'."\n";

	    
		//存储本地并ftp上传
		if(file_put_contents($file, $text)){
			if($isFtp){
				if(is_array(\haibao\cms\Config::$ftpToActing)){
					foreach(\haibao\cms\Config::$ftpToActing as $key => $ftpConfig){
						$ftp = new ftp($ftpConfig['serviceHost'],$ftpConfig['partNumber'],$ftpConfig['userName'],$ftpConfig['passWord']);
						$ftp->upFile($file, \haibao\classlibrary\cms\Config::getConfig(\haibao\classlibrary\cms\Config::JUHE_ARTICLE_DATA_PATH).$localChannelPath.$fileName);
						$ftp->close();
					}
				}
			}
		}
	}