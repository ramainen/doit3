<?php

//функции https://github.com/laravel/framework/blob/5.2/src/Illuminate/Foundation/helpers.php

/**
 * Возвращает экземпляр основного объекта системы. Если его не существует, создаёт его.
 * Является обёрткой для паттерна Singletone
 * Если указан необязательный параметр, возвращет свойство основного объекта с указанными именем
 * Например: d('title') или d('User')
 * Более короткая запись функции doit()
 *
 * @param string $object (необязательно) Свойство основного объекта
 * @return doitClass Экземпляр основного объекта системы
 */
function d()
{
	return Doit::$instance;
}

/**
 * Обработчик ошибок, возникающих при работе функций любого типа (шаблоны, функции и т.д.)
 *
 * @param $output Ошибочный вывод.
 * @return string Информация об шибке
 */
function doit_ob_error_handler($output)
{
	$error = error_get_last();
	
	
	 
	if($error['type']==1){
		
		//throw  new Exception('sd') ;
		
		$parent_function =  'nono';//d()->_active_function();




		if(d()->db->errorCode()!=0){
			$db_err=d()->db->errorInfo();
			$_message='<br>Также зафиксирована ошибка базы данных:<br>'. $db_err[2]." (".$db_err[1].")";
			if(iam('developer')){ 
				if($db_err[1] == '1146'){
					$_message.='<br> Создать таблицу <b>'.h(d()->bad_table).'</b>? <form method="get" action="/admin/scaffold/new" style="display:inline;" target="_blank"><input type="submit" value="Создать"><input type="hidden" name="table" value="'.h(d()->bad_table).'"></form> ';
					
					
				}
				if($db_err[1] == '1054'){
					//Попытка создать столбик для таблицы
					//Unknown column 'user_id'
					$_column_name = array();
					if( preg_match_all("/Unknown\scolumn\s\'(.*?)\'/",$db_err[2], $_column_name)==1){
						$_column_name = 	$_column_name[1][0];
					
						$_message.='<br> Создать столбец <b>'.h($_column_name).'</b> в таблице '.h(d()->bad_table).'? <form method="post" action="/admin/scaffold/create_column" style="display:inline;" target="_blank"><input type="submit" value="Создать"><input type="hidden" name="table" value="'.h(d()->bad_table).'"><input type="hidden" name="column" value="'.h($_column_name).'"></form> ';
					}
					
				}
				$_message.='<br> Провести обработку схемы? <form method="get" action="/admin/scaffold/update_scheme" style="display:inline;" target="_blank"><input type="submit" value="Провести"></form><br>';
			}
			
		}
		$errfile = substr($error['file'],strlen($_SERVER['DOCUMENT_ROOT'])) ;
		return print_error_message(' ',$error['line'],$errfile ,$error['message'],'Ошибка при выполнении функции '.$parent_function.' '.$_message );
	}
	return $output;
}

/**
 * Обработчик исключений тип PARSE_ERROR, возникших при загрузке проекта, которые невозможно словить другим способом.
 */
function doit_parse_error_exception()
{
	if($error = error_get_last()){
	
		if($error['type']==4 || $error['type']==64 || $error['type']==4096){
			$errfile = substr($error['file'],strlen($_SERVER['DOCUMENT_ROOT'])) ;
			$lines=file($_SERVER['DOCUMENT_ROOT'].'/'.$error['file']);
			$wrongline=$lines[$error['line']];
			print print_error_message($wrongline,$error['line'],$errfile ,$error['message'],'Ошибка при разборе кода');
		}
	}
}



/**
 * Внутренняя служебная функция для вывода сообщений об ошибке.
 *
 * @param $wrongline Текст строки с ошибкой
 * @param $line Номер строки с ошибкой
 * @param $file Файл
 * @param $message Сообщение системное
 * @param $usermessage Соощение пользователя
 * @param bool $last Скрывать все следающие ошибки после этой.
 * @return string Сформированное сообщение.
 */
function old_print_error_message($wrongline,$line,$file,$message,$usermessage,$last=false)
{
	static $not_show_me_in_future=false;
	if($not_show_me_in_future){
		return '';
	}
	if($last==true){
		$not_show_me_in_future=true;
	}
	$errfile = substr($file,strlen($_SERVER['DOCUMENT_ROOT'])) ;
	$file_and_line='';
	if($file!='' || $line!=''){
		$file_and_line='<div>Файл '.$file.', строка '.$line.'</div>';
	}
	return '<div style="padding:20px;border:1px solid red;background:white;color:black;">
					<div>'.$usermessage.': '.$message.'</div>'.
					$file_and_line.
					htmlspecialchars($wrongline).'</div>';
}



function print_error_message($wrongline,$line,$file,$message,$usermessage,$last=false, $errcontext)
{
	static $not_show_me_in_future=false;
	if($not_show_me_in_future){
		return '(not_show_me_in_future)';
	}
	if($last==true){
		$not_show_me_in_future=true;
	}
	
	//return json_encode(xdebug_get_function_stack());
	$template_dir = DOIT_ROOT.'/core/lib/templates/error/';
	
	//$e = new \Exception;
	//return($e->getTraceAsString());
	
	
	$content =  file_get_contents($template_dir . 'error.html');
	
	$replacements = array();
	//Текст ошибки
	$replacements['#MESSAGE#'] = $message;
	$replacements['#USERMESSAGE#'] = $usermessage;
	
	$res = '';
	$arr = file(DOIT_ROOT.$file);
 
	$first_line = $line - 10;
	if($first_line < 0 ){
		$first_line = 0;
	}
	
	$last_line = $line + 10;
	if($last_line > count($arr)-1){
		$last_line = count($arr)-1;
	}
	for($i = $first_line;$i<=$last_line;$i++){
		$arr[$i] = htmlspecialchars($arr[$i]);
		if($i == $line-1){
			$res .= '<li class="bugi" style="">'.$arr[$i].'</li>' ;
		}else {
			$res .= '<li class="" style="">'.$arr[$i].'</li>' ;
		}
		
	}
	
	/*$r = debug_backtrace();
	return json_encode($r);*/
	//$res = trim($res);
	$res = "<ol start='". ($first_line+1) ."'>".$res."</ol>";
	$replacements['#FRAGMENT#'] = $res;
	$replacements['#START_LINE#'] =  $first_line;
	
	
	if(function_exists("xdebug_get_function_stack")){
		$stack = xdebug_get_function_stack();
	}else{
		$stack = debug_backtrace();
	}
	
	
	$stacktrace = "";
	foreach ($stack as $row){
		
		$res = '';
		$arr = file($row['file']);
	 
		$first_line = $row['line'] - 10;
		if($first_line < 0 ){
			$first_line = 0;
		}
		
		$last_line = $row['line'] + 10;
		if($last_line > count($arr)-1){
			$last_line = count($arr)-1;
		}
		for($i = $first_line;$i<=$last_line;$i++){
			$arr[$i] = htmlspecialchars($arr[$i]);
			if($i == $row['line']-1){
				$res .= '<li class="bugi" style="">'.$arr[$i].'</li>' ;
			}else {
				$res .= '<li class="" style="">'.$arr[$i].'</li>' ;
			}
			
		}
 
		$res = "<ol start='". ($first_line+1) ."'>".$res."</ol>";	
		
		$res ='<DIV class="codecontainer"  ><DIV class="filename"><a class="js-openrow" href="javascript:void(0)"> ' . $row['file']. ' (' . $row['line']. '):</a></DIV><pre class="hidden_code"><code>'. $res .'</code></pre></DIV>';
		
		
		$stacktrace .= $res;
	}
	//return d()->db->errorCode();
//	
	$pdo_data = new DebugBar\DataCollector\PDO\PDOCollector(d()->db);
	$data_queries = $pdo_data->collect();
	
	
	
	$queries = '';
	$res = '';
	//return d()->db->errorCode();
 //return json_encode($data_queries);
	foreach($data_queries ['statements'] as $key =>$value ){
		
		if($value['is_success']){
			$res .= '<li class="" style="">'.htmlspecialchars( $value["sql"]).'</li>' ;	
		}else{
			$res .= '<li class="bugi" style="">'.htmlspecialchars( $value["sql"]).'</li>' ;	
		}
		
		
		
	}
 
	
	$res = "<ol  >".$res."</ol>";	
	
	$res ='<DIV class="codecontainer" > <pre class=" "><code class="sql">'. $res .'</code></pre></DIV>';
	
	
	$queries .= $res;
	
	
	
	
	
	
//	return json_encode($data_queries);
	
	
	
	//Имя файла
	$replacements['#FILENAME#'] = DOIT_ROOT.$file;
	$replacements['#SHORTNAME#'] = json_encode($file);
	$replacements['#LINE#'] = $line;
	$replacements['#STACKTRACE#'] = $stacktrace;
	$replacements['#QUERIES#'] = $queries;
//	$replacements['#DB_ERROR#'] = d()->db->errorInfo();
	//Номер строки
	
	//Содержимое файла.
	$content = str_replace(array_keys($replacements),$replacements,$content);
	
	return $content;
	$errfile = substr($file,strlen($_SERVER['DOCUMENT_ROOT'])) ;
	$file_and_line='';
	if($file!='' || $line!=''){
		$file_and_line='<div>Файл '.$file.', строка '.$line.'</div>';
	}
	return '<div style="padding:20px;border:1px solid red;background:white;color:black;">
					<div>'.$usermessage.': '.$message.'</div>'.
					$file_and_line.
					htmlspecialchars($wrongline).'</div>';
}