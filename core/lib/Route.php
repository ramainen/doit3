<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Route
{
	public  $method = false;
	public $url='/news/:id+';
	public $closure=false;
	public $include_directory = false;
	public $basename = ''; //Имя папки, в которой мы находимся. Также может быть задано из группы роутов при помощи d()->group
	public $priority = 1000;
	
 
	
	public function via($via=false){
		if(!is_array($via)){
			$via = array($via);
		}
		$this->method=$via;
	}
	/*
		Передает текущую директорию, из которого вызвана. Инициирует автопоиск шаблонов, автогруппы роутов.
	*/
 
	
	public function initiateAutoFind($directory)
	{
		if(Doit::$instance->_current_route_basename !== false){
			$this->basename = Doit::$instance->_current_route_basename;
		}else{
			$this->basename = '/' . basename($directory) . '/';	
		}
		$this->include_directory = $directory;
	}
	
	public function priority($level=0){
		$this->priority = strlen(preg_replace(
			array('#\:[a-z_][a-zA-Z0-9_]*\+#','#\:[a-z_][a-zA-Z0-9_]*\*#','#\:[a-z_][a-zA-Z0-9_]*#'),
			array('','','')
		,$this->url)) + $level*1000;
	}
	
	public function map($url,$closure=false)
	{
		if($closure===false){
			$closure = $url;
			$url = ':param*';
		}
		$this->url=$url;
		
		$this->priority = strlen(preg_replace(
			array('#\:[a-z_][a-zA-Z0-9_]*\+#','#\:[a-z_][a-zA-Z0-9_]*\*#','#\:[a-z_][a-zA-Z0-9_]*#'),
			array('','','')
		,$url));
		
		
		$this->closure=$closure;
	}
	
	public function check($url='/catalog', $method=false, $level="content"){
 
		
		//Добавляем путь, который начинается с текущей папки, если путь начинается не на ""/"
		if($this->url{0}!='/'){
			$this->url = $this->basename . $this->url;
		}
		$regex = $this->url;
		$regex = preg_replace(
			array('#\:[a-z_][a-zA-Z0-9_]*\+#','#\:[a-z_][a-zA-Z0-9_]*\*#','#\:[a-z_][a-zA-Z0-9_]*#')
			,array('(.+?)','(.*?)','([^\/]+?)')
		,$regex);
		if(!preg_match('#^'.$regex.'$#',$url)){
			return false;
		}
		if($method !== false && $this->method !== false){
			if(!in_array($method,$this->method)){
				return false;
			}
		}
		
		return true;
	}
	
	/*
	//DEPRECATED
	public function dispatch($url){
		$matches = array();
		$regex = $this->url;
		$regex = preg_replace(
			array('#\:[a-z_][a-zA-Z0-9_]*\+#','#\:[a-z_][a-zA-Z0-9_]*\*#','#\:[a-z_][a-zA-Z0-9_]*#'),array('(.+?)','(.*?)','([^\/]+?)')
		,$regex);
		preg_match('#^'.$regex.'$#',$url,$matches);
		unset($matches[0]);
		ob_start('doit_ob_error_handler');
		//TODO: error handler
		
		$_executionResult = call_user_func_array($this->closure,$matches);
		$_end = ob_get_contents();
		ob_end_clean();
		if (!is_null($_executionResult)) {
			$_end = $_executionResult;
		}else{
			//null; ob_start ничего не дал, return в контроллере не было
			//начинаем рулить шаблон
			if($_end == ''){
				$_end = d()->view;
		
			}
		}
		
		
		return $_end;
	}
	*/
	
	
 
	public function __invoke(Request $request, Response $response, callable $out = null)
    {

		$url = urldecode(strtok($request->getRequestTarget(),'?'));
		$matches = array();
		$regex = $this->url;
		$regex = preg_replace(
			array('#\:[a-z_][a-zA-Z0-9_]*\+#','#\:[a-z_][a-zA-Z0-9_]*\*#','#\:[a-z_][a-zA-Z0-9_]*#'),array('(.+?)','(.*?)','([^\/]+?)')
		,$regex);
		preg_match('#^'.$regex.'$#',$url,$matches);
		unset($matches[0]);
		ob_start('doit_ob_error_handler');
		//TODO: error handler
	
		Doit::$instance->request = $request;
		Doit::$instance->response = $response;
		
		call_user_func_array($this->closure,$matches);
		
		
		$request = Doit::$instance->request ;
		$response = Doit::$instance->response ;
		
		$_end = ob_get_contents();
		ob_end_clean();
		if (!is_null($_executionResult)) {
			$_end = $_executionResult;
			$response->getBody()->write($_end);
		} else {
			//null; ob_start ничего не дал, return в контроллере не было
			//начинаем рулить шаблон
			if(!d()->view->isRendered){
				if($_end == ''){
					d()->view->render();
				}else{
					d()->view->renderHTML($_end);
				}
			}
			
		}
		
		
		
		return $response;
		
		
    }
	
}
