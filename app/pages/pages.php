<?php

d()->route('/:url*',function($url){
	print "Текстовая страница :".$url;
 
	// $response->getBody()->write('TT');
	//d()->http_response = d()->http_response->withHeader('sadasd','asdas');
});
