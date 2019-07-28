<?php // app/Controller.php

class Controller {

    public  $name   = null,
            $url    = null, // pointer to url object
            /**
             * $themes are an array of view type preferences, with priority in descending order (like a css font-family stack) 
             */
            $themes = array('default'),
            /**
             * $layout is the initial template to start compiling
             */
            $layout = 'default_page',
            
            $view   = null;
    
    /**
     * 
     */
    public function __construct(&$url = null) {
        
        if ($url) {
            $this->url = $url;
        }

        if ($this->name === null) {
            $r = null;
            if (!preg_match('/(.*)Controller/i', get_class($this), $r)) {
                die (__("Controller::__construct() : Cannot get or parse my own class name, exiting."));
            }
            $this->name = $r[1];
        }
        
        $modelClass = $this->name;
        
        // models cannot be autoloaded
        require_once("models/$modelClass.php");
        
        $this->{$this->name} = new $modelClass();
        
        $viewClass = $this->name . 'View';
        
        // views cannot be autoloaded
        require_once('views/' . $viewClass . '.php');
        
        $this->view = new $viewClass($this);

    } // __construct()
    
    /**
     * 
     */
    public function setThemes($themes, $toInsert = false) {
        if ($toInsert) {
            $this->themes = array_unique( array_merge((array)$themes, (array)$this->themes) );
        } else {
            $this->themes = (array)$themes;
        }
    } // setThemes()
    
    /**
     * 
     */
    public function insertThemes($themes) {
        $this->setThemes($themes,/*toInsert*/true);
    } // insertThemes()
    
    /**
     * 
     */
    public function setLayout($layout) {
        $this->layout = $layout;
    } // setLayout()
    
    /**
     * if no matching action found
     */
    public function action404($actionName) {
        
        throw new Exception("Could not find action <b>{$actionName}</b>");
        
    } // action404()
    
    /**
     * 
     */
    public function renderView() {
        
        echo $this->view->fetch('layouts/' . $this->layout);
        
    } // renderView()

} // Controller{}
