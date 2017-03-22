<?php
/**
* @file composeimage.php
* @date: 2016-12-2 下午2:58:39
* @author: xiweijie
*/
namespace haibao\cms\web\common;

class ComposeImage {
	
	const COLOR_BLACK = 1;
	const COLOR_WHITE = 2;
	
	private $width = 468;
	private $height = 468;
	private $leftImgWidth = 312;//左侧图片宽
	private $smallImgWidth = 96;//右侧小图宽
	private $smallImgHeight = 96;//右侧小图高
	private $smallBoxWidth = 110;//右侧小图外框宽
	private $smallBoxHeight = 110;//右侧小图外框高
	
	/**
	* 合并图片
	* @param string $leftImg  左侧大图地址
	* @param array $rightImages 右侧小图
	* $rightImages = array(
	*			array(
	*				'FileName'=>'shoudai.jpg',
	*				'Description'=>'薇姿 (Vichy)薇姿',
	*				'Price'=>'$1233'	
	*			)
	*	);
	*/
	public function getComposeImage($leftImg,$rightImages,$isPrice=false){
		$image = new \Imagick();
		$new_im = clone $image;
		$imgPath = \haibao\cms\business\ImageLibrary::getSaveImagePath($leftImg);
		$image->readImage($imgPath);
		$image->setCompression(\Imagick::COMPRESSION_JPEG);
		$image->setCompressionQuality(80); // 设置图片品质
		$srcImage = $image->getImageGeometry(); //获取源图片宽和高(左侧)
		
		$scale_org = $srcImage['width'] / $srcImage['height'];//左侧图片宽高比例
		if ($srcImage['width'] / $this->leftImgWidth > $srcImage['height'] / $this->height) {
		    /* 原始图片以宽度为准 */
			$newX = $this->leftImgWidth;
			$newY = $this->leftImgWidth / $scale_org;
		} else {
			/* 原始图片比较高，则以高度为准 */
			$newX = $this->height * $scale_org;
			$newY = $this->height;
		}
		$size["width"] = $newX;     //原图片的宽度（左侧图片）
		$size["height"] = $newY;
		$image->thumbnailImage($newX, $newY);
		if($isPrice){
			//获取水印地址
			$waterImgPath = $this->getWaterImgPathNew($image,$size);
			$waterInfo = $this->getInfo($waterImgPath);      //获取水印图片信息
			/*如果背景比水印图片还小，就会被水印全部盖住*/
			$pos = $this->position($size, $waterInfo);
			$waterImg = new \Imagick($waterImgPath);
			$image->compositeImage($waterImg, $waterImg->getImageCompose(), $pos["posX"], $pos["posY"]);
		}
		$new_im->newImage($this->width, $this->height, 'white', 'jpg');//创建一个背景图片
		//合并图片
		$new_im->compositeImage($image, \Imagick::COMPOSITE_OVER, 10, ($this->height - $newY) / 2);
		$new_im->setImageFormat('jpg');
		
		$imgHeight = $this->height;
		$rightImg = new \Imagick();
		$rightImg->newImage($this->width-$this->leftImgWidth-34, $imgHeight, new \ImagickPixel('white'));
		$rightWidth = $this->width-$this->leftImgWidth-34;
		$count = count($rightImages);
		if($count > 3){
			$rightImages = array_slice($rightImages,0,3);
			$count = 3;
		}else{
			$imageArr = $rightImages;
		}
		$top = ceil($this->height/$count) - ($this->smallBoxHeight + 35);
		$i = 0;
		foreach ($rightImages as $key=>$val){
			$boxImg = new \Imagick();
			$boxImg->newImage($this->smallBoxWidth, $this->smallBoxHeight, new \ImagickPixel('white'));
			$color=new \ImagickPixel();
			$color->setColor("gainsboro");
			$boxImg->borderImage($color,1,1);
			$smallImgPath = \haibao\cms\business\ImageLibrary::getSaveImagePath($val['FileName']);
			$smallImg = new \Imagick( $smallImgPath );
			$smallImg->thumbnailImage( $this->smallImgWidth, $this->smallImgHeight);
			
			$boxImg->compositeImage($smallImg, $smallImg->getImageCompose(), 8, 8);//单品图片 在小框中的位置
			$marginTop = ceil ( $this->height / $count ) * $i;
			if ($count < 3) {
				if ($count == 1) {
					$marginTop = ceil ( $this->height / 3 );
				} else {
					if ($i == 0) {
						$marginTop = $top - 10;
					}
				}
			}
			
			$rightImg->compositeImage($boxImg, $boxImg->getImageCompose(), 0, $marginTop);//右侧盒子 放在右侧图片里  一个盒子一个盒子的发
			
			$draw = new \ImagickDraw(); //实例化一个绘画对象，绘制exif文本信息嵌入图片中
			$draw->setFont( \haibao\frame\Environment::path().'/web/resource/font/yahei.ttf' );
// 		    $draw->setStrokeColor('black');
// 		    $draw->setFillColor('black');
	 
// 		    $draw->setStrokeWidth(0.1);
 			$draw->setFontSize( 13 ); //设置字号
			$draw->setFontWeight( 900 ); //设置weight
			$draw->setTextAlignment( 2 ); //文字对齐方式，2为居中
			$draw->setFillColor( '#000000' );
			$space = ceil($this->height/$count) - $this->smallBoxHeight - 20;
			$h = (ceil($this->height/$count))*($i+1)-$space;
			if($count < 3 && $i==0){
				if($count == 1){
					$h = $h + ceil($this->height/3);
				}else{
					$h = $h + $top - 10;
				}
			}
			$rightImg->annotateImage( $draw, ($rightWidth/2)-4, $h, 0, $this->strLength($val['Description']));
			if($isPrice && $val['Price']){
				$drawPrice = new \ImagickDraw(); //实例化一个绘画对象，绘制exif文本信息嵌入图片中
				$drawPrice->setFont( \haibao\frame\Environment::path().'/web/resource/font/yahei.ttf' );
				$drawPrice->setFontSize( 12 ); //设置字号
				$drawPrice->setTextAlignment( 3 ); //文字对齐方式，2为居中
				$drawPrice->setFillColor( '#fff' );
				$len = mb_strlen($val['Price'],'UTF-8');
				$textWidth = $rightWidth-($len*8+10);
				$textImage = new \Imagick();
				$textImage->newImage($len*8+6, 15, new \ImagickPixel('red'));
				$textImage->annotateImage( $drawPrice, $len*8+2, 12, 0, $val['Price']);
				$rightImg->compositeImage($textImage, $textImage->getImageCompose(), $textWidth-6, $h-34);
			}
			$i++;
		}
		$new_im->compositeImage($rightImg, $rightImg->getImageCompose(), $this->leftImgWidth+34, 0);
		$new_im->setImageFormat('jpg');
		$storagePath = $this->getImagePath();
		$newPath = \haibao\cms\business\ImageLibrary::getSaveImagePath($storagePath);
		if($isPrice){
			$imageBorder = new \Imagick();
			$imageBorder->newImage($this->width+40, $this->height+40, 'white', 'jpg');
			$imageBorder->compositeImage($new_im, $new_im->getImageCompose(), 20, 20);
			$imageBorder->writeImage( $newPath );
		}else{
			$new_im->writeImage( $newPath );
		}
		header('Content-type: image/jpeg');
		echo $new_im;exit;
		return $storagePath;
	}
	
	public function getImagePath(){
		$basePath = \haibao\classlibrary\cms\Config::getConfig(\haibao\classlibrary\cms\Config::IMAGE_UPLOAD_BASE_PATH).date('Y').DIRECTORY_SEPARATOR.date('md').DIRECTORY_SEPARATOR;
		$storagePath = $basePath.microtime(true).'.jpg';
		$newPath = \haibao\cms\business\ImageLibrary::getSaveImagePath($storagePath);
		\haibao\cms\business\ImageLibrary::createDir($newPath);
		return $storagePath;
	}
	
	private function getWaterImgPathNew($imgHandle, $imgInfo) {
		$path = '';
		$width = $imgInfo ['width'];
		$height = $imgInfo ['height'];
		$name = 'logo14';
		$waterColor = $this->getWaterColor ( $imgHandle, $imgInfo, $name );
		if (! $waterColor) {
			return false;
		}
		
		if ($waterColor == self::COLOR_BLACK) {
			if ($height > 600) {
				$name = 'logo11';
			} else {
				$name = 'logo13';
			}
		} else {
			if ($height > 600) {
				$name = 'logo12';
			} else {
				$name = 'logo14';
			}
		}
		
		if ($name) {
			$path = \haibao\frame\Environment::path () . '/web/resource/waterimages/' . $name . '.png';
		}
		
		return $path;
	}
	
	private function getWaterColor($imgHandle,$imgInfo,$waterFilePath){
		$color = false;
		$waterFilePath = \haibao\frame\Environment::path().'/web/resource/waterimages/'.$waterFilePath.'.png';
	
		if (file_exists($waterFilePath)){
			$waterInfo = $this->getInfo($waterFilePath);
			$pos = $this->position($imgInfo, $waterInfo);
			if(!$pos){
				return false;
			}
	
			$grey = $this->getGreyNew($imgHandle,$pos,$waterInfo);
			if ($grey > 127){
				$color = self::COLOR_BLACK;
			}else{
				$color = self::COLOR_WHITE;
			}
		}
	
		return $color;
	}

	private function position($groundInfo, $waterInfo){
		$img_w = $groundInfo["width"];
		$img_h = $groundInfo["height"];
		$pian_w = $pian_h = 10;
		if ($img_w < $this->leftImgWidth) {
			$pian_h = 60;
		}else{
			if($img_h < $this->height){
				$pian_w = 20;
			}else{
				$pian_h = 60;
			}
		}
	
		$posX = $img_w - ($waterInfo['width'] + $pian_w);
		$posY = $img_h - ($waterInfo['height'] + $pian_h);
		if($posX < 0 || $posY < 0){
			$posX = $posY = 0;
		}
		if($posX && $posY){
			return array("posX"=>$posX, "posY"=>$posY);
		}
		return array();
	
	}
	
	/**
	 *
	 * @param \Imagick $img
	 */
	private function getGreyNew($img,$posInfo,$sizeInfo,$points = 1){
		$grey = 0;
		$copyImg = clone $img;
		$copyImg->cropimage($sizeInfo['width'], $sizeInfo['height'], $posInfo['posX'], $posInfo['posY']);
		$copyImg->quantizeImage( $points, \Imagick::COLORSPACE_GRAY, 0, false, false );
		$copyImg->uniqueImageColors();
		$colorArr = $this->getImagesColor($copyImg);
		$copyImg->destroy();
	
		foreach ($colorArr as $value){
			$grey += $value['r'];
		}
	
		return floor($grey/$points);
	}
	
	/**
	 * @param \Imagick $im
	 */
	private function getImagesColor($im){
		$colorarr = array();
		$it = $im->getPixelIterator();
		$it->resetIterator();
		while( $row = $it->getNextIteratorRow() ){
			foreach ( $row as $pixel ){
				$colorarr[] = $pixel->getColor();
			}
		}
	
		return $colorarr;
	}
	
	/* 内部使用的私有方法，用于获取图片的属性信息（宽度、高度和类型） */
	private function getInfo($imgPath) {
		$data = getimagesize($imgPath);
		$imgInfo["width"]  = $data[0];
		$imgInfo["height"] = $data[1];
		$imgInfo["type"]  = $data[2];
	
		return $imgInfo;
	}
	
	private function strLength($str){
		$str = iconv("UTF-8","UTF-8//IGNORE",$str);
		$num = mb_strlen($str,'UTF-8');
		$number = 0;
		$content = '';
		$flag = false;
		for($i=0;$i<$num;$i++){
			$s = mb_substr($str,$i,1,'UTF-8');
			if(preg_match("/^[\x7f-\xff]+$/", $s)){
				$number = $number+2;
			}else{
				$number++;
			}
			$content .= $s;
			if($number >= 15 && !$flag){
				$content .= "\n";
				$flag = true;
			}
			if($number >= 32){
				break;
			}
		}
		return $content;
	}
}