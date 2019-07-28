<?php // views/Bible/default/blocks.config.php

/**
 * code runs in View constructor: $this references the View object
 */

$this->addBlock('meta',array(
    'profile.frag',
    'head'
));

$this->addBlock('header',array(
    'banner',
    'search_bar'
));

$this->addBlock('nav_top',array(
    'results_nav'
));

$this->addBlock('main',array(
    'verses'
));

$this->addBlock('nav_bottom',array(
    'results_nav'
));

$this->addBlock('index',array(
    'book_list'
));

$this->addBlock('footer',array(
    'footer'
));
