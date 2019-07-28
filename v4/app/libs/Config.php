<?php // app/Config.php

class Config {

    public static function get($scope=null) {
            
        $config_file = TT_CONFIG_FILE;
        
        if (!file_exists($config_file)) return null;

        include($config_file);
        
        return isset(${"config_$scope"}) ? ${"config_$scope"} : null;
         
    } // get()

} // Config{}

