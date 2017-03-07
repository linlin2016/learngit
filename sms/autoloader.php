<?php
/**
 * autoloader.php
 * 
 * Created on 2015-3-13
 * Create by Calvin.Wang
 */

namespace haibao\classlibrary\sms;

class Autoloader {
	public static function autoLoad($className){
		$nameSpacePre = 'haibao\sms';
		$end = strlen($className);
		$start = strlen($nameSpacePre);
		if ($end > $start){
			if (substr($className,0,$start) == $nameSpacePre){
				$file = dirname(__FILE__) . strtolower(substr($className,$start,$end)) . '.php';
				$file = str_replace('\\', DIRECTORY_SEPARATOR, $file);

				if (file_exists($file)){
					include_once $file;
				}
			}
		}
	}
}

spl_autoload_register(array('haibao\classlibrary\sms\Autoloader','autoLoad'));
