<?php
// common functions
require_once "includes/f3_common.php";
// database function always necessary
require_once "includes/f_dbase.php";
// f3_parser required for parsing query results
require_once "includes/f3_parser.php";

if(substr_count('bible|music',$_GET['query'])) {
	// Bible query classes
	require_once 'includes/classes_query.php';
	// create html for page
	$cQuery = ucfirst($_GET['query'])."Query";
	$query = new $cQuery();
	
	if($cQuery == 'MusicQuery' || $_GET['q']) {
		$query->get_q($_GET['q'],$_GET['passage']);
	} else {
		$query->get_passage($_GET['passage']);
	}
}
if($_GET['output'] == 'xhtml') {
	parse_xml_texts($query->output);
	echo $parsed;
} elseif($_GET['output'] == 'xml') {
	echo $query->output;
}
?>