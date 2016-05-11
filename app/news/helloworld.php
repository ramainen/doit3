<?php

d()->route('/news/',function(){
	print "Hello, world from news!";
	
});

d()->add('/test/', function($request,$response,$next){
	$response->getBody()->write('Beg1in');
	$next($request,$response);
	 
	$response->getBody()->write('End');
});