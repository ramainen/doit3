<?php

 
d()->route('/:url*',function($url){
	print "Текстовая страница :".$url;
	
	// throw new Exception("Something broke!");
	//$a = new Exception("sdasd");
	$a = 2 / 0;
/*	throw new Exception('sadasd');*/
$a = d()->Page;
print $a->title;


$a = d()->Page;
print $a->title;
print $a->zeegunda;

$a = d()->Page->where('sort >  1 or zere > 2');
print $a->title;
	//print 	d()->Sprite->all->sdas();
	print d()->view->render();
	//d()->http_response = d()->http_response->withHeader('sadasd','asdas');
});
