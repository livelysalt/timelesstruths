<?php // app/Url.routes.php

// app will follow first matching route 

/**
 * bible.timelesstruths.org
 */

Url::setRouteDomain('bible.timelesstruths.org');

/*
Url::setRoute(array('query' => array('search=<query>')),
            array('redirect' => '/<query>'),
            array('named' => array(
                'search'  => '.*search',
                'query'   => '([^/]+)'
            )));
*/

Url::setRoute('/~dev',
            array('controller' => 'bible', 'action' => 'dev')
            );

Url::setRoute('/contact/',
            array('controller' => 'bible', 'action' => 'contact')
            );

Url::setRoute('/<query><start><show>',
            array('controller' => 'bible', 'action' => 'search'),
            array('named' => array(
                'query'   => '([^/]+)',
                'start'   => '(?:/start:(\d+))?',
                'show'    => '(?:/show:(\d+))?'
            )));

Url::setRoute('/',
            array('controller' => 'bible', 'action' => 'home')
            );


/**
 * music.timelesstruths.org
 */

Url::setRouteDomain('music.timelesstruths.org');

Url::setRoute('/test/',
            array('controller' => 'music', 'action' => 'home')
            );


/**
 * admin.timelesstruths.org
 */

Url::setRouteDomain('admin.timelesstruths.org');

Url::setRoute('/edit/',
            array('controller' => 'admin', 'action' => 'edit')
            );

Url::setRoute('/<action>',
            array('controller' => 'admin', 'action' => 'admin'),
            array('named' => array(
                'action'   => '([^/\?]+)'
            )));
            

/**
 * dev.timelesstruths.org
 */

Url::setRouteDomain('dev.timelesstruths.org');

Url::setRoute('/<action>',
            array('controller' => 'dev', 'action' => 'dev'),
            array('named' => array(
                'action'   => '([^/\?]+)'
            )));
            