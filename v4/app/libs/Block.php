<?php // Block.php

class Block {
    
    public $name = '';
    
    public $elements = array();
    
    public function __construct($name,$elements=array()) {
        $this->name = $name;
        if (count($elements)) {
            $this->elements = $elements;
        }
    } // __construct()
    
    public function addElement($name) {
        $this->elements[] = $name;
    } // addElement()
    
    public function removeElement($name) {
        $key = array_search($name,$this->elements);
        if ($key === false) {
            echo "<!-- could not find element '{$name}' in block '{$this->name}' -->";
            return false;
        }
        unset($this->elements[$key]);
    } // removeElement()
    
    public function insertElementAt($name,$pos = 1) {
        // $pos counts from 1
        $els_following = array_splice($this->elements, ($pos - 1));
        $this->elements = array_merge($this->elements, array($name), $els_following);
    } // insertElementAt()
    
    public function insertElementAfter($name,$afterName) {
        $i = array_search($afterName, $this->elements);
        // if $afterName not found, insert new name at end of list
        if ($i === false) {
            $this->addElement($name);
            return;
        }
        $pos = $i + 2; // $i value is always 1 less than $pos
        $this->insertElementAt($name, $pos);
    } // insertElementAfter()
    
    public function insertElementBefore($name,$beforeName) {
        $i = array_search($beforeName, $this->elements);
        // if $beforeName not found, insert new name at beginning of list
        if ($i === false) {
            $i = 0;
        }
        $pos = $i; // $i value is always 1 less than $pos 
        $this->insertElementAt($name, $pos);
    } // insertElementBefore()
    
    public function getElements() {
        return $this->elements;
    } // getElements()
    
} // Block{}
