<?php

	//error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
	error_reporting(0);
	session_start();
	mb_internal_encoding("UTF-8");

	if(file_exists('vendor/autoload.php')){
		require_once ('vendor/autoload.php');	
	}

	require_once ('core/vendor/autoload.php');
	
	require_once ('config.php');
	
	//Если ничего не найдено, создаёт класс и экземпляр класса ActiveRecord
	spl_autoload_register(function ($class_name) {
		$class_name = ltrim($class_name, '\\');
		if ($lastNsPos = strripos($class_name, '\\')) {
			$class_name = substr($class_name, $lastNsPos + 1);
		}
		if(substr(strtolower($class_name),-10)!='controller'){
			//Если совсем ничего не найдено, попытка использовать ActiveRecord.
			eval ("class ".$class_name." extends ActiveRecord {}");	
		}
	});

	register_shutdown_function('doit_parse_error_exception');

	$core = new Doit();
	return $core;