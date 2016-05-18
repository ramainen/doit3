<?php

 
d()->route('/:url*',function($url){
	print "Текстовая страница :".$url;
	
	// throw new Exception("Something broke!");
	//$a = new Exception("sdasd");
	$a = 2 / 0;
/*	throw new Exception('sadasd');*/
	$all = array();
		$all->sdas();
	//print 	d()->Sprite->all->sdas();
	print d()->view->render();
	//d()->http_response = d()->http_response->withHeader('sadasd','asdas');
});
