<?php // app/views/BibleView.php

class BibleView extends View {
    
    /**
     * Takes an object and an array of properties,
     * and returns an HTML class string containing all the properties whose value is set
     * @param $object (object)
     * @param $props (array)
     */
    public function getClassFrom($object,$props=array()) {
        
        $class = '';
        foreach($props as $prop) {
            if (isset($object->$prop)) {
                $class .= ' '. str_replace('_', '-', $prop);
            }
        }
        return $class;
        
    } // getClassFrom()
    

} // BibleView{}
