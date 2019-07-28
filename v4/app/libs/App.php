<?php // app/App.php

class App {
    public $url, 
           $db,
           $controller; 

    //====================================================================================

    public function __construct() {

        $this->url = Url::get();
        
        // not a class file
        require_once('Url.routes.php');

        $this->db = Db::get();

        $this->run();

    } // __construct()

    //====================================================================================

    public function run() {
        
        $route = $this->url->matchRoute();

        if (!$route) { return; }
        
        $controllerClass = ucwords( $route['goto']['controller'] ) . 'Controller';

        // controllers cannot be autoloaded
        $classFile = 'controllers/' . $controllerClass . '.php';

        require_once($classFile);

        $this->controller = new $controllerClass($this->url);
        
        // if no matching action found in controller, send to 'not found' action
        if (!$route['goto']['action'] || !method_exists($this->controller, $route['goto']['action'])) {
            $this->controller->action404($route['goto']['action']);
        }

        // calls route action with any named params
        $this->controller->{$route['goto']['action']}($route['goto']['params']);
        
        if (!TT_VIEW) return;

        $this->controller->renderView();

    } // run()

    //====================================================================================

} // App{}
