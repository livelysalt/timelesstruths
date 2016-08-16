<?php
// classes_listing_query.php
// copied from classes_listing.php
class QueryList {
	var $type;
	var $collection;
	var $title; // for browser title bar purposes, title is usually the same as collection
	
	var $sortby;
	var $order;
	
	var $prev_title = false; // previous section title
	var $next_title = false; // next section title
	var $section; // taken from the initialization call or jump menu section argument
	var $sections = array(); // array of objects listing section titles and number of items per title keyed to $sort_match
	
	var $xml;
}

class BibleQueryList extends QueryList {
	var $warning;
	
	function BibleQueryList() {
		$this->type = 'bible';
		$this->collection = $collection;
		$this->section = $section;
		global $query;
		if(isset($_GET['passage'])) {
			$this->title = $_GET['passage'].($_GET['q'] ? ' | '.$_GET['q'] : '');
			$this->sections = $query->sections;
			$this->warning = $query->warning;
		} else {
			$this->title = stripslashes(stripslashes($_GET['q']));
		}
		$this->xml = $query->output;
		
		$this->prev_title = $query->prev_title;
		$this->next_title = $query->next_title;
	}
}

class MusicQueryList extends QueryList {
	function MusicQueryList() {
		$this->type = 'music';
		$this->collection = $collection;
		$this->section = $section;
		global $query;
		$this->title = stripslashes(stripslashes($_GET['q']));
		if($_GET['start']) {
			$this->title .= ' | results '.$_GET['start'].' - '.($_GET['start'] + $_GET['results']);
		}		
		$this->warning = $query->warning;
		$this->xml = $query->output;

		$this->prev_title = $query->prev_title;
		$this->next_title = $query->next_title;
	}
}
?>