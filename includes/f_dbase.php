<?php
// F_DBASE.PHP
// DATABASE FUNCTIONS
$db = array();

function db_connect(&$db){
	// database connection
	// cwd: root -> /
	if(substr_count($_SERVER['HTTP_HOST'],'timelesstruths.org')) {
        define('TT_CONFIG_FILE', '../_config.www.php');
	} else {
        define('TT_CONFIG_FILE', '_config.dev.php');
	}

    include TT_CONFIG_FILE;

    $db = $config_db;
    
	$db['tt3_bible']       = 'tt3_bible';
	$db['tt3_kjv']         = 'tt3_kjv';
	$db['tt3_texts']       = 'tt3_texts';
	$db['tt3_music']       = 'tt3_music';
	$db['tt3_music_list']  = 'tt3_music_list';
	$db['tt3_source_list'] = 'tt3_source_list';
	$db['tt3_scores']      = 'tt3_scores';
	$db['tt3_getID3']      = 'tt3_getID3';
	$db['tt3_indices']     = 'tt3_indices';
    $db['tt3_aka']         = 'tt3_aka';
	//------------------------------------------------------------------------------------------
	$db['link'] = mysql_connect($db['host'], $db['user'], $db['pass']) or die('<small>Could not connect with database</small>');

	mysql_select_db($db['data']) or die('Could not select database');
}

function db_disconnect(&$db){
	mysql_close($db['link']);
	$db['link'] = false;
}
