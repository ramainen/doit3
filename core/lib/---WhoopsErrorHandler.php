<?php // WhoopsMiddleware.php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Stratigility\ErrorMiddlewareInterface;

class WhoopsErrorHandler implements ErrorMiddlewareInterface
{
    protected $whoops;

    public function __construct()
    {
        $this->whoops = new Run;
        $this->whoops->pushHandler(new PrettyPageHandler);
        $this->whoops->register();
    }

    public function __invoke($error, Request $request, Response $response, callable $next = null)
    {
		
        $method = Run::EXCEPTION_HANDLER;
        ob_start();
        $this->whoops->$method($error);
        return new HtmlResponse(ob_get_clean(), 500);
    }
}