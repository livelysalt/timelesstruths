<?php // app/Model.php

class Model {
    
    protected $db;
    
    protected $vars = array();

    public function __construct() {

        $this->db = Db::get();

    } // __construct()
    
    /*
     * provides a way for models to make variables accesible through get() 
     */
    protected function set($var,$val) {
        
       $this->vars[$var] = $val;
       
       return $this->get($var);
        
    }
    
    /*
     * provides a way for controllers and views to access model vars
     */
    public function get($var) {
        
        if (!isset($this->vars[$var])) {
            return null;
        }
        
        return $this->vars[$var];
        
    }
    
    /*
     * copied from f3_common.php
     */
    protected function title_url_format($title) {
        // replace with unaccented characters
        $title = str_replace(array('&eacute;'),
                             array('e'),
                             $title);
        // replaces music notation flats -> 'b', sharps -> 's', removes other HTML entities
        $title = preg_replace(array("'&#9837;'","'&#9839;'","'\&\#?(.*?)\;'"),array('b','s',''), $title);
    
        $title = strip_tags( stripslashes(urldecode($title)) );
        $title = preg_replace(array("' |/|[\-]+'","'[\"\'\,\.\!\?\:\(\)]'"),array('_',''),$title);
        return $title;
    }

} // Model{}
