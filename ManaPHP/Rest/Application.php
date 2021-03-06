<?php

namespace ManaPHP\Rest;

use ManaPHP\Http\Response;
use ManaPHP\Router\NotFoundRouteException;

/**
 * Class ManaPHP\Mvc\Application
 *
 * @package application
 * @property-read \ManaPHP\Http\RequestInterface   $request
 * @property-read \ManaPHP\Http\ResponseInterface  $response
 * @property-read \ManaPHP\RouterInterface         $router
 * @property-read \ManaPHP\Mvc\DispatcherInterface $dispatcher
 * @property-read \ManaPHP\Http\SessionInterface   $session
 * @property-read \ManaPHP\AuthorizationInterface  $authorization
 */
class Application extends \ManaPHP\Application
{
    /**
     * Application constructor.
     *
     * @param \ManaPHP\Loader $loader
     */
    public function __construct($loader = null)
    {
        ini_set('html_errors', 'off');
        parent::__construct($loader);
        $routerClass = $this->alias->resolveNS('@ns.app\Router');
        if (class_exists($routerClass)) {
            $this->_di->setShared('router', $routerClass);
        }

        $this->attachEvent('actionInvoker:beforeInvoke', [$this, 'authorize']);
    }

    public function getDi()
    {
        if (!$this->_di) {
            $this->_di = new Factory();
        }

        return $this->_di;
    }

    public function authenticate()
    {
        $this->identity->authenticate();
    }

    public function authorize()
    {
        $this->authorization->authorize();
    }

    public function handle()
    {
        try {
            $this->authenticate();

            if (!$this->router->handle()) {
                throw new NotFoundRouteException(['router does not have matched route for `:uri`', 'uri' => $this->router->getRewriteUri()]);
            }

            $controllerName = $this->router->getControllerName();
            $actionName = $this->router->getActionName();
            $params = $this->router->getParams();

            $ret = $this->dispatcher->dispatch($controllerName, $actionName, $params);
            if ($ret !== false) {
                $actionReturnValue = $this->dispatcher->getReturnedValue();
                if ($actionReturnValue instanceof Response) {
                    null;
                } else {
                    $this->response->setJsonContent($actionReturnValue);
                }
            }
        } catch (\Exception $exception) {
            $this->handleException($exception);
        } catch (\Error $error) {
            $this->handleException($error);
        }

        if (isset($_SERVER['HTTP_X_REQUEST_ID']) && !$this->response->hasHeader('X-Request-Id')) {
            $this->response->setHeader('X-Request-Id', $_SERVER['HTTP_X_REQUEST_ID']);
        }

        $this->response->send();
    }

    public function main()
    {
        $this->dotenv->load();
        $this->configure->load();

        $this->registerServices();

        $this->handle();
    }
}