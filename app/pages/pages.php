<?php

d()->route('/:url*',function($url,$request,$response){
	print "Текстовая страница :".$url;
	$response ->withHeader('asdas','asdsdsd2');
	
});
