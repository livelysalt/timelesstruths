<?php
// classes_split.php

class SiteSplit {
	var $type;
	var $title;
	var $xml;
	
	function SiteSplit($type) {
		$this->type = $type;
		$this->title = ucfirst($type);
		if($type == 'bible' || $type == 'texts' || $type == 'music') {
			if(stream_resolve_include_path('library/'.$type.'3.php')) {
				include 'library/'.$type.'3.php';
			}
		} else {
			if(stream_resolve_include_path('www/'.$type.'3.php')) {
				include 'www/'.$type.'3.php';
			}
		}
	}
}
?>
