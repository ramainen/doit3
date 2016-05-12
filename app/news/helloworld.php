<?php

d()->route('/news/',function(){
	print "Hello, world from news!";
	
	
	
	
	d()->response = d()->response->withHeader('sadasd','asdas');
	
	
	d()->response->getBody()->write('TT');
	
	d()->next();
	
	print "Конец новостей";
	
	
});

d()->add('/test/', function($request,$response,$next){
	$response->getBody()->write('Beg1in');
	
	$response = $response->withHeader('sadasd','asdas');
	$response = $next($request,$response);
	 
	$response->getBody()->write('End');
	
	return $response;
	
});