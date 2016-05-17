<?php
/*
DoIt! CMS and VarVar framework
The MIT License (MIT)

Copyright (c) 2011-2016 Damir Fakhrutdinov

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

0.19 Скаффолдинг, ArrayAccess, обработка ошибок, мультиязычность, оптимизация скорости 28.12.2011
0.11 ActiveRecord и foreach для объектов 07.08.2011
0.0 Нулевая версия DoIt CMS
	Рабочее название фреймворка Var(Var) Framework
	Система названа в честь статьи Variable Variables http://php.net/manual/en/language.variables.variable.php 26.01.2011
*/

use Psr7Middlewares\Middleware;
use DebugBar\StandardDebugBar;

class Doit
{
	public $is_using_route_all=false;
	public $callables=array();
	public $datapool=array(); //Большой массив всех опций, данных и переменных, для быстрого прямого доступа доступен публично
	public static $instance;
	private $_run_before = false;
	public $fragmentslist=array(); //Массив кода фрагментов и шаблонов.
	public $php_files_list=array(); //Массив найденных php файлов.
	private $ini_database=array(); //Названия существующих ini-файлов, а также факт их использования
	private $for_include=array(); //Массив файлов для последующего инклуда
	private $for_ini=array(); //Массив файлов для последующей загрузки
	private $url_parts=array(); //Фрагменты url, разделённые знаком '/'
	private $url_string=''; //Сформированная строка URL без GET параметров
	private $call_chain=array(); //Цепь вызовов
	private $call_chain_start=array(); //Текущая функция, корень цепочки
	private $call_chain_current_link=array(); //Текущий элемент цепочки
	private $call_chain_level=0; //текущий уровень, стек для комманд
	private $compiled_fragments=array(); //Кеш шаблонов

	private $_last_router_rule=''; //Активное правило, которое сработало для текущей функции
    public  $lang='ru'; //Текущий язык мультиязычного сайта
	public $_this_cache=array();
	public $db = NULL;
	public $db_error=false;
	private $is_root_func=false;
	private $must_be_stopped=false; //Устанавливается в true при необходимости прервать текущее выполнение
	private $_prepared_content=array();
	private $validate_disabled=false;
	public $langlink='';
	protected $_closures = array();
	public static $autoload_folders = array();
	public $current_route = false; //Последний сработавший роут

	public $_current_include_directory = ''; //Путь, в которых лежат функции-кложуры
	//Автопоиск путей для Кложур
	public $_closure_current_view_path = false; //Пути, в которых искать вьюшки. Сюда пишутся пути, вызываемые фунциями
	public $_closure_directories = array(); //Пути, в которых лежат функции-кложуры
	//Автопоиск путей для роутов
	public $_router_current_view_path = false; //Пути, в которых искать вьюшки. Сюда пишутся пути, вызываемые роутами
	public $_router_directories = array(); //Пути, в которых лежат роуты
	//group
	public $_current_route_basename=false;
	
	/*public $request = false;
	public $response = false;*/
	public $middleware_pipe = false;
	
	
	private $_routes_count;
	private $_current_route_deep;
	private $_routes_list = array();
	
	
	
/* ================================================================================= */	
	function old__construct()
	{
		self::$instance = $this;
		
		define ('ROOT',substr( dirname(__FILE__) ,0,-4));
		
		
		//тут описана работа с базой данных
		
		if(!defined('DB_TYPE')){
			define('DB_TYPE','mysql');
		}
		try {
			if(DB_TYPE == 'mysql') {
				define ('DB_FIELD_DEL','`');
				$this->db = new PDO(DB_TYPE.':host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASSWORD);
				$this->db->exec('SET CHARACTER SET utf8');
				$this->db->exec('SET NAMES utf8');
			} else {
				define ('DB_FIELD_DEL','');
				$this->db = new PDO(DB_TYPE.':host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASSWORD);
			}
			
		} catch (PDOException $e) {
			$this->db_error=$e;
			//Создание заголовки для подавления ошибок и доступа к скаффолдингу
			$this->db=new PDODummy();
		}
		

		//Обрезка GET-параметров
		$_tmpurl=urldecode($_SERVER['REQUEST_URI']);
		
        //Проверка на мультиязычность сайта
        if(substr($_tmpurl,3,1)=='/'){
            $probablyLang=substr($_tmpurl,1,2);
			//Язык /ml/ при отсуствующем файле запрещён
            if(file_exists('app/lang/'.$probablyLang.'.ini')){
                $this->load_and_parse_ini_file('app/lang/'.$probablyLang.'.ini');
                $this->lang=$probablyLang;
                $_tmpurl=substr($_tmpurl,3);
            } else{
				if(file_exists('app/lang/'.$this->lang.'.ini')){
					$this->load_and_parse_ini_file('app/lang/'.$this->lang.'.ini');
				}
			}
        }else{
			if(file_exists('app/lang/'.$this->lang.'.ini')){
				$this->load_and_parse_ini_file('app/lang/'.$this->lang.'.ini');
			}
		}
		$this->langlink='';
		if($this->lang != '' && $this->lang!='ru'){
			$this->langlink='/'.$this->lang;
		}

		$_where_question_sign = strpos($_tmpurl,'?');
		if($_where_question_sign !== false) {
			$_tmpurl = substr($_tmpurl, 0, $_where_question_sign); 
		}
		
		//приписывание в конце слешей index
		if(substr($_tmpurl,-1)=='/') {
			$_tmpurl=$_tmpurl."index";
		}
		$this->url_string = $_tmpurl;
		
		//сохранение фрагментов url
		$this->url_parts=explode('/',substr($_tmpurl,1));
		
		$_files=array();
		//сначала инициализируются файлы из ./cms, затем из ./app
		$_work_folders = array('cms','app');
		$ignore_subfolders = array('.','..','internal','external','fields','vendor');
		define('SERVER_NAME',preg_replace('/^www./i','',$_SERVER['SERVER_NAME']));
		if(file_exists($_SERVER['DOCUMENT_ROOT'].'/sites/'.SERVER_NAME)){
			$_work_folders[]='sites/'.SERVER_NAME;
		}else{
			preg_match('#(^.*?)\.#',SERVER_NAME,$m);
			$subdomain = ($m[1]);
			if(file_exists($_SERVER['DOCUMENT_ROOT'].'/sites/'.$subdomain)){
				$_work_folders[]='sites/'.$subdomain;
			}
		}
		$disabled_modules=array();
		if(defined('DISABLED_MODULES')){
			$disabled_modules=explode(',',DISABLED_MODULES);
		}
		
		$simple_folders = array();
		
		foreach($_work_folders as $dirname) {
			$_files[$dirname]['/']=array();
			$_handle = opendir($_SERVER['DOCUMENT_ROOT'].'/'.$dirname);

			while (false !== ($_file = readdir($_handle))) {
				if(substr($_file,0,4)=='mod_') {
					if(!in_array(substr($_file,4), $disabled_modules)){
						$_subhandle = opendir($_SERVER['DOCUMENT_ROOT'].'/'.$dirname.'/'.$_file);
						$_files[$dirname]['/'.$_file.'/']=array();
						while (false !== ($_subfile = readdir($_subhandle))) {
							$_files[$dirname]['/'.$_file.'/'][]=$_subfile;
						}
						closedir($_subhandle);
					}
				} elseif (is_dir($_SERVER['DOCUMENT_ROOT'].'/'.$dirname .'/'. $_file) && !in_array($_file, $ignore_subfolders) ){
					 //Модули 2.0, список директорий
					 $simple_folders[] = $dirname.'/'.$_file;
				} else {
					$_files[$dirname]['/'][]=$_file;
				}
			}
			closedir($_handle);
		}
		
		$for_include=array();
		$for_ini=array();
		$ini_files_dirs=array();
		$ini_files_local=array();
		
		foreach($_work_folders as $dirname) {

			foreach($_files[$dirname] as $_dir => $_subfiles) {
				foreach($_subfiles as $_file) {

					if ( strrchr($_file, '.')=='.html') {
						$_fragmentname = str_replace('.','_',substr($_file,0,-5));
					} else {
						$_fragmentname = str_replace('.','_',substr($_file,0,-4));
					}
					if (substr($_fragmentname,0,1)=='_') {
						$_fragmentname=substr($_dir,5,-1).$_fragmentname;
					}
					if (strrchr($_file, '.')=='.html') {
						if (substr($_file,-9)!='.tpl.html') {
							$_fragmentname .= '_tpl';
						}	
						$this->fragmentslist[$_fragmentname] = $dirname.$_dir.$_file;
						continue;
					}
					
					//Контроллер - функции для работы с данными и бизнес-логика. Работа шаблонизатора подавлена.
					if (substr($_file,-9)=='.func.php') {
						$this->for_include[$_dir.$_file]=$dirname.$_dir.$_file;
						
						continue;
					}
					if (strrchr($_file, '.')=='.php') {
						$this->php_files_list[$_fragmentname] = $dirname.$_dir.$_file;
						continue;
					}
					
					//Обработка факта наличия .ini-файлов
					if (strrchr($_file, '.')=='.ini') {
						//Правила, срабатывающие в любом случае, инициализация опций системы  и плагинов
						if (substr($_file,-8)=='init.ini') {
							//Если имя файла оканчивается на .init.ini, инициализировать его сразу
							$this->for_ini[$_dir.$_file]=($dirname.$_dir.$_file);
						} else {
							//При первом запросе адрес сбрасывается в false для предотвращения последующего чтения
							//Хранит адрес ini-файла, запускаемого перед определённой функцией //DEPRECATED

							$_dir_file=($_dir.$_file);

							
							//Реалзация приоритетов: одноимённый файл из папки app переопределит тотже из папки cms
							if(isset($ini_files_dirs[$_dir_file])){
								foreach($this->ini_database as $_key=> $_ininame){
									foreach($_ininame as $key=>$value){
										if($value==$ini_files_dirs[$_dir_file]){
											unset($this->ini_database[$_key][$key]);
										}
									}
								}
							}
							$ini_files_dirs[$_dir_file]=$dirname.$_dir.$_file;
							if(isset($this->ini_database[substr($_file,0,-4)])){
								$this->ini_database[substr($_file,0,-4)][]=$dirname.$_dir.$_file;
							}else{
								$this->ini_database[substr($_file,0,-4)]=array($dirname.$_dir.$_file);
							}
						}
						continue;
					}
				}
			}
		}
		$autoload_folders = array();
		
		
		foreach($simple_folders as $folder){
			
			
			$_handle = opendir($_SERVER['DOCUMENT_ROOT'].'/'.$folder);

			while (false !== ($_file = readdir($_handle))) {
				//ищем php файлы
				
				if (strrchr($_file, '.')=='.php' || is_dir($_SERVER['DOCUMENT_ROOT'].'/'.$folder.'/'.$_file)) {
					$fistrsim = $_file{0};
					if($fistrsim>='A' && $fistrsim<='Z'){
						//это класс
						$autoload_folders[$folder]=true;
					}else{
						$this->for_include[$folder.'/'.$_file] = $folder.'/'.$_file;
					}
				}
				
			}
			//создаём план работы над директориями и их кодом
			//PHP файлы инклудим
			//HTML файлы запоминаем
		}
		
		foreach($this->for_ini as $value) {
			$this->load_and_parse_ini_file ($value);
		}
		
		doitClass::$autoload_folders = array_keys($autoload_folders);
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
			
			foreach (doitClass::$autoload_folders as $path){
				 
				
				if(is_file($_SERVER['DOCUMENT_ROOT'].'/'. $path . '/'.$fileName  )){
					require $_SERVER['DOCUMENT_ROOT'].'/'. $path . '/'.$fileName ;
					return;
				}	
				
			}
			 

		},true,true);
		
		if(PHP_VERSION_ID > 50408) {
			$this->http_request = Zend\Diactoros\ServerRequestFactory::fromGlobals(
				$_SERVER,
				$_GET,
				$_POST,
				$_COOKIE,
				$_FILES
			);
			
			$this->http_response = new Zend\Diactoros\Response();
			
			
			$this->middleware_pipe=new Zend\Stratigility\MiddlewarePipe();
			
			
		}
		
		foreach($this->for_include as $value) {
			
			$this->_current_include_directory = dirname($_SERVER['DOCUMENT_ROOT'].'/'.$value);
			
			$this->_current_route_basename = false;
			include($_SERVER['DOCUMENT_ROOT'].'/'.$value);
			$this->_current_route_basename = false;
		}
		
		//Отрабатывает роутинг
		if($this->is_using_route_all){

			$url = $_SERVER['REQUEST_URI'];
			$uparts = array();
			preg_match_all('#\/([0-9a-zA-Z_]+)\/.*#',$url,$uparts);
			$upart_found=false;
			if(isset($uparts[1][0]) && (class_exists($uparts[1][0].'controller'))){
				//Мы находимся по адресу /users/ и у нас есть контроллер users. Строго гоовря, мы готовы.
				$sub_uparts = array();
				foreach (doitClass::$instance->datapool['urls'] as $rule){
					preg_match_all('#\^?\/([0-9a-zA-Z_]+)\/.*#',$rule[0],$sub_uparts);
					if(isset($sub_uparts[1][0]) && $sub_uparts == $uparts[1][0]){
						$upart_found=true;
						break;
					}					
				}
				if(!$upart_found){
					route($uparts[1][0]);
				}
			}
		}
		
		
		
		d()->bootstrap();
		
		if(file_exists($_SERVER['DOCUMENT_ROOT'].'/app/static') && strpos($_SERVER['REQUEST_URI'],'..')===false && $_SERVER['REQUEST_URI'] !='/'    && file_exists($_SERVER['DOCUMENT_ROOT'].'/app/static'.$_SERVER['REQUEST_URI']) && is_file($_SERVER['DOCUMENT_ROOT'].'/app/static'.$_SERVER['REQUEST_URI']) ){
			
			$this->compiled_fragments['doit_open_static_file'] = $this->shablonize(file_get_contents($_SERVER['DOCUMENT_ROOT'].'/app/static'.$_SERVER['REQUEST_URI']));
			$this->_prepared_content['main'] = $this->compile_and_run_template('doit_open_static_file');
		}
		
		if(isset($_POST['_global']) && $_POST['_global']=='1'){
			if(isset($_POST['_run_before']) && $_POST['_run_before']!=''){
				$this->_run_before = $_POST['_run_before'];
			}else{
				$this->validate_global_handlers();
			}
		}
		
		 
	}

	
	

	
	/**
	 * Проверяет данные, полученные с формы, учитывая опции валидатора и пользовательские функции. Также проверяет факт
	 * получения $_POST как такового, например, если обязательных данных нет. В случае ошибки возвращает false.
	 *
	 * @param $validator_name Имя валидатора (указывается в форме и ini-файлах валидатора).
	 * @param $params Массив параметров, пришедших с формы
	 * @param array $additional_funcs Массив дополнительных пользовательских функций для проверки
	 * @return bool true, если валидация пройдена
	 */
	public function validate_action($validator_name,$params,$additional_funcs=array())
	{
		unset($additional_funcs[0]);
		$is_ok=true;
		
		if (isset($this->validator[$validator_name])) {
			$rules=$this->validator[$validator_name];
	//		if(!isset($this->datapool['notice'])) {
				if(isset($this->datapool['notice']) && is_array($this->datapool['notice']) && count($this->datapool['notice'])>0){
					//некоторые правила были добавлены в валидатор. Остальные сработать не должны
					return false;
				}else{
					$this->datapool['notice']=array();	
				}
				$this->datapool['inputs_with_errors']=array();
	//		}

			foreach($rules as $key=>$value) {
				if($key=='function') {
					continue;
				}
				if(isset($value['required']) && (!isset ($params[$key]) || trim($params[$key])=='')) {
					$this->datapool['notice'][] = $value['required']['message'];
					$this->datapool['inputs_with_errors'][] = $key;
					$is_ok=false;
				}
				if(isset($value['confirmation']) && (!isset ($params[$key.'_confirmation']) || $params[$key.'_confirmation']!=$params[$key])) {
					$this->datapool['notice'][] = $value['confirmation']['message'];
					$this->datapool['inputs_with_errors'][] = $key.'_confirmation';
					$is_ok=false;
				}
				if(isset($value['unique'])) {
					if(isset($value['unique']['table'])) {
						$table=$value['unique']['table'];
						$model=ucfirst(d()->to_o($table));
					}
					if(isset($value['unique']['model'])) {
						$model =ucfirst($value['unique']['model']);
						$table = d()->to_p(strtolower($value['unique']['model']));
					}
					if (! d()->$model->find_by($key,$params[$key])->is_empty){
						$this->datapool['notice'][] = $value['unique']['message'];
						$this->datapool['inputs_with_errors'][] = $key;
						$is_ok=false;
					}
				}
				if(isset($value['function'])) {
					if(!is_array($value['function'])) {
						$value['function']=array($value['function']);
					}
					foreach($value['function'] as $func) {
						$rez = $this->call($func,array($params[$key]));
						if($rez===false){
							if (count($this->datapool['notice'])!=0){
								$is_ok=false;
							}
						}
					}
				}
				foreach($value as $rule => $rule_array){
					 if( !in_array($rule,array('unique','required','function','confirmation'))){
						 $rez = $this->call($rule,array($params[$key],$rule_array));
						 if($rez===false){
							 $this->datapool['notice'][] = $value[$rule]['message'];
							 $this->datapool['inputs_with_errors'][] = $key;
							 $is_ok=false;
						 }
					}
				}
			}

			//дополнительные функции с правилами для валидаторов
			if(isset($rules['function'])) {
				if(!is_array($rules['function'])) {
					$rules['function']=array($rules['function']);
				}
				foreach($rules['function'] as $func) {
					$rez = $this->call($func,array($params));
					if($rez===false){
						if (count($this->datapool['notice'])!=0){
							$is_ok=false;
							return $is_ok;
						}
					}
				}
			}
		}
		foreach($additional_funcs as $func) {
			$rez = $this->call($func,array($params));
			if($rez===false){
				if (count($this->datapool['notice'])!=0){
					$is_ok=false;
					return $is_ok;
				}	
			}
		}
		foreach($additional_funcs as $func) {
			$this->call($func,array($params));
		}
		if (count($this->datapool['notice'])!=0){
			$is_ok=false;
		}
		
		return $is_ok;
	}

		
 

	/**
	 * Добавляет сообщение об ошибке валидации формы в существующий список
	 *
	 * @param $text Текст ошибки
	 */
	public function add_notice($text,$element=false)
	{
		$this->datapool['notice'][] = $text;
		if($element!==false){
			$this->datapool['inputs_with_errors'][] = $element;
		}
		
	}	

	/**
	 * Функция принимает имя валидатора (имя формы), и в случае её корректности (пришёл $_POST, правила пройдены),
	 * запускает одноимёную функцию/метод класса посредством d()->call().
	 * Используется для указания того, что именно в этом месте должно происходить действие, и какая функция для этого
	 * действия нужна. Вызывается в контроллере до вывода представления.
	 * Может принимать дополнительные параметры - пользовательские функции-валидаторы
	 * Рекомендуется использовать другой подход, при помощи функции d()->validate()
	 * Записывает параметры формы в массив d()->params для дальнейшего использования.
	 *
	 * @param $action_name Имя функции/валидатора/формы, например send_mail или users#create
	 * @return mixed|string|void Возвращает результат отработавшей функции (как правило, HTML-код)
	 */
	public function action($action_name)
	{
		//Обработка actions. Ничего не выводится.
		//параметры, тобы передавать в action(дополнительные функции для проверки)
		$parameters = func_get_args();

		if(isset($_POST['_is_simple_names']) && $_POST['_is_simple_names']=='1'){
			$values =  $_POST;
			if(isset($_POST[$_POST['_element']]) && is_array($_POST[$_POST['_element']]) && count($_POST[$_POST['_element']])>0){
				foreach($_POST[$_POST['_element']] as $key=>$value){
					$values[$key]=$value;
				}
			}
		}else{
			$values =  $_POST[$_POST['_element']];
		}
		if(isset($_POST) && isset($_POST['_action']) && ($action_name == $_POST['_action']) && ($this->validate_action($_POST['_action'], $values,$parameters ))) {
			$this->datapool['params'] =  $values;
			return $this->call($_POST['_action'],array($_POST[$_POST['_element']]));
		}
	}

	/**
	 * Проверяет корректность правила валидации/названия формы, а также факт POST-запроса, и если все правила верны,
	 * возвращает true и заполняет массив d()->params параметрами формы
	 * В отличие от d()->action, не требует существования одноимённой функции.
	 * Использование:
	 * if(d()->validate('send_mail')) {
	 *     mail(d()->params['user'],'Письмо','Письмо');
	 * }
	 *
	 * @param $action_name Имя валидатора/формы
	 * @return bool Корректность заполненной информации
	 */
	public function validate($action_name=false)
	{
		if($this->validate_disabled){
			return false;
		}
		if($action_name===false & isset($_POST['_action']) && strpos($_POST['_action'],'#')!==false && isset($_POST['_global']) && '1' == $_POST['_global']){
			$action_name=$_POST['_action'];
		}
		$parameters = func_get_args();

				if(isset($_POST['_is_simple_names']) && $_POST['_is_simple_names']=='1'){
			$values =  $_POST;
			if(isset($_POST[$_POST['_element']]) && is_array($_POST[$_POST['_element']]) && count($_POST[$_POST['_element']])>0){
				foreach($_POST[$_POST['_element']] as $key=>$value){
					$values[$key]=$value;
				}
			}
		}else{
			$values =  $_POST[$_POST['_element']];
		}
		
		if(isset($_POST) && isset($_POST['_action']) && ($action_name == $_POST['_action']) && ($this->validate_action($_POST['_action'], $values,$parameters ))) {
			$this->datapool['params'] = $values;
			return true;
		}
		return false;
	}

	 

	/**
	 * Возвращает скомпилированный в PHP шаблон на основе HTML-файла.
	 * Исползуется ленивая (lazy) загрузка, если файл не был запрошен, он не будет загружен и обработан,
	 * если файл уже запрашивался, отдаются данные из кеша.
	 *
	 * @param $fragmentname Имя фаргмента (шаблона)
	 * @return mixed PHP-код шаблона, готовый к запуску
	 */
	function get_compiled_code($fragmentname)
	{
		if(!isset ($this->compiled_fragments[$fragmentname])) {
			return $this->compiled_fragments[$fragmentname]=$this->shablonize(file_get_contents($this->fragmentslist[$fragmentname]));
		}
		return $this->compiled_fragments[$fragmentname];
	}

	/**
	 * Функция для eval
	 *
	 * Подготавливает новую функцию для предотвращения повторных eval-ов и запускает её.
	 * По сути, имея название шаблона, eval-ит его с экономией процессорного времени.
	 *
	 * @param $name имя шаблона вида file_tpl
	 * @return void значение, полученное из шаблона при помощи return.
	 */
	function compile_and_run_template($name){
		if(!function_exists($name)){
			ob_start(); //Подавление стандартного вывода ошибок Parse Error
			$result=eval('function '.$name.'(){ $doit=d(); ?'.'>'.$this->get_compiled_code($name).'<'.'?php ;} ');
			ob_end_clean();
			if ( $result === false && ( $error = error_get_last() ) ) {
 				$lines = explode("\n",'function '.$name.'(){ $doit=d(); ?'.'>'.$this->get_compiled_code($name).'<'.'?php ;} ');
				$file = $this->fragmentslist[$name];
				return print_error_message( $lines [$error['line']-1],$error['line'],$file,$error['message'],'Ошибка при обработке шаблона',true);
			} else {
				return call_user_func($name);
			}


		}else{
			return call_user_func($name);
		}

	}




	/**
	 * Фабрика экземпляров контроллеров
	 * universal_controller_factory('clients_controller') вернёт существующий экземпляр класса clients_controller,
	 * или создаст его и вернёт. Аналог d() или doit() для пользовательских классов. Не используется напрямую,
	 * запускается автоматически при попутке запросить
	 * d()->users_controller->method()
	 *
	 * @param $name имя класса контроллера (в виде clients_controller).
	 * @return mixed
	 */
	public function universal_controller_factory($name)
	{
		static $controllers =array(); //Склад контроллеров
		if (! isset ($controllers[$name])) {
			$controllers[$name] = new  $name();
		}
		return $controllers[$name];
	}

	/**
	 * Записывает в реесто переменную для дальнейшего использования
	 *
	 * @param $name Имя переменной
	 * @param $value Значение
	 */
	function __set($name,$value)
	{
		unset($this->_closures[$name]);
		if( is_object($value) && ($value instanceof Closure)){
			$this->_closure_directories[$name] = $this->_current_include_directory;
		}
		$this->datapool[$name]=$value;
	}

	function singleton($name,$closure){
		$this->datapool[$name] = $closure;
		$this->_closure_directories[$name] = $this->_current_include_directory;
		$this->_closures[$name] = true; //является синглтоном
	}
	
	/**
	 * Получает из реестра значение переменной либо, при её отстуствии, запускает допольнитмельные функции, такие как
	 * фабрика классов, фабрика моделей d()->User, и другие, могут быть заданы в ini-файлах
	 *
	 * @param $name Имя переменной
	 * @return mixed Значение
	 */
	function &__get($name)
	{
	;
		//Одиночная загрузка .ini файла при первом обращении к переменной
		if (isset($this->ini_database[$name])) {
			$this->load_and_parse_ini_file($this->ini_database[$name]);
			
			unset ($this->ini_database[$name]);
		}
		
		if(isset($this->datapool[$name])) {
			if( is_object($this->datapool[$name]) && ($this->datapool[$name] instanceof Closure)){
				$closure = $this->datapool[$name];
				if(isset($this->_closures[$name])){ //синглтон
					$result = $closure();
					$this->datapool[$name] = $result;
					return $result;
				}
				//не синглтон, обычный контейнер
				$result = $closure();
				return $result;
			}
			return $this->datapool[$name];
		}
		
		//$fistrsim =  ord(substr($name,0,1));
		//if($fistrsim>64 && $fistrsim<91){
		if(preg_match('/^[A-Z].+/', $name)) {
			$result = new $name();
			return $result;
		}
		
		if($name!='this'){
			if(is_object($this->this)) {
				return $this->this->{$name};	
			}
			if(is_array($this->this)) {
				return  $this->this[$name];
			}			
		}
		return '';
	}


	public function __isset($name) {
		return isset($this->datapool[$name]);
	}
	 
	public function __unset($name) {
		unset($this->datapool[$name]);
	}
 
 

	/**
	 * Запускает имя_функции.tpl.html, либо пытается угадать имя текущей триады
	 * Будучи запущенной из функции d()->users_list, запускает d()->users_list_tpl(),
	 * Будучи запущенной из функции d()->users_controller->list, также запускает d()->users_list_tpl(),
	 * Предаёт управление в d()->call(), так что все переопределения разрешены.
	 *
	 * @param string|boolean $parent_function Имя функции
	 * @return mixed|string|void Результат, HTML-код
	 */
	public function view($parent_function=false)
	{
		
		
		//Определяем функцию (контроллер), из которого был произведён вызов. Припиываем _tpl, вызываем
		if($parent_function===false) {
			$parent_function =  $this->_active_function();
		}
		if(substr($parent_function,-4)!='_tpl'){
			$parent_function .= '_tpl';
		}
		$parent_function =  str_replace('#','_',$parent_function);
		return $this->call($parent_function);
	}


 

	/**
	 * Выводит значение переменной, либо, при её отсуствии, запускает соотвествующую одноимённую функцию
	 * Таким образом, если запускать d()->render('content') вместо d()->content(), можно заранее в коде переопределить
	 * content нужным нам образом, просто присвоив переменной d()->content нужное значение.
	 * Используется для того, чтобы иметь возможность переопределить основной шаблон (main_tpl) изнутри кода,
	 * выполняемого в content.
	 * Если пемеременная представляет собой массив, выводит все элементы массива подряд.
	 * Практически всегда может заменить и вывод переменной и запуск функции, для большей гибкости.
	 * Запись в шаблоне: {{{content}}}
	 *
	 * @param $str Имя переменной/функции/php-файла/контроллера-метода/html-файла
	 * @return mixed|string|void
	 */
	function render($str)
	{
		if (isset($this->datapool[$str])) {
			if (is_array($this->datapool[$str])) {
				$result='';
				foreach($this->datapool[$str] as $value) {
					$result .= $value;
				}
				return $result;
			} else {
				return $this->datapool[$str];
			}
		} else {
			return  $this->call($str);
		}
	}	

	/**
	 * Загружает ini-файл, распознаёт его, и записывает его содержимое в реестр.
	 * В случае ошибки загрузки возвращает   false.
	 *
	 * @param $filename Имя файла (начиная от корня сайта, включая app/mod*)
	 * @return bool false в случае ошибки
	 */
	function load_and_parse_ini_file($filename){
	 
		if(is_array($filename)){
			foreach($filename as $name){
				$this->load_and_parse_ini_file($name);
			}
		}
		if(!$ini=file($_SERVER['DOCUMENT_ROOT'].'/'.$filename,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) return false;
		$res=array();
		$currentGroup='';
		$arrayKeys=array();
		foreach($ini as $row) {
			$first_symbol=substr(trim($row),0,1);
			if($first_symbol==';') continue; //Комментарии строки игнорируются
			if ($first_symbol=='[') { //Начало новой группы [group]
				$currentGroup=substr($row,1,-1);
				continue;
			}
			$delimeterPos=strpos($row,'=');
			if($delimeterPos===false) {
				//Если тип строки - неименованный массив, разделённый пробелами
				$subject=$currentGroup;

				$tmparr = explode(' ',str_replace("\t",' ',$row));
				$value=array();
				$quoteflag=false;
				$tmpstr="";
				foreach ($tmparr as $val) {
					if ($val!='') {  //игнорирование двойных пробелов между значениями
						if(substr($val,0,1)=='"' && $quoteflag==false) {
							if(substr($val,-1,1)=='"') {
								$value[]=substr($val,1,-1); //Одиночное слово в кавычках
							} else {
								$tmpstr=$val;
								$quoteflag=true;
							}
						} else {
							if(substr($val,-1,1)=='"' && $quoteflag==true) {
								$tmpstr.=' '.$val;
								$value[]=substr($tmpstr,1,-1); //Кавычки закрываются
								$quoteflag=false;
							} else {
								if ($quoteflag==true) {
									$tmpstr.=' '.$val;
								} else {
									$value[]=$val;
								}
							}
						}
					}
				}
				
				if (!isset($arrayKeys[$currentGroup])) {
					$arrayKeys[$currentGroup]=0;
				}
				//Разбор пар ключ-значение
				$founded = false;
				//$value2=$value;
			/*	foreach($value as $number => $element) {
					if(substr($element,-1,1)==':') {
						$value2[substr($element,0,-1)] = $value[$number+1];
						unset($value2[$number]);
						unset($value2[$number+1]);						
					}
				}*/
				
				$value=array($arrayKeys[$currentGroup]=>$value);
				$arrayKeys[$currentGroup]++; //Генерация номера элемента массива, массив нельзя перемешивать с обычными данными
				
			} else {
				$subject= rtrim(substr($row,0,$delimeterPos));
				if ($currentGroup!='') {
					$subject = $currentGroup . '.' . $subject;
				}
				$value=ltrim(substr($row,$delimeterPos+1));
				if(substr($value,0,5) == 'json:'){
					$value = json_decode(substr($value ,5),true);
				}
			}
			if (strpos($subject,'.')===false) {
				$res=array_merge_recursive ($res,array($subject=>$value));
			} else {
				$tmpvalue=$value;
				$tmparr=array_reverse(explode('.',$subject));
				foreach($tmparr as $subSubject) {
					$tmpvalue=array($subSubject=>$tmpvalue);
				}
				$res=array_merge_recursive ($res,$tmpvalue);
			}
		}
		$this->datapool=array_merge_recursive ($this->datapool,$res);
	}
	
	function error($error_page)
	{
		if($error_page == '404'){
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); 
			header("Status: 404 Not Found");
		}
		return d()->redirect('/error_'.$error_page);
	}
	 
	
 
	function prepare_content($function,$content)
	{
		$this->_prepared_content[$function]=$content;
	}
	
 
	function prepare_smart_array($string)
	{
		$res=array();
		$res_keyvalue=array();
		$p_arr= array();
		preg_match_all('/[\'\"]?([a-zA-Z0-9_]+)[\'\"]?\s*\=\s*[\'\"](.*?)[\'\"]/i',$string,$p_arr);
		foreach($p_arr[1] as $key=>$value){
			$res_keyvalue[$value] = $p_arr[2][$key];
			$string = str_replace($p_arr[0][$key], '',$string);
		}
		$p_arr= array();
		preg_match_all('/[\'\"]([a-zA-Z0-9_]*)[\'\"]/i',$string,$p_arr);
		foreach($p_arr[1] as $key=>$value){
			$res[] = $p_arr[1][$key];
		}
		foreach($res_keyvalue as $key=>$value){
			$res[$key] = $value;
		}
		
		return $res;
	}
	
	function current_version()
	{
		static $result = null;
		if (!isset($result)) {
			$result = file_get_contents(DOIT_ROOT . '/core/VERSION.txt') || '3';
		}
		return $result;
	}

	
	/* VERSION 2.0 */
	public $routes=array();
	function route($adress, $closure=false){
		$route = new Route();
		$route->map($adress, $closure);
		$route->initiateAutoFind($this->_current_include_directory);
		$this->routes[]=$route;
		return $route;
	}
	
	function post($adress, $closure=false){
		$route = new Route();
		$route->map($adress, $closure);
		$route->method = array('POST');
		$route->initiateAutoFind($this->_current_include_directory);
		$this->routes[]=$route;
		return $route;
	}
	
	function get($adress, $closure=false){
		$route = new Route();
		$route->map($adress, $closure);
		$route->method = array('GET');
		$route->initiateAutoFind($this->_current_include_directory);
		$this->routes[]=$route;
		return $route;
	}
	
	function group($url, $closure=false){
		$this->_current_route_basename = $url;
		if($closure!==false){
			$closure();
		}
	}
	/*
	function dispatch($level='content'){
 
		//TODO: получать REQUEST_URI из $request
		$accepted_routes = array();
		$url=urldecode(strtok($_SERVER["REQUEST_URI"],'?'));
		foreach($this->routes as $route){
			if($route->check($url,$_SERVER['REQUEST_METHOD'])){
				$accepted_routes[]=$route;
			}
		}
		if(count($accepted_routes)){
			$this->current_route = $accepted_routes[0];
			$result = $accepted_routes[0]->dispatch($url);
			$this->current_route = false;
			return $result;
		}
		return false;
	}
	*/
	function new_pipe()
	{
		return new Zend\Stratigility\MiddlewarePipe();
	}
	
	function add($path, $middleware = null)
	{
		$this->middleware_pipe->pipe($path, $middleware);
	}
	
	function pipe($path, $middleware = null){
		$this->middleware_pipe->pipe($path, $middleware);
	}
	function write($text){
		$this->response->getBody()->write($text);
	}

	
	
		/**
	 * Кaллер (caller), срабатывает при всех возможных запросах вроде d()->func()
	 * Полностью передаёт управление и параметры методу d()->call()
	 *
	 * @param $name Имя функции/php-файла/щаблона
	 * @param $arguments Массив параметров для передачи функции
	 * @return mixed|string|void Результат, как правило, HTML-код
	 */
	public function __call($name, $arguments)
	{
		return 	$this->call($name, $arguments);
	}

	
	/**
	 * Вызывает методы основного класса (функции), используя всевозможные переопределения, проверки и так далее.
	 * d()->call('func') это полный аналог d()->func()
	 * @param $name Имя функции
	 * @param array $arguments Массив параметров, передаваемых в вызываемую функцию
	 * @return mixed|string|void Результат (как правило, HTML-код)
	 */
	public function call($name, $arguments=array())
	{
		
		$fistrsim = $name{0};
		if($fistrsim>='A' && $fistrsim<='Z'){
			return new $name($arguments);
		}

		//ob_start('doit_ob_error_handler');
	
		ob_start('doit_ob_error_handler');
		//Closure
		if(isset($this->datapool[$name]) && ($this->datapool[$name] instanceof Closure)) {
			//Сохраняем путь, в котором был инициирована Closure
			$this->_closure_current_view_path = $this->_closure_directories[$name];
			$_executionResult=call_user_func_array($this->datapool[$name], $arguments);
		//function
		}elseif (function_exists($name)) {
			$_executionResult=call_user_func_array($name, $arguments);
		//controller
		} else {
			$_fsym=strpos($name,'#');
			if($_fsym !== false) {
				//Вызов метода контроллера???
			} 
		}
		$_end = ob_get_contents();
		ob_end_clean();
		
		if (!is_null($_executionResult)) {
			$_end = $_executionResult;
		}
	 	return $_end;

	}
	
	
	/* END VERSION 2.0 */
		
	
	/* VERSION 3.0 */
	function __construct(){
		
		self::$instance = $this;
		
		if(!defined('DOIT_ROOT')){
			define ('DOIT_ROOT', substr( dirname(__FILE__) ,0,-9));
		}
		//server name without www
		if(!defined('DOIT_SERVER_NAME')){
			define('DOIT_SERVER_NAME',preg_replace('/^www./i','',$_SERVER['SERVER_NAME']));
		}
		
		//сначала инициализируются файлы из ./cms, затем из ./app
		$_work_folders = array('lib','app');
		if(file_exists(DOIT_ROOT.'/sites/'.DOIT_SERVER_NAME)){
			$_work_folders[]='sites/'.DOIT_SERVER_NAME;
		}else{
			preg_match('#(^.*?)\.#',DOIT_SERVER_NAME,$m);
			$subdomain = ($m[1]);
			if(file_exists(DOIT_ROOT.'/sites/'.$subdomain)){
				$_work_folders[]='sites/'.$subdomain;
			}
		}
		$disabled_modules=array();
		if(defined('DISABLED_MODULES')){
			$disabled_modules=explode(',',DISABLED_MODULES);
		}
		$ignore_subfolders = array('.','..','internal','external','fields','vendor');
		$simple_folders = array();
		foreach($_work_folders as $dirname) {
			$simple_folders[] =  $dirname ;
			$_files[$dirname]['/']=array();
			$_handle = opendir(DOIT_ROOT.'/'.$dirname);

			while (false !== ($_file = readdir($_handle))) {
				if (is_dir(DOIT_ROOT.'/'.$dirname .'/'. $_file) && !in_array($_file, $ignore_subfolders) ){
					$simple_folders[] = $dirname.'/'.$_file;
				} else {
					$_files[$dirname]['/'][]=$_file;
				}
			}
			closedir($_handle);
		}
		$for_include = array();
		$autoload_folders = array();
		foreach($simple_folders as $folder){
			$_handle = opendir(DOIT_ROOT.'/'.$folder);
			while (false !== ($_file = readdir($_handle)) ) {
				if (strrchr($_file, '.')=='.php' ) {
					$fistrsim = $_file{0};
					if($fistrsim>='A' && $fistrsim<='Z'    ){
						$autoload_folders[$folder]=true;
					}else{
						$for_include[$folder.'/'.$_file] = $folder.'/'.$_file;
					}
				}
				//Is directory name started ad uppercase letter
				if(is_dir(DOIT_ROOT.'/'.$folder.'/'.$_file)){
					$fistrsim = $_file{0};
					if($fistrsim>='A' && $fistrsim<='Z'    ){
						$autoload_folders[$folder]=true;
					}
				}
			}
		}
		

		if(count($autoload_folders)!==0){
			Doit::$autoload_folders = array_keys($autoload_folders);
		 
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
				$lover_class_name=strtolower($class_name);
				
				foreach (Doit::$autoload_folders as $path){
					if(is_file(DOIT_ROOT.'/'. $path . '/'.$fileName  )){
						require DOIT_ROOT.'/'. $path . '/'.$fileName ;
						return;
					}	
				}
				 

			},true,true);
		}
		
		//Objects initiates here
		$this->middleware_pipe=new Zend\Stratigility\MiddlewarePipe();
	
		
		
		
		
		foreach($for_include as $value) {
			
			$this->_current_include_directory = dirname(DOIT_ROOT.'/'.$value);
			
			$this->_current_route_basename = false;
			include(DOIT_ROOT.'/'.$value);
			$this->_current_route_basename = false;
		}
		
		
	}
	//Пока использовать нельзя.
	function next(){
		die('Функция устарела, надо переписать её начисто');
		/*$request = d()->request;
		$response = d()->response ; 
		
		if($this->_routes_count - 1 > $this->_current_route_deep){

			$current_route  = $this->current_route;
			$this->_current_route_deep++;
			$this->current_route = $this->_routes_list[$this->_current_route_deep];
			
			$current_route = $this->_routes_list[$this->_current_route_deep];
			d()->response = $current_route($request, $response);
			$this->current_route = false;
				
			$this->current_route = 	$current_route ;
			return $response;
		}*/
	}
	
	function runApplication(){
		

		//
 
		$request = Zend\Diactoros\ServerRequestFactory::fromGlobals(
			$_SERVER,
			$_GET,
			$_POST,
			$_COOKIE,
			$_FILES
		);
		
		$response =  new Zend\Diactoros\Response();
		
		$response = $response->withHeader('Content-type','text/html; Charset=UTF-8');
	 
	 
	 
		
		$this->singleton('view',function(){
			return new View;
		});
		
		
		
		
		
		//print print_error_message('Укажите верные настройки базы данных в файле config.php','','' , d()->db_error->getMessage(),'Ошибка при подключении к базе данных ' );		
		
		//Connecting to database, setting up options.
		//Creating d()->db variable
 
		try {

			if($_ENV['DB_CONNECTION'] == 'mysql') {
				define ('DB_FIELD_DEL','`');
				$this->db = new PDO($_ENV['DB_CONNECTION'].':host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_DATABASE'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
				$this->db->exec('SET CHARACTER SET utf8');
				$this->db->exec('SET NAMES utf8');
			} else {
				define ('DB_FIELD_DEL','');
				$this->db = new PDO($_ENV['DB_CONNECTION'].':host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_DATABASE'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
			}
			
		} catch (PDOException $e) {
		
			header('Content-type: text/html; Charset=UTF-8');
			print print_error_message('Укажите верные настройки базы данных в файле config.php, .env или в переменных окружения','','' , $e->getMessage(),'Ошибка при подключении к базе данных ' );		
			exit;
		}
		
		
		
		if($_ENV['APP_DEBUG'] == true){
			$this->middleware_pipe->pipe(Middleware::FormatNegotiator());     	
			$debugBar = new StandardDebugBar();
			$debugBar->getJavascriptRenderer()->setOptions(array('base_url'=>'/core/vendor/maximebf/debugbar/src/DebugBar/Resources/'));
			$this->db = new DebugBar\DataCollector\PDO\TraceablePDO($this->db);
			$debugBar->addCollector(new DebugBar\DataCollector\PDO\PDOCollector($this->db));
			
			$debugBarMiddleware = Middleware::DebugBar($debugBar)  ;
			
			
			
			
			$this->middleware_pipe->pipe($debugBarMiddleware); 
		}
	 
	 //(optional) To send data in headers in ajax);
	     //(optional) To send data in headers in ajax);
		
		$this->middleware_pipe->pipe(function($request, $response, $next){
			//Running code here
			
			$accepted_routes = array();
			$url=urldecode($request->getUri()->getPath());
			foreach($this->routes as $route){
				if($route->check($url,$request->getMethod())){
					$accepted_routes[]=$route;
				}
			}
			
			usort($accepted_routes, function($a, $b){
				return ($a->priority > $b->priority) ? -1 : 1;
			});
			
			$this->_routes_list = $accepted_routes;
			if(count($this->_routes_list)){
				$this->_routes_count = count($this->_routes_list);
				$this->_current_route_deep =0;
				
				$this->current_route = $this->_routes_list[0];
				$current_route = $this->_routes_list[0];
				$response = $current_route($request, $response);
				$this->current_route = false;
				return $response;
			}
			
		});
		if($_ENV['APP_ENV'] == 'local'){
			$this->middleware_pipe->pipe(new \Franzl\Middleware\Whoops\ErrorMiddleware);      //(optional) To send data in headers in ajax);
		}
		$pipe = $this->middleware_pipe;
		 
		$response = $pipe($request, $response);
			
		 
		foreach ($response->getHeaders() as $name => $values) {
			foreach ($values as $value) {
				header(sprintf('%s: %s', $name, $value), false);
			}
		}
		$exec_time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
		header("X-Core-Runtime: {$exec_time}s, ". memory_get_usage(true).'b');
		print $response->getBody();
		
	}
	
}



 