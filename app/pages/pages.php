<?php

d()->papa = function($a){
		print 3;
	return 2+$a;
};

d()->route('/:url*',function($url){
	print "Текстовая страница :".$url;
	
	var_dump(d()->papa(13));
	
	d()->response = d()->response->withHeader('pages','asdas');
	
	print d()->view->render();
	//d()->http_response = d()->http_response->withHeader('sadasd','asdas');
});
