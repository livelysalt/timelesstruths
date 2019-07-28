<?php // app/url.php

class Url {

    // store the single instance of object
    private static $instance;

    public $url,    // full request
           $base,   // normalized html base href
           $domain, // normalized domain
	       $path,   // normalized path
           $query,  // normalized query

           $routes = array();
           
    private static $routeDomain = 'timelesstruths.org';

    private function __construct($url=null) {
        
        if (!$url) {
            $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        // reduces multiple sequential forward slashes
        if(($fwd_url = preg_replace("'//'",'/',$url)) != $url) {
              $this->redirect($fwd_url);
        }

        $this->url = urldecode($url);
        
        //            [1                  ] [2                          ]      [3     ]     [4   ]
        preg_match("'^(". TT_LOCAL_IP ."\/)?((?:[^/]+\.)?(?:org|com|net))?" . "([^\?]*)" . "(\?.*)?$'", $this->url, $m);

        $this->base   = '//' . $m[1] . $m[2] . '/';
        $this->domain = $m[2];
        $this->path   = $m[3];
        $this->query  = $m[4];
    
        // makes sure domain is followed by /
        if (substr($this->path,0,1) != '/') {
            $this->redirect($this->url . '/');
        }
    
        // removes solitary ? from query branch
        if ($this->query == '?') {
            $this->redirect( substr($this->url,0,strlen($this->url)-1) );
        }
        
    } // __construct()

    // singleton declaration
    public static function get($url=null) {
        if (!self::$instance){ 
            self::$instance = new Url($url); 
        } 
        return self::$instance; 
    } // get()
    
    public function setRouteDomain($routeDomain) {
        self::$routeDomain = $routeDomain;
    } // setDomain()
    
    /**
     * @param string|array('domain','path') $match An array for matching against a request; if a string, assume default of 'path'
     * @param array('controller','action') $goto Specifies the action to take if match is successful
     * @param array(mixed) $params Any number of params that are used by $match (e.g., 'named') or $goto (e.g., params passed to 'action')
     */
    public function setRoute($match, $goto = array(), $params = array()) {
        $_this = self::get();
        
        if (is_string($match)) {
            $match = array(
                'domain' => self::$routeDomain,
                'path'   => $match
            );
        }

        $_this->routes[self::$routeDomain][] = array(
            'match'  => $match,
            'goto'   => $goto,
            'params' => $params
        );
        return $_this->routes;
        
    } // setRoute()

    //====================================================================================
    
    public function matchRoute() {
        
        if (!$this->routes[$this->domain]) { return false; }
        
        foreach ($this->routes[$this->domain] as $route) {
            
            $path = $route['match']['path'];
            // prefix naturally-occuring meta characters with escape character for further regex matching
            $path = preg_replace("'([\\^$.[\](){}|?*+\-])'", '\\\$1', $path);
            // substitute named patterns
            // named array list MUST be in the sequence as given in the match path in order to assign correct values later
            if (isset($route['params']['named'])) {
                foreach ($route['params']['named'] as $named => $pattern) {
                    $path = preg_replace("'<".$named.">'", $pattern, $path);
                }
            }
            // must match full path
            $pattern = "'^". $path ."$'";
//echo "<br>pattern:{$pattern}";            
            if (preg_match("$pattern", $this->path, $m)) {
//echo '<b>&lt;--</b>';                
                // remove first index, which is the full string
                array_shift($m);
                // assign patterns matches to named params
                if (isset($route['params']['named'])) {
                    foreach($route['params']['named'] as $named => $pattern) {
                        $route['goto']['params'][$named] = array_shift($m);
                    }
                }
                return $route;
            }
        }
        
        return false;
        
    }

    //====================================================================================

    public function redirect($location,$code=null) {
        if (TT_DEBUG) {
            echo "<h3>goto &raquo; $location</h3>";
            return;
        }
        if (!$code) {
            header('HTTP/1.1 301 Moved Permanently'); 
        }
        if (substr($location,0,4) != 'http') {
            $location = 'https://' . $location;
        }
        header('Location: ' . $location);

        exit;
    } // redirect()

    //====================================================================================

} // class Url