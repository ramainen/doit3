<?php

d()->route('/news/',function(){
	
	print "(news:1)";
	
	
	print d()->view->render('view.html');
	
	d()->response = d()->response->withHeader('sadasd','asdas');
	
	
	d()->response->getBody()->write('(news:2)');
	
	
	
	print "(news:3)";
	
	
});

d()->add('/test/', function($request,$response,$next){
	$response->getBody()->write('Beg1in');
	
	$response = $response->withHeader('sadasd','asdas');
	$response = $next($request,$response);
	 
	$response->getBody()->write('End');
	
	return $response;
	
});


d()->route('/news/bobo',function(){
	//print 2+2;
	print d()->view->render();
});