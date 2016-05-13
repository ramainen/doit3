<?php

class View
{
	protected $chosen=false;
	
	protected $template_patterns=array(); //Теги шаблонизатора
	protected $template_replacements=array(); //Значения тегов шаблонизатора
	public $isRendered = false;
	
	//todo: render без параметров должен заниматься ТОЛЬКО автопоиском
	//todo: twig запущенный из роута тоже должен работать
	function render($path=false){
		if($path!==false){
			$this->chosen = str_replace('..','',$path);//система безопасности =)
		}
		Doit::$instance->response->getBody()->write($this->renderNow());
		$this->isRendered  = true;
	}

	function renderHTML($html){
		Doit::$instance->response->getBody()->write($html);
		$this->isRendered  = true;
	}
	
	function renderPartial($html){
		$this->chosen = str_replace('..','',$path);
		Doit::$instance->response->getBody()->write($this->renderNow());
	}
	
	function partial($path){
		$this->chosen = str_replace('..','',$path);
		return $this->renderNow();
	}
	
	function __toString(){
		return $this->renderNow();
	}
	
	protected function renderNow(){


		$trys = array();
		
		$url=strtok($_SERVER["REQUEST_URI"],'?');
		$url = str_replace('..','',$url);
		$called_file = false;
		if($this->chosen !== false){
			$called_file = $this->chosen;
			
			$shortfile = $this->chosen;
			$trys[]  = $shortfile ;
			if(is_file($shortfile))
			{
				return  $this->from_file($shortfile);
			}
			
			if(is_file(DOIT_ROOT . '/app'.$shortfile))
			{
				return  $this->from_file($shortfile);
			}
			
			
			
			//вариант четвертый - файл внутри директории, вызов closure
			$tryfile =d()->_closure_current_view_path . '/'. $this->chosen;
			//Вырезаем всё
			$shortfile = substr($tryfile,strlen(DOIT_ROOT . '/app'));
			$trys[] = '/app'.$shortfile;
			if(is_file($tryfile))
			{
				
				 return  $this->from_file($shortfile);
			}
			
			//вариант пятый - файл внутри директории, вызов route
			if(d()->current_route != false){
				$tryfile =d()->current_route->include_directory . '/'. $this->chosen;
				//Вырезаем всё
				$shortfile = substr($tryfile,strlen(DOIT_ROOT . '/app'));
				$trys[] = '/app'.$shortfile;
				if(is_file($tryfile))
				{
					 return  $this->from_file($shortfile);
				}
				
			}
			
			
			
			$this->chosen=false;
			
			//Указанно явно варианта недостаточно
		}
		
		//Если не указан явно заданный файл, то проводим автопоиск в соответствии с url-ом
		
		//Вариант первый - файл существует
		$shortfile = $url.'.html';
		$tryfile = DOIT_ROOT . '/app'.$shortfile;
		
		
		 
		$trys[] = '/app'.$shortfile;
		
		if( strpos($shortfile,'/_')===false && is_file($tryfile))
		{
			return  $this->from_file($shortfile);
		}
		//Вариант третий - index.html
		if(substr($url,-1)=='/'){
			$try_url = substr($url, 0, -1 );
			$shortfile = $try_url.'/index.html';
			$tryfile = DOIT_ROOT . '/app'.$shortfile;
			
			
			$trys[] = '/app'.$shortfile;
			
			if(is_file($tryfile))
			{
				return  $this->from_file($shortfile);
			}	
		}
		
		//Вариант третий - show.html
		$try_url = substr($url, 0, strrpos($url, '/') );
		$shortfile = $try_url.'/show.html';
		$tryfile = DOIT_ROOT . '/app'.$shortfile;
		
		$trys[] = '/app'.$shortfile;
		
		if(is_file($tryfile))
		{
			return  $this->from_file($shortfile);
		}
		
		 
		
		
		return  print_error_message(' ','',$errfile ,'','Не удалось найти файл шаблона (проверялись: '.implode(', ',$trys).')'  );
	}
	
	function from_file($file){
		$name = str_replace(array('/','.','-','\\'),array('_','_','_','_'),substr($file,1)).'_tpl';
	
	
		
		
		


		if(!function_exists($name)){
			
			ob_start(); //Подавление стандартного вывода ошибок Parse Error
			$code = $this->shablonize(file_get_contents(DOIT_ROOT . '/app'.$file));
			
			$result=eval('function '.$name.'(){ $doit=Doit::$instance; ?'.'>'.$code.'<'.'?php ;} ');
			ob_end_clean();
			if ( $result === false && ( $error = error_get_last() ) ) {
 				$lines = explode("\n",'function '.$name.'(){ $doit=d(); ?'.'>'.$code.'<'.'?php ;} ');
				$file = $this->fragmentslist[$name];
				return print_error_message( $lines [$error['line']-1],$error['line'],$file,$error['message'],'Ошибка при обработке шаблона',true);
			} else {
				ob_start();
				$result =  call_user_func($name);
				$_end = ob_get_contents();
				ob_end_clean();
				if (!is_null($result)) {
					$_end = $result;
				}
				return $_end;
			}


		}else{
			ob_start();
			$result =  call_user_func($name);
			$_end = ob_get_contents();
			ob_end_clean();
			if (!is_null($result)) {
				$_end = $result;
			}
			return $_end;
		}

	
	}
	
	
	
	
	
	
	
	function __construct(){
				
		// Массив для шаблонизатора
		
		// <foreach users as user>
		$this->template_patterns[]=	'/<foreach\s+(.*?)\s+as\s+([a-zA-Z0-9_]+)>/';
		$this->template_replacements[]='<'.'?php $tmparr= $doit->$1;
		if(!isset($doit->datapool[\'this\'])){
			$doit->datapool[\'this\']=array();
		}
		array_push($doit->_this_cache,$doit->datapool[\'this\']);
if(is_string($tmparr)) $tmparr=array($tmparr);
foreach($tmparr as $key=>$subval)
	if(is_string($subval)) print $subval;else {
		$doit->key = $key;
		$doit->datapool["override"]="";
		if(is_object($subval)){
			 $doit->datapool[\'$2\']=$subval;
			 $doit->datapool[\'this\']=$subval;
			 $doit->datapool[\'override\']=$subval->override;
		}else{
		$doit->datapool[\'this\']=array();
		foreach($subval as $subkey=>$subvalue) {
		$doit->datapool[\'$2\'][$subkey]=$subvalue;
		$doit->datapool[\'this\'][$subkey]=$subvalue;
		}   }
		if ($doit->datapool["override"]!="") { print $doit->{$doit->datapool["override"]}(); } else { ?'.'>';


		//TODO: приписать if (is_object($tmparr)) $Tmparr=array($tmparr)
		// TODO: 		foreach($subval as $subkey=>$subvalue) $doit->datapool[$subkey]=$subvalue;
		//	возможно, убрать эту конструкцию

		// <foreach users>
		$this->template_patterns[]='/<foreach\s+(.*)>/';
		$this->template_replacements[]='<'.'?php $tmparr= $doit->$1;

		if(!isset($doit->datapool[\'this\'])){
			$doit->datapool[\'this\']=array();
		}
		array_push($doit->_this_cache,$doit->datapool[\'this\']);
if(is_string($tmparr)) $tmparr=array($tmparr);
foreach($tmparr as $key=>$subval)
	if(is_string($subval)) print $subval;else {
		$doit->key = $key;
		$doit->datapool["override"]="";
		if(is_object($subval)){
			 $doit->datapool[\'this\']=$subval;
			 $doit->datapool[\'override\']=$subval->override;
		}else{
		$doit->datapool[\'this\']=array();
		foreach($subval as $subkey=>$subvalue) {
		$doit->datapool[\'this\'][$subkey]=$subvalue;
		}   }
		if ($doit->datapool["override"]!="") { print $doit->{$doit->datapool["override"]}(); } else { ?'.'>';

		// {* comment *}
		$this->template_patterns[]='#{\*.*?\*}#muis';
		$this->template_replacements[]='';

		// @ print 2+2;
		$this->template_patterns[]='#^\s*@((?!import|page|namespace|charset|media|font-face|keyframes|-webkit|-moz-|-ms-|-o-|region|supports|document).+)$#mui';
		$this->template_replacements[]='<?php $1; ?>';

		
		$this->template_patterns[]='/<tree\s+(.*)>/';
		$this->template_replacements[]='<?php 
		$passed_tree_elements = array();
		$child_branch_name = "$1";
		$call_stack = array();
		$last_next = true;
		d()->level = 0;
		while (true) {
			if(is_object(d()->this)){
				$is_valid = d()->this->valid();
			}else{
				break;
			}
			if($is_valid){
				if(isset($passed_tree_elements[d()->this["id"]])){
					break;
				}
				$passed_tree_elements[d()->this["id"]]=true;
			?>';

				
		$this->template_patterns[]='/<\/tree>/' ;
		$this->template_replacements[]='<?php 
											
			 }
			
			if( isset( d()->this[$child_branch_name]) && count(d()->this[$child_branch_name])>0){
				$call_stack[] = d()->this;
				d()->this = d()->this[$child_branch_name];
				d()->level++;
				continue;
			}else{
				if(is_object(d()->this)){
					if(!d()->this->valid()){
						if( count($call_stack)>0){
							d()->this = array_pop($call_stack);
							d()->level--;
							d()->this->next();
							continue;
						}else {
							break;
						}
					}else{
						d()->this->next();
					}
					continue 1;
				}else{
 					break;
				}
			}
		} ?>';
				
    	
		
		
		// {{{content}}}
		$this->template_patterns[]='/\{{{([#a-zA-Z0-9_]+)\}}}/';
		$this->template_replacements[]='<'.'?php print $doit->render("$1"); ?'.'>';

		// <type admin> //DEPRECATED
//		$this->template_patterns[]='/<type\s+([a-zA-Z0-9_-]+)>/';
//		$this->template_replacements[]='<'.'?php if($doit->type=="$1"){ ?'.'>';

		// <content for header>
		$this->template_patterns[]='/<content\s+for\s+([a-zA-Z0-9_-]+)>/';
		$this->template_replacements[]='<'.'?php ob_start(); $doit->datapool["current_ob_content_for"] = "$1"; ?'.'>';

		// </content>
		$this->template_patterns[]='/<\/content>/';
		$this->template_replacements[]='<'.'?php  $doit->datapool[$doit->datapool["current_ob_content_for"]] = ob_get_contents(); ob_end_clean(); ?'.'>';

		// </foreach>
		$this->template_patterns[]='/<\/foreach>/' ;
		$this->template_replacements[]='<'.'?php } }
		$doit->datapool[\'this\'] = array_pop($doit->_this_cache );
		 ?'.'>';

		// </type>
		$this->template_patterns[]='/<\/type>/';
		$this->template_replacements[]='<'.'?php } ?'.'>';

		/*
		// {{helper 'parame' 'param' 'param2'=>'any'}}
		$this->template_patterns[]='/\{{([#a-zA-Z0-9_]+)\s+([a-Z0-9\s\"\\\']+)\}}/';
		$this->template_replacements[]='{{test}}>';
		*/

		$this->template_patterns[]='#\{{([\\\\a-zA-Z0-9_/]+\.html)}}#';
		$this->template_replacements[]='<'.'?php print $doit->view->partial("$1"); ?'.'>';

		// {{content}}
		$this->template_patterns[]='/\{{([#a-zA-Z0-9_]+)\}}/';
		$this->template_replacements[]='<'.'?php print $doit->call("$1"); ?'.'>';

		// {{helper param}}
		$this->template_patterns[]='/\{{([#a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)\}}/';
		$this->template_replacements[]= '<'.'?php print $doit->call("$1", array(d()->$2));  ?'.'>';

		// {{helper 'parame','param2'=>'any'}}
	##	$this->template_patterns[]='/\{{([#a-zA-Z0-9_]+)\s+(.*?)\}}/';
	##	$this->template_replacements[]='<'.'?php print $doit->call("$1",array(array($2))); ?'.'>';

		// <@helper 'parame' param2 = 'any'>
		$this->template_patterns[]='/<@([#a-zA-Z0-9_]+)\s+(.*?)>/';
		$this->template_replacements[]='<'.'?php print $doit->call("$1",array(d()->prepare_smart_array(\'$2\'))); ?'.'>';

		// {{@helper 'parame' param2 = 'any'}}
		$this->template_patterns[]='/\{{\@([#a-zA-Z0-9_]+)\s+(.*?)\}}/';
		$this->template_replacements[]='<'.'?php print $doit->call("$1",array(d()->prepare_smart_array(\'$2\'))); ?'.'>';



        // {if url()==23?}
        $this->template_patterns[]='/\{if\s(.*)\?\}/';
        $this->template_replacements[]='<'.'?php if ($doit->$1) { ?'.'>';

        // {or url()==23?}
        $this->template_patterns[]='/\{(or|elseif)\s(.*)\?\}/';
        $this->template_replacements[]='<'.'?php } elseif($doit->$1) { ?'.'>';


        // {url()==23?}
        $this->template_patterns[]='/\{(.*)\?\}/';
        $this->template_replacements[]='<'.'?php if ($doit->$1) { ?'.'>';


        // {else}
        $this->template_patterns[]='/\{else\}/';
        $this->template_replacements[]='<'.'?php } else { ?'.'>';


        // {(endif)} или {/}
        $this->template_patterns[]='/\{(endif|\/)\}/';
        $this->template_replacements[]='<'.'?php }  ?'.'>';

        // {title}
	##	$this->template_patterns[]='/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/';
	##	$this->template_replacements[]='<'.'?php print  $doit->$1; ?'.'>';

		// {:title}
		$this->template_patterns[]='/\{:([a-zA-Z0-9\._]+)\}/';
		$this->template_replacements[]='<'.'?php } ?'.'>';

		// {title:}
		$this->template_patterns[]='/\{([a-zA-Z0-9_]+):\}/';
		$this->template_replacements[]='<'.'?php if($doit->$1) { ?'.'>';

		// <if user>    //DEPRECATED
//		$this->template_patterns[]='/\<if\s([a-zA-Z0-9_]+)\>/';
//		$this->template_replacements[]='<'.'?php if($doit->$1) { ?'.'>';

		// <if user.title>    //DEPRECATED
//		$this->template_patterns[]='/\<if\s([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\>/';
//		$this->template_replacements[]='<'.'?php if((is_array($doit->$1) && $doit->$1[\'$2\']) || $doit->$1->$2) { ?'.'>';

		// {page.title}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\}/';
	##	$this->template_replacements[]='<'.'?php if(is_array($doit->$1)) {  print  $doit->$1[\'$2\']; }else{ print  $doit->$1->$2; } ?'.'>';

		//DEPRECATED
		// {page.title:}
		$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+):\}/';
		$this->template_replacements[]='<'.'?php if((is_array($doit->$1) && $doit->$1[\'$2\']) || $doit->$1->$2) { ?'.'>';

		// {.title}
		$this->template_patterns[]='/\{\.([a-zA-Z0-9_]+)\}/';
		$this->template_replacements[]='<'.'?php if(is_array($doit->this)) {  print  $doit->this[\'$1\']; }else{ print  $doit->this->$1; } ?'.'>';

		// {.title|h}
		$this->template_patterns[]='/\{\.([a-zA-Z0-9_]+)\|([a-zA-Z0-9_]+)\}/';
		$this->template_replacements[]='<'.'?php if(is_array($doit->this)) {  print  $2($doit->this[\'$1\']); }else{ print  $2($doit->this->$1); } ?'.'>';
		
		// </if> //DEPRECATED
//		$this->template_patterns[]='/\<\/if\>/';
//		$this->template_replacements[]='<'.'?php } ?'.'>';

		// {title|h}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\|([a-zA-Z0-9_]+)\}/';
	##	$this->template_replacements[]='<'.'?php print  $doit->$2($doit->$1); ?'.'>';


		// {{.image|preview 'parame','param2'=>'any'}}
	##	$this->template_patterns[]='/\{\.([a-zA-Z0-9_]+)\|([#a-zA-Z0-9_]+)\s+(.*?)\}/';
	##	$this->template_replacements[]='<'.'?php print $doit->call("$2",array(array($doit->this[\'$1\'], $3))); ?'.'>';

		// {{image|preview 'parame','param2'=>'any'}}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\|([#a-zA-Z0-9_]+)\s+(.*?)\}/';
	##	$this->template_replacements[]='<'.'?php print $doit->call("$2",array(array($doit->$1, $3))); ?'.'>';

		// {{news.image|preview 'parame','param2'=>'any'}}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\|([#a-zA-Z0-9_]+)\s+(.*?)\}/';
	##	$this->template_replacements[]='<'.'?php print $doit->call("$3",array(array($doit->$1[\'$2\'], $4))); ?'.'>';


		// {"userlist"|t}
		$this->template_patterns[]='/\{\"(.+?)\"\|([a-zA-Z0-9_]+)\}/';
		$this->template_replacements[]='<'.'?php print  $doit->$2("$1"); ?'.'>';

		// {page.title|h}
		$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\|([a-zA-Z0-9_]+)\}/';
		$this->template_replacements[]='<'.'?php if(is_array($doit->$1)) {  print  $doit->$3($doit->$1[\'$2\']); }else{ print  $doit->$3($doit->$1->$2); } ?'.'>';

		// {page.user.title}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+).([a-zA-Z0-9_]+)\}/';
	##	$this->template_replacements[]='<'.'?php print  $doit->$1->$2->$3; ?'.'>';

		// {page.user.title|h}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+).([a-zA-Z0-9_]+)\|([a-zA-Z0-9_]+)\}/';
	##	$this->template_replacements[]='<'.'?php print $doit->$4( $doit->$1->$2->$3); ?'.'>';

		// {page.parent.user.title}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+).([a-zA-Z0-9_]+).([a-zA-Z0-9_]+)\}/';
	##	$this->template_replacements[]='<'.'?php print  $doit->$1->$2->$3->$4; ?'.'>';

		// {page.parent.user.avatar.url}
	##	$this->template_patterns[]='/\{([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+).([a-zA-Z0-9_]+).([a-zA-Z0-9_]+).([a-zA-Z0-9_]+)\}/';
	##	$this->template_replacements[]='<'.'?php print  $doit->$1->$2->$3->$4->$5; ?'.'>';



		// {{/form}}
		$this->template_patterns[]='/\{{\/([a-zA-Z0-9_]+)\}}/';
		$this->template_replacements[]='</$1>';//Синтаксический сахар

		
		// {=url(0)}
		$this->template_patterns[]='/\{=(.+)\}/';
		$this->template_replacements[]='<'.'?php print  $1; ?'.'>';
		
	}
	
	
	
	
  
/* ================================================================================= */
	function shablonize($_str)
	{
		
	
		$_str   = preg_replace($this->template_patterns,$this->template_replacements,str_replace(array("\r\n","\r"),array("\n","\n"),$_str));	
		$_str = preg_replace('#{\.(.*?)}#','{this.$1}',$_str);
		$_str = preg_replace_callback( "#\{((?:[a-zA-Z_]+[a-zA-Z0-9_]*?\.)*[a-zA-Z_]+[a-z0-9_]*?)}#mui", function($matches){
			d()->matches = ($matches);
			$string = $matches[1]; //user.comments.title
			$substrings = explode('.',$string);
			$first = array_shift($substrings);
			
			$result = '<?php print $doit->'.$first . implode('',array_map(function($str){
				return "->".$str."";
			},$substrings)) . '; ?>';   //$user ['comments']  ['title']
			return $result;
		}, $_str);
			
		
		$_str = preg_replace_callback( "#\{((?:[a-z0-9_]+\.)*[a-z0-9_]+)((?:\|[a-z0-9_]+)+)}#mui", function($matches){
			d()->matches = ($matches);
			$string = $matches[1]; //user.comments.title
 
			$substrings = explode('.',$string);
			$first = array_shift($substrings);
			
			$result = '  $doit->'.$first . implode('',array_map(function($str){
				return "->".$str."";
			},$substrings)) . ' ';   //$user ['comments']  ['title']
			
			$functions = $matches[2]; //|h|title|htmlspecialchars
			$substrings = (explode('|',$functions));
			array_shift($substrings);
			$result = '<?php print  ' . array_reduce($substrings, function($all, $item){
				return '$doit->'.$item.'('. $all .')';
			}, $result) .  ' ; ?>'; 
			
			return $result;
		}, $_str);
		
		$_str = preg_replace_callback( "#\{((?:[a-z0-9_]+\.)*[a-z0-9_]+)((?:\|.*?)+)}#mui", function($matches){
			d()->matches = ($matches);
			$string = $matches[1]; //user.comments.title
 
			$substrings = explode('.',$string);
			$first = array_shift($substrings);
			
			$result = '  $doit->'.$first . implode('',array_map(function($str){
				return "->".$str."";
			},$substrings)) . ' ';   //$user ['comments']  ['title']
			
			$functions = $matches[2]; //|h|title|htmlspecialchars
			$substrings = (explode('|',$functions));
			array_shift($substrings);
			$result = '<?php print  ' . array_reduce($substrings, function($all, $item){
			
				preg_match('#([a-z0-9_]+)(\s+.*)?#',$item,$m);
				if(is_null($m[2])){
					return '$doit->'.$m[1].'('. $all .')';
				}else{
				
					$attr_params = $m[2]; //'50', '100' '200' user="10"   ===>   '50', '100', '200', 'user'=>"10"
					
					$attr_params = preg_replace('#\s+=\s+\\\'(.*?)\\\'#',' => \'$1\' ',$attr_params);
					$attr_params = preg_replace('#\s+=\s+\\"(.*?)\\"#',' => "$1" ',$attr_params);
					$attr_params = preg_replace('#([\s\$a-zA-Z0-9\\"\\\']+)=\s([\s\$a-zA-Z0-9\\"\\\']+)#','$1=>$2',$attr_params);
					$attr_params = preg_replace('#\s+([a-z0-9_]+?)\s*=>#',' \'$1\' => ',$attr_params);
					return '$doit->'.$m[1].'(array('. $all .', '. $attr_params .'))';
				}
				
			}, $result) .  ' ; ?>'; 
			
			return $result;
		}, $_str);
		

		$_str = preg_replace_callback( "/{{([#a-zA-Z0-9_]+)\s+(.*?)\}}/mui", function($matches){
			//file_put_contents('1.txt',json_encode($matches));
			$attr_params = ' '.$matches[2];
			$attr_params = preg_replace('#\s+=\s+\\\'(.*?)\\\'#',' => \'$1\' ',$attr_params);
			$attr_params = preg_replace('#\s+=\s+\\"(.*?)\\"#',' => "$1" ',$attr_params);
			$attr_params = preg_replace('#([\s\$a-zA-Z0-9\\"\\\']+)=\s([\s\$a-zA-Z0-9\\"\\\']+)#','$1=>$2',$attr_params);
			$attr_params = preg_replace('#\s+([a-z0-9_]+?)\s*=>#',' \'$1\' => ',$attr_params);
			return '<?php print $doit->call("' .$matches[1] . '",array(array('.$attr_params.')));?>';
		
		}, $_str);
		
		
		
		return $_str;
		
	}
	
	
	
	
	
	
}
