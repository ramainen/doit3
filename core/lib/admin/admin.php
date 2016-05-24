<?php
	function iam($username='')
	{
		if($username!=''){
			if(isset($_SESSION['admin']) && ($_SESSION['admin'] == $username)) {
				return true;
			}
		}else{
			if(isset($_SESSION['admin'])) {
				return true;
			}
		}
		return false;
	}
	
	if(!iam()){
		d()->get('/admin:url*',function(){
			print d()->view->partial('login.html');
		})->priority(10);
		
		d()->post('/admin:url*',function(){
			$data = ($this->request->getParsedBody());
			if($data['login'] == 'admin'){
				$_SESSION['admin']='admin';
				$this->response =  $this->response->withHeader('Location', (string)$this->request->getUri()->getPath());
			}else{
				d()->notice ="Неверный логин";
				print d()->view->partial('login.html');	
			}
			
		})->priority(10);
		
		
	}
	
	d()->route('/admin:url*',function(){
		d()->view->setLayout('_admin_layout.html');
		print d()->view->render('dashboard.html');	
		
	});	