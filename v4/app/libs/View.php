<?php // app/View.php

/**    
* Copyright (c) 2003 Brian E. Lozier (brian@massassi.net)    
*    
* set_vars() method contributed by Ricardo Garcia (Thanks!)    
*    
* Permission is hereby granted, free of charge, to any person obtaining a copy    
* of this software and associated documentation files (the "Software"), to    
* deal in the Software without restriction, including without limitation the    
* rights to use, copy, modify, merge, publish, distribute, sublicense, and/or    
* sell copies of the Software, and to permit persons to whom the Software is    
* furnished to do so, subject to the following conditions:    
*    
* The above copyright notice and this permission notice shall be included in    
* all copies or substantial portions of the Software.    
*    
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR    
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,    
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE    
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER    
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING    
* FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS    
* IN THE SOFTWARE.    
*/    
   
class View {    
    public $vars       = array(), // Holds all the template variables
           $blocks     = array(), //    
           $controller = null;    // reference to owner
   
    /**
     * 
     * Constructor    
     *    
     * @param string $path the path to the templates    
     *    
     * @return void    
     */    
    public function __construct(&$controller = null) {
        if ($controller) {
            $this->controller = $controller;
            $modelName = $controller->name;
            $this->{$modelName} = &$controller->{$modelName};
        }    
        
        $config_file = $this->findFile('blocks.config.php','views');
        
        if ($config_file) include($config_file);
    } // __construct()

    /**
     * 
     */
    public function __call($name, $arguments) {
        // Note: value of $name is case sensitive.
        $class = get_class();
        $this->error("calling $class->$name(". implode(', ', $arguments). ")");
    } // __call()

    //=============================================================================================
       
    /**
     * 
     */
    public function error($msg) {
        $msg = "<!-- ERROR: {$msg} -->";
        if (TT_DEBUG) {
            echo "\r\n<pre><b>". htmlentities($msg) ."</b></pre>\r\n";
        } else {
            echo "\r\n{$msg}\r\n";
        }
    } // error()

    //=============================================================================================
       
    /**
     * 
     */
    public function findFile($file, $stem='views') {
        $controller = $this->controller->name;
        $theme      = $this->getTheme();
        
        $path_dirs = array(
            "app/{$stem}/{$controller}/{$theme}/",
            "app/{$stem}/{$controller}/default/",
            "app/{$stem}/{$theme}/",
            "app/{$stem}/default/"
        );
        foreach($path_dirs as $path_dir) {
            // if in development mode, use development version of file if available
            if (TT_DEV && file_exists($path = $path_dir . preg_replace('/app\//','app.DEV/',$file))) {
                return $path;
            }
            if (file_exists($path = $path_dir . $file)) {
                return $path;
            }
        }
        return false;
    } // findFile()

    //=============================================================================================
       
    /**
     * 
     */
    public function findFront($file) {
        if (!($path = $this->findFile($file, 'webfront'))) {
            return false;
        }
        $path = $this->relRoot() . str_replace('app/webfront/', '-/', $path);
        return $path;
    } // findFront()

    //=============================================================================================
       
    /**
     * 
     */
    public function addBlock($blockName,$blockElements=array()) {
        $this->blocks[$blockName] = new Block($blockName, $blockElements);
    } // addBlock();
   
    /**    
     * Set the path to the template files.    
     *    
     * @param string $path path to template files    
     *    
     * @return void    
     */    
    public function setPath($path) {    
        $this->path = $path;    
    } // setPath()    

    //=============================================================================================
        
    /**
     * 
     */
    public function get($name) {
        if (isset($this->vars[$name])) {
            return $this->vars[$name];
        }
        $this->error('could not find $this->vars["'.$name.'"]');
        return false;
    } // get()
    
    /**    
     * Set a template variable.    
     *    
     * @param string $name name of the variable to set    
     * @param mixed $value the value of the variable    
     *    
     * @return void    
     */    
    public function set($name, $value) {    
        $this->vars[$name] = $value;    
    } // set()    
   
    //=============================================================================================
       
    /**    
     * Set a bunch of variables at once using an associative array.    
     *    
     * @param array $vars array of vars to set    
     * @param bool $clear whether to completely overwrite the existing vars    
     *    
     * @return void    
     */    
    public function setVars($vars, $clear = false) {    
        if ($clear) {    
            $this->vars = $vars;    
        } else {    
            if(is_array($vars)) $this->vars = array_merge($this->vars, $vars);    
        }    
    } // setVars()
   
    //=============================================================================================
       
    /**
     * 
     */
    public function block($name) {
       
        $fetched = '';
        // if no block name found, assume single element was requested
        if (!isset($this->blocks[$name])) {
            $fetched = $this->fetch('elements/'. $name);
        } else {
            foreach($this->blocks[$name]->getElements() as $element) {
                $fetched .= $this->fetch('elements/'. $element);
            }
        }
        return $fetched;
       
    } // block()

    //=============================================================================================
       
    /**    
     * Open, parse, and return the template file.    
     *    
     * @param string string the template file name    
     *    
     * @return string    
     */    
    public function fetch($filename) {
        if (!substr_count($filename, '.phtml')) {
            $filename .= '.phtml';
        }

        if (!($filepath = $this->findFile($filename,'views'))) {
            $this->error("could not find <$filename>");
            return false;
        }

        extract($this->vars);            // Extract the vars to local namespace    

        ob_start();                      // Start output buffering
        include($filepath);              // Include the file    
        $contents = ob_get_contents();   // Get the contents of the buffer    
        ob_end_clean();                  // End buffering and discard
        
        if (TT_DEV) { // inserts filenames to help with debugging
            $contents =
                "\r\n<!--(BEGIN:{$filepath})-->\r\n" .
                preg_replace("/(<[^!>]+)(?=>)/","$1 data-dev-file=\"{$filepath}\"",$contents,1) .
                "\r\n<!--(END:{$filepath})-->\r\n";
        }     

        return "\r\n". $contents ."\r\n"; // Return the contents    
    } // fetch()


    //=============================================================================================
    
    public function relRoot() {
        
        $levels = substr_count($this->controller->url->path, '/') - 1;
        return ($levels ? str_repeat('../', $levels) : './');
        
    } // relRoot()

    //=============================================================================================
    
    public function getTheme() {
        
        return $this->controller->themes[0];
        
    } // relFront()
    
    //=============================================================================================
    
    public function sanitize($str,$mode) {
        
        switch($mode) {
        case 'css':
            $str = str_replace(' ','-',$str);
            break;
        }
        
        return $str;
        
    } // relFront()

    //=============================================================================================

} // View{}
