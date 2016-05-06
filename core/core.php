<?php

	if(file_exists('vendor/autoload.php')){
		require_once ('vendor/autoload.php');	
	}

	require_once ('cms/vendor/autoload.php');
/**
 * Автоматический создатель классов и загрузчик классов по спецификации PSR-0
 * Ищет файлы вида class_name.class.php, затем ищет классы в папке vendors по спецификации PSR-0.
 * Если ничего не найдено, создаёт класс и экземпляр класса ar (ActiveRecord)
 *
 * @param $class_name Имя класса
 */
 
 spl_autoload_register(function  ($class_name) {

	$class_name = ltrim($class_name, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strripos($class_name, '\\')) {
        $namespace = substr($class_name, 0, $lastNsPos);
        $class_name = substr($class_name, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';
	//$fileName = 'vendors'.DIRECTORY_SEPARATOR.$fileName;
	$lover_class_name=strtolower($class_name);
	if(isset(d()->php_files_list[$lover_class_name.'_class']) && is_file($_SERVER['DOCUMENT_ROOT'].'/'. d()->php_files_list[$lover_class_name.'_class'])){
		require $_SERVER['DOCUMENT_ROOT'].'/'.d()->php_files_list[$lover_class_name.'_class'];
	}elseif(is_file($_SERVER['DOCUMENT_ROOT'].'/'.('vendors'.DIRECTORY_SEPARATOR.$fileName))){
		require $_SERVER['DOCUMENT_ROOT'].'/'.'vendors'.DIRECTORY_SEPARATOR.$fileName;
	}else{
		if(substr(strtolower($class_name),-10)!='controller'){
			//Если совсем ничего не найдено, попытка использовать ActiveRecord.
			eval ("class ".$class_name." extends ActiveRecord {}");	
		}
	}

});