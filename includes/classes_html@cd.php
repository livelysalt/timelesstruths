<?php
// basic class includes variable for storing markup
class HTML {
	var $html = '';
	// other variables may be added by extending classes
}

class Page extends HTML {
	var $document;
	var $parsed; // used by parser_xml_texts
	
	var $h_meta;
	var $h_body;
	var $h_globalnav;
	var $h_subnav;
	var $h_header;
	var $h_navbar;
	var $h_content;
	var $h_sidetable;
	var $h_score;
	var $h_source;
	var $h_footer;
	
	function Page($type, $title, $section) {
		if($type == 'music' && $title) {
			if($title && !is_collection($title)) {
				$this->document = new MusicDoc($title,$section);
			} elseif($title) {
				$this->document = new MusicListing($title,$section);
			}
		} elseif(($type == 'texts' || $type == 'help') && $title) {
			if($title && !is_collection($title)) {
				$this->document = new TextDoc($type,$title,$section);
			} elseif($title) {
				$this->document = new TextsListing($title,$section);
			}
		} elseif(($type == 'bible') && $title) {
			if($title && !is_collection($title)) {
				$this->document = new BibleDoc($type,$title,$section);
			} elseif($title) {
				$this->document = new BibleListing($title,$section);
			}
		} elseif($_GET['query'] == 'bible') {
			$this->document = new BibleQueryList();
		} elseif(!$title) {
			$this->document = new SiteSplit($type);
		}

		$this->h_meta = new hMeta($type,$section,$this->document);
		$this->h_body = new hBody($type);
		$this->h_globalnav = new hGlobalNav($type);
		$this->h_subnav = new hSubNav($type,$this->document->collection);
		if(get_parent_class($this->document) && substr_count( 'document|listing|querylist',get_parent_class($this->document) )) {
			// header and navigation bar only required in certain situations
			$this->h_header = new hHeader($type,$section,$this->document);
// @CD does not support queries			
			if(get_parent_class($this->document) != 'querylist') {
				$this->h_navbar = new hNavBar($type,$section,$this->document);
			}
// ^^^			
		}
		
		$this->h_content = new hContent($type,$title,$section,$this->document);
		
		// side table only required for site pages
		if	( get_class($this->document) == 'sitesplit'
			||	( get_parent_class($this->document) == 'document'
				// music document pages, except for score
				&&	(	($type=='music' && substr($section,0,5) != 'score')
					// document TOCs
					||	(substr_count('/bible/texts/help/','/'.$type.'/') && !$section)
					)
				)
			// collection indices
			||	( get_parent_class($this->document) == 'listing'
				&&	is_collection($title)
				&&	!$section
				)
			) {
			$this->h_sidetable = new hSideTable($type,$section,$this->document);
		}
		if( ($type=='music' && get_parent_class($this->document)=='document' && substr($section,0,5)=='score') ) {
			// score only required in specific situations
			$this->h_score = new hScore($title,$section);
		}
		if($type=='music' && substr($section,0,5) != 'score' && get_parent_class($this->document)=='document') {
			// source only required in music documents
			$this->h_source = new hSource($this->document->source);
		}
// @CD footer used on sorting pages
		if( (get_parent_class($this->document) != 'document' && get_parent_class($this->document) != 'listing')
		 || $this->document->sortby){
// ^^^
			// footer only required when navbar is absent
// @CD footer always present
//			if(get_parent_class($this->document) != 'querylist') {
				$this->h_footer = new hFooter($type,$title);
//			}
// ^^^
		}
	}
	
	function output($method="return") {
		$this->stitch();
		if($method=="return") {
			return $this->html;
		}
	}
	function stitch() {
		$this->html .= $this->h_meta->html;
		$this->html .= $this->h_meta->parse($this->document);
		$this->html .= $this->h_body->pre;
		$this->html .= $this->h_globalnav->html;
		$this->html .= $this->h_subnav->html;
		$this->html .= $this->h_header->html;
		$this->html .= $this->h_navbar->pre;
		$this->html .= $this->h_content->pre;
		$this->html .= $this->h_sidetable->pre;
		$this->html .= $this->h_sidetable->html;
		$this->html .= $this->h_sidetable->post;
		$this->html .= $this->h_content->html;
		
		$this->html .= $this->h_content->parse($this->document);
		$this->html .= $this->h_sidetable->final;
		$this->html .= $this->h_content->post;
		$this->html .= $this->h_score->html;
		$this->html .= $this->h_source->html;
		$this->html .= $this->h_navbar->post;
		$this->html .= $this->h_footer->html;
		$this->html .= $this->h_body->post;
	}
}

class hMeta extends HTML {
	function hMeta($type,$section,&$document,$meta="A free online library of books, music, magazines, and more.") {
		//if the current score has an associated author name, add to author line (identical code for page TITLE and document info)
		$score_author = false;
		if(substr($section,0,5)=='score' || substr($section,0,4)=='midi') {
			$score_id = (substr($section,6) ? substr($section,6) : '0'); // '0' is the default score with no extension, and is only needed for sorting purposes if there are more than one score to a music title
			if($document->score[$score_id]->author) {
				$score_author = preg_replace("'^.*?>(.*?)</author>[\s\S]*$'","$1",$document->score[$score_id]->author); // matches the first author given
				$meta = $score_author;
				$score_author = ' / '.$score_author;
			}			
		} elseif($type == 'music' && $section == '') {
			$meta = preg_replace("'^.*?>(.*?)</author>[\s\S]*$'","$1",$document->author); // matches the first author given
		}

		$medialine = false;
		$s_media = ($section ? substr($section,0,4) : 'none');
		if($type=='music' && $document->collection && !is_collection(title_url_format($document->title))) {
			$arrDescrip = array(
				'scor' => 'Score Sheet Music',
				'midi' => 'MIDI',
				'hifi' => 'High Quality MP3',
				'lofi' => 'Dial-Up Quality MP3',
				'lowm' => 'Dial-Up Quality WMA',
				'none' => 'Lyrics'
								) ;
			if(!array_key_exists($s_media,$arrDescrip)) {
				header('Location: http://'. NORMALIZED_DOMAIN .'music/'.title_url_format($document->title).'/');
				exit;
			}
			$document->description = $arrDescrip[$s_media];
			$medialine = ' > ' . $document->description;
			$meta = $document->description .': '. $meta;
		}
		if(!$document->author && !$title) { $document->author = 'Timeless Truths Free Online Library'; }
		if( is_collection(title_url_format($document->title)) ) {
			if(!$document->section_real) { $document->section_real = 'All'; }
			$titleline = (!$document->section ? $document->title.' | ' : $document->section_real.' | '.$document->title).' sorted by '.$document->sortby;
		} elseif($type == 'music') {
			$titleline = $document->title.$medialine.' | '.preg_replace("'^.*?>(.*?)</author>[\s\S]*$'","$1",$document->author).$score_author;
		} else if(substr_count('bible|texts|help',$type)) {
			$section_line = ' > '.($type == 'bible'
				? (($bible_id = $document->parts[$document->this_pNum]->sections[$document->this_sNum]->id) && $document->title == 'Psalms' ? 'Psalm ' : 'Chapter ').$bible_id
				: strip_tags($document->parts[$document->this_pNum]->sections[$document->this_sNum]->title) );
			// don't try to add section name to title bar if displaying Whole Document or no section name is available
			$titleline = $document->title.($document->this_pNum === false || !isset($document->this_pNum)? '' : $section_line).' | '.preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$document->author);
		} elseif($_GET['query']) {
			$titleline = $document->title;
		} else {
			$titleline = ($document->title == 'Welcome' ? '' : $document->title.' | ').'Timeless Truths Free Online Library';
		}
		$this->html = '
		<?xml version="1.0"?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
		
		<head>
			<title>'.$titleline.'</title>
			<meta name="description" content="'.$meta.'" />
			<meta http-equiv="content-type" content="text/html; charset=utf-8" />
			<meta http-equiv="Content-Style-Type" content="text/css" />
			<link rel="shortcut icon" href="'.level_url_webfront().'timeless.ico" />
<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_position.css" media="all" />
<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_font.css" media="all" />
<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_color.css" media="screen, projection" />
<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_print.css" media="print" />
<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_cd.css" media="screen, projection" />';
/* ^^^ @CD stylesheet */
		global $url_bits;
		if(substr_count('//texts/music/','/'.$url_bits[0].'/') && !$url_bits[1]) {
			$this->html .= '
			<link rel="alternate" title="Timeless Truths - Recent '.($url_bits[0] == '' ? 'Additions' : ucfirst($url_bits[0])).'" href="'.level_url().'feeds/rss2/'.($url_bits[0] != '' ? $url_bits[0].'/' : '').'" type="application/rss+xml" />';
		}
	}
	
	function parse(&$document) {
/* @CD no rel links	
		$html = ($document->type != 'welcome' ? '
<link rel="start" title="Library Welcome Page" href="'.level_url().'" />' : '' ).'
<link rel="help" title="Help" href="'.level_url().'help/" />
<link rel="search" title="Search" href="'.level_url().'search/" />'.(substr_count("textlist|musiclist|textdoc",get_class($document)) ? '
<link '.(get_class($document) == 'textdoc' ? 'rel="contents first" title="Table of Contents"' : 'rel="index first" title="Index"').' href="?section=" />' : '').($document->prev_link ? '
<link rel="prev" title="Previous Page: '.strip_tags($document->prev_title).'" href="'.$document->prev_link.'" />' : '').($document->next_link ? '
<link rel="next" title="Next Page: '.strip_tags($document->next_title).'" href="'.$document->next_link.'" />' : '').'
';
*/// ^^^
		if($document->type == 'search' || $_GET['query']) {
			$html .= "\r\n\r\n"
			.'<script language="JavaScript" type="text/javascript">'
			.'<!--'."\r\n"
			.'function jump2search() { document'.($_GET['query'] ? '.bible_query.passage' : '.search.q').'.focus(); }'
			.'//-->'."\r\n"
			.'</script>';
		}
		// if page is going to includes notes, add notes script
		if(substr_count($document->xml,'<note') || ($document->section == '' && substr_count($document->xml,'class="tab_open"'))) {
			$html .= '
<script src="'.level_url_webfront().'js_popins.js" type="text/javascript"></script>'
// base functions loaded last to make sure accessory functions are present
.'<script src="'.level_url_webfront().'js_base.js" type="text/javascript"></script>
';
		}
		
		$html .= "\r\n"
		.'</head>
		';
		return $html;
	}
}

class hBody extends HTML {
	var $pre;
	var $post;
	function hBody($type) {
		if(substr_count("bible|texts|music|welcome",$type)) {
			$color = "green";
		} else {
			$color = "blue";
		}
// @CD requires js for automated cache creation
		global $cd_ffw;
		$this->pre = "\r\n"
		.'<body class="'.$color.'"'.($type == 'search' || $_GET['query'] ? ' onload="jump2search();"' : '').($cd_ffw ? ' onload="settime();"' : '').'><div id="column">';
// ^^^		
		$this->post = "\r\n"
		.'</div></body>'
		."\r\n".'</html>';
	}
}

class hGlobalNav extends HTML {
	function hGlobalNav($selected=false) {
// @CD version div
		$this->html = '
		<div id="cd-version">CD Edition: '.date("F j, Y", time()).'</div>
		<div id="logo"><a href="http://library.timelesstruths.org" accesskey="1" alt="http://library.timelesstruths.org" title="http://library.timelesstruths.org"><span>Timeless Truths Free Online Library</span></a></div>'
// ^^^		
		.(substr_count('bible|texts|music',$selected) ? '<div class="inprint block byline">Timeless Truths Free Online Library | books, sheet music, midi, and more</div>' : '')
		."\r\n".'<a class="skipnav" href="#content" accesskey="2">Skip over navigation</a>'
		.'
		<div id="globalnav">
			<div id="tab-bible"'.($selected=='bible' ? ' class="current"' : '').'><a href="'.level_url().'bible/">Bible</a></div>
			<div id="tab-texts"'.($selected=='texts' ? ' class="current"' : '').'><a href="'.level_url().'texts/">Texts</a></div>
			<div id="tab-music"'.($selected=='music' ? ' class="current"' : '').'><a href="'.level_url().'music/">Music</a></div>
			<div id="site-links"><a href="'.level_url().'">Welcome</a> | <a href="'.level_url().'about/">About Us</a> | <a href="'.level_url().'search/">Search</a> | <a href="'.level_url().'help/">Help</a></div>
		</div>
		';
	}
}

// creates the site subnavigation bar with a list of the current ancestor category and siblings, if applicable
class hSubNav extends HTML {
	function hSubNav($type,$music_coll) {
		$this->html = "\r\n\t".'<div class="navbar subnav"><p>';
		if($type == "bible") {
			$this->html .= $this->list_colls($type,array("Old Testament","New Testament"),$music_coll);
		} elseif($type == "texts") {
			$this->html .= $this->list_colls($type,array("Foundation Truth","Treasures of the Kingdom","Dear Princess","Books","Articles"),$music_coll);
		} else if($type == "music") {
			$this->html .= $this->list_colls($type,array("Select Hymns","Evening Light Songs","Echoes from Heaven Hymnal","The Blue Book","Sing unto the Lord"),$music_coll);
		} else if($type == "help") {
			$this->html .= $this->list_colls($type,array("Help"),$music_coll);
		} else {
			// javascript date should subtract from UTC 8 hours during PST, and 7 hours during PDT ----------------v
			$this->html .= '<script type="text/javascript">var d=new Date();dMil=d.getUTCMilliseconds();dMil=dMil-(8*60*60*1000);d.setUTCMilliseconds(dMil);var monthname=new Array("January","February","March","April","May","June","July","August","September","October","November","December");document.write(monthname[d.getUTCMonth()] + " ");document.write(d.getUTCDate() + ", ");document.write(d.getUTCFullYear());</script>';
		}
		$this->html .= "\r\n\t".'</p></div>';
	}
	
	// takes a list of category siblings and highlights the current one
	function list_colls($type,$array,$music_coll) {
		if($array[0] == 'Help') { // at present Help is too small to have a full 3-level heirarchy, so the listing level is ommited
			$html = "\r\n"
				.'<a class="current" href="'.level_url().'help/">Help</a>';
		} else {
			$music_coll = str_replace('_',' ',$music_coll);
			$bible_x = ($type == 'bible' ? '_/' : ''); // Bible collections currently use shorcut to full indices
			if($type != 'help') {
/* @CD does not include url arguments
				$query = add_query();
*/// ^^^
// @CD does not show indices
				$html = "\r\n\t\t\t"
					.'<a'.($music_coll == ' ' ? ' class="current"' : '')
					.' href="'.level_url().$type.'/_/_/'.$query.'">All</a>';
// ^^^					
			}
			foreach($array as $collection) {
/* @CD does not include url arguments
				$query = add_query();
*/// ^^^
				preg_match("'$collection\:([^:/]*?)/'",$music_coll,$cm);
				$coll_display = preg_replace("'(Echoes from Heaven) Hymnal'","$1",$collection);
				$html .= "\r\n"
					.'| <a'.(substr_count($music_coll,$collection) ? ' class="current"' : '')
// @CD does not show indices
					.' href="'.level_url().$type.'/'.title_url_format($collection).'/_/'
// ^^^					
					.$query.'"'
					.($cm[1] ? ' title="#'.$cm[1].'"' : '')
					.'>'.$coll_display.'</a>';
			}
		}
		return $html;
	}
}

class hFooter extends HTML {
	function hFooter($type,$title) {
		$this->html = '
		<div class="navbar footer"><p>
		Site by LivelySalt. <a href="'.level_url().'help/Management_and_Policies/Copyrights/">Copyright</a> &copy; 2002-2006 Timeless Truths Publications. Hosted by <a href="http://ibiblio.org">ibiblio</a>.
		</p></div>
		';
	}
}

class hHeader extends HTML {
	function hHeader($type,$section,&$document) {
		if(get_parent_class($document)=='document') {
			//lists only primary author
			$author = preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$document->author);
			//if the current score has an associated author name, add to author line (identical code for page TITLE and document info)
			if(substr_count('/scor/midi/hifi/lofi/lowm/','/'.substr($section,0,4).'/')) {
				$score_id = (substr_count($section,'_') ? preg_replace("'^.+_'",'',$section) : '0'); // '0' is the default score with no extension, and is only needed for sorting purposes if there are more than one score to a music title
				if($document->score[$score_id]->author) {
					$score_author = ' / '.preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$document->score[$score_id]->author);
				}
			}
			// if not at score already, gets score data, if any, for links
			if($type=='music' && substr($section,0,5)!='score') {
				foreach($document->score as $s_id => $score) {
					if($score->sib) {
						// create tooltip-compatible 'title|primary-author' string
						$tooltip = ($score->title ? $score->title.' | ' : '').preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$score->author);
						$score_links .= "\r\n\t\t".'<div class="score_link"><a href="'.level_url().'music/'
						// append score id, if any, to link
						.title_url_format($document->title).'/score'.($score->id ? '_'.$score->id : '')
						.'/" title="'.title_tooltip_format($tooltip).'">'
						.'<img src="'.level_url_webfront().'link_score.gif" alt="'.title_tooltip_format($tooltip).'" /></a></div>';
					}
				}
			}
			// if not at music > lyrics, or text > table of contents, provide link
			if($section != '') {
				$title_link =
					'<a href="'.level_url().$type.'/'.title_url_format($document->title).'/'
					.'" title="'.($type=='music' ? 'Show Lyrics' : 'Table of Contents').'">';
			}
			$this->html = 
			"\r\n\t".'<div class="header">'
				.($type=='music' ? $score_links : '')
				."\r\n\t\t".'<div class="title">'
					.($title_link ? $title_link : '<span class="current">')
					.$document->title
					.($title_link ? '</a> ' : '</span> ')
					.'| <span>'.$author.$score_author.'</span>
				</div>
				<div class="subject">
					<span>'.$document->subject.'</span>
				</div>'
			."\r\n\t".'</div>';
		} elseif(get_parent_class($document) == 'listing') {
			$sortby = $document->sortby;
			$order = $document->order;

			if(substr_count('|Foundation Truth|Treasures of the Kingdom|Dear Princess|','|'.$document->title.'|')) {
				$default = "\r\n\t".'<p><a href="./?sortby=issue&sortlast='.$document->sortby.'">'.$document->title.' sorted by issue</a></p>';
			} else {
				$default = "\r\n\t".'<p><a href="./?sortby=title&sortlast='.$document->sortby.'">'.$document->title.' sorted by title</a></p>';
			}
			$this->html = 
			"\r\n\t".'<div class="header hidden"><!--SEARCH ENGINE FRIENDLY LINKS-->'
			.($type == 'bible' ? "\r\n\t".'<p><a href="./&sortlast='.$document->sortby.'">Bible sorted by collection</a></p>' : '')
			.$default
			.($type != 'bible' ? "\r\n\t".'<p><a href="./?sortby=author&sortlast='.$document->sortby.'">'.$document->title.' sorted by author</a></p>' : '')
			.($type != 'bible' ? "\r\n\t".'<p><a href="./?sortby=year&sortlast='.$document->sortby.'">'.$document->title.' sorted by year</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=lyrics&sortlast='.$document->sortby.'">'.$document->title.' sorted by lyrics</a></p>' : '')
			."\r\n\t".'<p><a href="./?sortby=subject&sortlast='.$document->sortby.'">'.$document->title.' sorted by subject</a></p>'
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=scripture&sortlast='.$document->sortby.'">'.$document->title.' sorted by scripture</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=scores&sortlast='.$document->sortby.'">'.$document->title.' sorted by scores</a></p>' : '')
			.($type == 'bible' ? "\r\n\t".'<p><a href="./?sortby=verse&sortlast='.$document->sortby.'">'.$document->title.' sorted by verse</a></p>' : '')
			.($type == 'texts' ? "\r\n\t".'<p><a href="./?sortby=excerpt&sortlast='.$document->sortby.'">'.$document->title.' sorted by excerpt</a></p>' : '')
			.($type == 'texts' ? "\r\n\t".'<p><a href="./?sortby=published&sortlast='.$document->sortby.'">'.$document->title.' sorted by publishing date</a></p>' : '')
			.($type == 'music' && $document->collection != '_' ? "\r\n\t".'<p><a href="./?sortby=number&sortlast='.$document->sortby.'">'.$document->title.' sorted by number</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=tune&sortlast='.$document->sortby.'">'.$document->title.' sorted by tune</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=composer&sortlast='.$document->sortby.'">'.$document->title.' sorted by composer</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=composed&sortlast='.$document->sortby.'">'.$document->title.' sorted by composed</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=meter&sortlast='.$document->sortby.'">'.$document->title.' sorted by meter</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=key&sortlast='.$document->sortby.'">'.$document->title.' sorted by key</a></p>' : '')
			."\r\n\t".'</div>';

			if(substr_count('|Foundation Truth|Treasures of the Kingdom|Dear Princess|','|'.$document->title.'|')) {
				$default = "\r\n\t".'<option value="issue"'.($sortby=='' ? ' selected="selected"' : '').'>issue</option>';
			} else {
				$default = "\r\n\t".'<option value="title"'.(($sortby=='' && $type != 'bible') || $sortby == 'title' ? ' selected="selected"' : '').'>title</option>';
			}
// @CD requires specialized js for jump menu
			global $url_bits;
			$this->js = '<script type="text/javascript">
				function jumpTo( select ) {
					window.location = select.options[ select.selectedIndex ].value; 
				}
				function nextPage() {
					if(timerID) { clearTimeout(timerID); }
					window.location = "../../../_data/@cd_director.php?t=listing&u0='.$url_bits[0].'&u1='.$url_bits[1].'";
				}
				function settime() {
					// default delay
					timerID = setTimeout("nextPage()", 100);
				}
				</script>'."\r\n\t\t";
			$this->html .= $this->js;
// ^^^				
			$this->html .= 
			"\r\n\t".'<div class="header"><!--HUMAN FRIENDLY FORM OPTION LIST-->
				<form id="flist" name="flist" action="./" method="get">
					<label for="sortby" class="form_item">Sort by:</label>
					<select name="sortby" id="sortby" onchange="jumpTo(this);">'
// @CD requires specialized jump ^^^					
			.($type == 'music' ? "\r\n\t".'<optgroup label="Lyrics">' : '')
			.($type == 'bible' ? "\r\n\t".'<option value="collection"'.($sortby=='' ? ' selected="selected"' : '').'>collection</option>' : '')
			.$default
			.($type != 'bible' ? "\r\n\t".'<option value="author"'.($sortby=='author' ? ' selected="selected"' : '').'>author</option>' : '')
			.($type != 'bible' ? "\r\n\t".'<option value="year"'.($sortby=='year' ? ' selected="selected"' : '').'>year</option>' : '')
			.($type == 'music' ? "\r\n\t".'<option value="lyrics"'.($sortby=='lyrics' ? ' selected="selected"' : '').'>lyrics</option>' : '')
			."\r\n\t".'<option value="subject"'.($sortby=='subject' ? ' selected="selected"' : '').'>subject</option>'
			.($type == 'music' ? "\r\n\t".'<option value="scripture"'.($sortby=='scripture' ? ' selected="selected"' : '').'>scripture</option>' : '')
			.($type == 'music' ? "\r\n\t".'<option value="scores"'.($sortby=='scores' ? ' selected="selected"' : '').'>scores</option>' : '')
			.($type == 'bible' ? "\r\n\t".'<option value="verse"'.($sortby=='verse' ? ' selected="selected"' : '').'>verse</option>' : '')
			.($type == 'texts' ? "\r\n\t".'<option value="excerpt"'.($sortby=='excerpt' ? ' selected="selected"' : '').'>excerpt</option>' : '')
			.($type == 'texts' ? "\r\n\t".'<option value="published"'.($sortby=='published' ? ' selected="selected"' : '').'>published</option>' : '')
			.($type == 'music' && $document->collection != '_' ? "\r\n\t".'<option value="number"'.($sortby=='number' ? ' selected="selected"' : '').'>number</option>' : '')
			.($type == 'music' ? "\r\n\t".'</optgroup>'
				."\r\n\t".'<optgroup label="Scores">'
				."\r\n\t".'<option value="tune"'.($sortby=='tune' ? ' selected="selected"' : '').'>tune</option>'
				."\r\n\t".'<option value="composer"'.($sortby=='composer' ? ' selected="selected"' : '').'>composer</option>'
				."\r\n\t".'<option value="composed"'.($sortby=='composed' ? ' selected="selected"' : '').'>year</option>'
				."\r\n\t".'<option value="meter"'.($sortby=='meter' ? ' selected="selected"' : '').'>meter</option>'
				."\r\n\t".'<option value="key"'.($sortby=='key' ? ' selected="selected"' : '').'>key</option>'
				: '')
			.'				
					</select>'
/* @CD no special sorting
					<input type="hidden" name="sortlast" id="sortlast" value="'.$document->sortby.'" />
					&nbsp;<span class="form_item">Order:</span>'
					.'<input type="radio" name="order" id="ascending" value="ascending"'.($order=='ascending' ? ' checked="checked"' : '').' />'
					.'<label for="ascending">ascending</label>'
					.'&nbsp;<input type="radio" name="order" id="descending" value="descending"'.($order=='descending' ? ' checked="checked"' : '').' />'
					.'<label for="descending">descending</label>'
					.'&nbsp;&nbsp;<input type="submit" value="Apply" />
*/// ^^^
					.'
				</form>'
			."\r\n\t".'</div>';
		} elseif(get_parent_class($document) == 'querylist') {
			$this->html = 
			"\r\n\t".'<div class="header">'
			."\r\n".'<form name="bible_query" action="./" method="get"><input type="hidden" name="query" id="query" value="bible" /><label for="passage" class="form_item">Bible passages:</label> '
			.'<input type="text" style="width:35%;" name="passage" id="passage" accesskey="L" value="'.$_GET['passage'].'" onfocus="this.select();" />&nbsp;&nbsp;<input type="submit" value="Look It Up" /></form>'
			."\r\n\t".'</div>'."\r\n";
		}
	}
}

class hNavBar extends HTML {
	function hNavBar($type,$section,&$document) {
		// adds document title to url if not a music document
		$title_url = ($type != 'music' || get_parent_class($document) == 'listing' ? title_url_format($document->title): '');
		if(get_parent_class($document) == 'document') {
			// get code for dropdown jump menu, and if section is Table of Contents (but not music), modifies $document->xml
			$menu = doc_outline($document, $type, ($section == '' && $type != 'music'));
			// inserts section title "level" if not Table of Contents; and not music
			$prev_title_url = $title_url . ($document->prev_title != 'Table of Contents' ? ($type != 'music' ? '/' : ''). title_url_format($document->prev_title) : '');
			$next_title_url = $title_url . ($document->next_title != 'Table of Contents' ? ($type != 'music' ? '/' : '') . title_url_format($document->next_title) : '');
		} elseif(get_parent_class($document) == 'listing') {
			// get code for dropdown jump menu
			$menu = load_listing($type,$document);
			// converts title to url representation
			$title_url = ($title_url == 'Whole_Bible' || $title_url == 'All_Music' || $title_url == 'All_Texts' ? '_' : $title_url);
			$prev_title_url = $title_url . (	!substr_count($document->prev_title,' Index')
												? '/'.($document->sortby == 'number' ? preg_replace("'^\D+(\d+)$'","$1",$document->prev_title) : title_url_format($document->prev_title))
												: '');
			$next_title_url = $title_url . (	$section != '_'
												? '/'.($document->sortby == 'number' ? preg_replace("'^\D+(\d+)$'","$1",$document->next_title) : title_url_format($document->next_title))
												: '');
/* @CD queries not shown
		} elseif(get_parent_class($document) == 'querylist') {
			$title_url = '?query=bible&q='.urlencode(stripslashes(stripslashes($_GET['q'])));
			$prev_title_url = $title_url.'&start='.($_GET['start']-$_GET['results']).'&results='.$_GET['results'];
			$next_title_url = $title_url.'&start='.($_GET['start']+$_GET['results']).'&results='.$_GET['results'];
*/// ^^^
		}
// @CD requires specialize js for jump menu; generating engine can handle up to 2 tunes per item
		if(count($document->score) == 2 && substr_count('//hifi_2/midi_2/','/'.$section.'/')) {
			$js_next = '"next-mp32","next-mid2","next-sib2",';
		}
		$this->js = '<script type="text/javascript">
			function jumpTo( select ) {
				window.location = select.options[ select.selectedIndex ].value; 
			}
			function nextPage() {
				if(timerID) { clearTimeout(timerID); }
				'.($document->type == 'music' ? (substr($document->section,6,1) ? 'window.location = "../'.((int)$document->score[2]->hi ? 'hifi' : 'midi').'/"; return;' : '') : '').'
				// this allows for up to 2 tunes
				var v_id = new Array('.$js_next.'"next-mp30","next-mid0","next-sib0","next");
				var a_next = "";
				for(i=0;i < v_id.length;i++) {
					a_next = document.getElementById(v_id[i]);
					if(a_next) {
						window.location = a_next.href;
						break;
						window.status += " " + a_next.href;
					}
				}
				if(!a_next) {
					window.location = "../../../_data/@cd_director.php?t='.$document->type.'&x='.urlencode($document->title).'";
				}
			}
			function settime() {
				// default delay
				timerID = setTimeout("nextPage()", 100);
			}
			</script>'."\r\n\t\t";
// ^^^			
		// skip the jump menu if this is a music document
// @CD no querylist		
		if($type != 'music' || get_parent_class($document) != 'document') {
// ^^^
			// each form needs a different id, so the top jump menu is jump1 and the bottom jump menu is jump2
			$this->pre = "\r\n\t\t"
				.'<table summary=""><tr><td><form id="fjump1" name="fjump1" action="./" method="get">'
				."\r\n\t\t\t".'<select id="jumpto1" name="section" onchange="jumpTo(this);">';
// @CD specialized jump ^^^				
			$this->post = "\r\n\t\t"
				.'<table summary=""><tr><td><form id="fjump2" name="fjump2" action="./" method="get">'
				."\r\n\t\t\t".'<select id="jumpto2" name="section" onchange="jumpTo(this);">';
// @CD specialized jump ^^^				
			$this->html =
					$menu
					."\r\n\t\t\t".'</select>';
			// if this jump menu is from listing, and sorting variables are not the defaults, send values with it
			if(get_parent_class($document) == 'listing') {
				$this->html .= "\r\n\t\t\t"
					.($document->sortby != 'title' ? '<input type="hidden" name="sortby" value="'.$document->sortby.'" />' : '')
					.($document->order != 'ascending' ? '<input type="hidden" name="order" value="'.$document->order.'" />' : '');
			}
// @CD submit button not needed
			$this->html .= /*'<input type="submit" value="Go" onclick="this.form.firstChild.click(); return false;" />'.*/
// ^^^			
				"\r\n\t\t".'</form></td></tr></table>';
		}
		if(is_collection(title_url_format($document->title))) {
			$queryline = add_query();
		}
		// the following links used in the Meta section
// @CD no queryline		
		$document->prev_link = ($document->prev_title ? level_url().$type.'/'.$prev_title_url.'/'.$queryline : '');
		$document->next_link = ($document->next_title ? level_url().$type.'/'.$next_title_url.'/'.$queryline : '');
// ^^^		
		$prevline = '<span'.($document->prev_title ? '><a href="'.$document->prev_link.'" title="Previous Page | '.title_tooltip_format($document->prev_title).'">&lt;&lt;</a>' : ' class="disabled">&lt;&lt;');
// @CD add id to next link
		$nextline = '<span'.($document->next_title ? '><a id="next" href="'.$document->next_link.'" title="Next Page | '.title_tooltip_format($document->next_title).'">&gt;&gt;</a>' : ' class="disabled">&gt;&gt;');
// ^^^

		$cderror = urlencode( 'Error on (CD '. date("y-m-d", time()) .'):'.$document->title.'/'. str_replace('_',' ',$section) );
		$reportline = '<a href="http://library.timelesstruths.org/contact/?subject='.$cderror.'&amp;comments='.urlencode('[Please tell us about the problem]').'" accesskey="9" class="www">Report Error</a>';
// @CD specialized error report ^^^		
		$this->html .= '
			<div>'
				.$reportline.'
				| &nbsp;&nbsp;'
				.$prevline
				.'</span>&nbsp;&nbsp;
				| &nbsp;&nbsp;'
				.$nextline
				.'</span>
			</div>';
// @CD uses specialized js
		$this->pre = "\r\n\t". $this->js .'<div class="navbar docnav">' . $this->pre . $this->html . "\r\n\t".'</div>';
		$this->post = "\r\n\t". '<div class="navbar docnav">' . $this->post . $this->html . "\r\n\t".'</div>';
// @CD remove id from second bar
		$this->post = preg_replace("' id=\"next\"'","",$this->post);
// @CD navbar not used on sorting pages
		if($document->sortby) {
			$this->pre = '';
			$this->post = '';
		}
// ^^^
	}
}

class hContent extends HTML {
	var $pre;
	var $post;
	function hContent($type,$title,$section,&$document) {
		if(substr($section,0,5)!='score') {
			if($type=='music' && !is_collection($title)) {
				$class_side = ' sidebar';
			}
			$this->pre = "\r\n"
			.'<div class="content'.$class_side.'" id="content">
			';
			}	
		if($type=='music' && get_parent_class($document) == 'document') {
			if(substr($section,0,5)=='score') {
				// note type and zoom always set to default for caching
				// THESE VALUES MUST BE EXAMINED FOR CUSTOMIZING AFTER EXTRACTING FROM CACHE
				$notes = 'standard';
				$zoom = 760;
				$shaped = file_exists('library/music/'.substr($title,0,1).'/'.$title.'/'.$title.substr($section,5).'+.sib');
				$zoom = ($zoom==580 || $zoom==760 || $zoom==900 || $zoom==1200) ? $zoom : 'custom';
// @CD uses specialized js
				$this->html = "\r\n"
.'<script type="text/javascript">
function zoomTo( select ) {
	sWidth = select.options[ select.selectedIndex ].value;
	sHeight = sWidth * 1.3 + 26; // letter size ratio
	
	s = document.getElementById("scorch");
	s.style.width = sWidth + "px";
	s.style.height = sHeight + "px";
}
</script>';
// ^^^
				$this->html .= "\r\n"
				.'<div class="panel score">
					<p class="first">To view the music score below, you will need to <a class="red" href="http://www.sibelius.com/scorch/">get the free Scorch plugin</a>. See <a class="blue" href="'.level_url().'help/More_Help/Sheet_Music_Scorch_Plugin/">Help &gt; Sheet Music</a>.</p>
					<form id="fzoom" name="fzoom" action="./" method="get">
						<p><label for="zoom" class="form_item">Zoom:</label>																
						<select id="zoom" name="zoom" onchange="'/* @CD uses special zoom function */.'zoomTo(this);'./* <<< */'">
							'.($zoom=='custom' ? '<option value="'.(int)($_GET['zoom']).'" selected="selected">custom</option>' : '').'
							<option value="580"'.($zoom==580 ? ' selected="selected"' : '').'>smaller</option>
							<option value="760"'.($zoom==760 ? ' selected="selected"' : '').'>standard</option>
							<option value="900"'.($zoom==900 ? ' selected="selected"' : '').'>larger</option>
							<option value="1200"'.($zoom==1200 ? ' selected="selected"' : '').'>largest</option>
						</select>
						&nbsp;<span class="form_item">Notes:</span>'
						.'<input type="radio" name="notes" id="standard" value="standard"'.($notes=='standard' ? ' checked="checked"' : '').' />'
						.'<label for="standard">standard</label>'
						.'&nbsp;<input type="radio"'.(!$shaped ? ' disabled="disabled"' : '').' name="notes" id="shaped" value="shaped"'.($notes=='shaped' ? ' checked="checked"' : '').' />'
						.'<label for="shaped">shaped</label>'
						.'&nbsp;&nbsp;<input type="submit" value="Apply" />
					</form>'
				."\r\n".'</div>
				';
			}
		} else {
			if(get_parent_class($document) == 'listing' && ($document->section || $type == 'bible') ) {
				$this->html = '
				<div class="list"><div class="list_table">'."\r\n\r\n"
				.$document->xml
				."\r\n\r\n".'
				</div></div>
				';
			} elseif( (get_parent_class($document) == 'document' || get_parent_class($document) == 'listing') && $section == '') {
				// if this is Table of Contents, $document->xml has been already set by function called from hNavBar
				$this->html = '
				<div class="document toc">'."\r\n\r\n"
				.$document->xml
				."\r\n\r\n".'
				</div>
				';
			} elseif(get_class($document) == 'sitesplit') {
				$this->html = '
				<div class="site '.$type.'">'."\r\n\r\n"
				.$document->xml
				."\r\n\r\n".'
				</div>
				';
			}
		}
		if(substr($section,0,5)!='score') {
			$this->post = "\r\n".'</div>';
		}
	}
	
	// parsing to come after object initialization to take advantage of the objects variables
	function parse(&$document) {
		// run lyrics parsing only if it's a music page with parsable lyrics
		if(get_class($document) == 'musicdoc' && substr($document->section,0,5) != 'score') {
			$section = $document->section;
			global $parsed;
			parse_xml_lyrics($document->xml);
		
			// adds music playing in background
			$mediacode = '';
			$title = title_url_format($document->title);
			$id = (substr($section,5) ? '_'.substr($section,5) : '');
			if(substr($section,0,4) == 'midi') {
				$path = level_url().'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.$id.'.mid';
// @CD version must use javascript detection; add to _mid folder if not present
				if(!file_exists('@cd/_mid/'.$title.$id.'.mid')) {
					copy('library/music/'.substr($title,0,1).'/'.$title.'/'.$title.$id.'.mid',
						'@cd/_mid/'.$title.$id.'.mid');
				}
				$mediacode = "\r\n".
					'<script type="text/javascript">
					if(navigator.userAgent.indexOf("MSIE") != -1 || navigator.userAgent.indexOf("Opera") != -1) {
						document.write("<bgsound src=\''.$path.'\' loop=\'infinite\' />");
					} else {
						document.write("<embed style=\'height:0;\' src=\''.$path.'\' hidden=\'true\' autostart=\'true\' loop=\'true\' />");
					}
					</script>';
/*
				if(substr_count($_SERVER['HTTP_USER_AGENT'],'MSIE') || substr_count($_SERVER['HTTP_USER_AGENT'],'Opera')) {
					$mediacode = "\r\n".'<bgsound src="'.$path.'" loop="infinite" />';
				} else {
					$mediacode = "\r\n".'<embed style="height:0;" src="'.$path.'" hidden="true" autostart="true" loop="true" />';
				}
				// because the html is tailored to the browser, the page can't be cached
				global $nocache;
				$nocache = true;
*/// ^^^				
			} elseif(substr($section,0,4)=='hifi' || substr($section,0,4)=='lofi') {
				$quality = '_'.substr($section,0,2);
// @CD add to _mp3 folder if not present
				if(!file_exists('@cd/_mp3/'.$title.$id.$quality.'.mp3')) {
					copy('library/music/'.substr($title,0,1).'/'.$title.'/'.$title.$id.$quality.'.mp3',
						'@cd/_mp3/'.$title.$id.$quality.'.mp3');
				}
// ^^^
				// gets the right mp3 to pass to Flash
				$mediapath = level_url_webfront().'f_stream.swf?mp3='.urlencode(level_url().'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.$id.$quality.'.mp3');
/* @CD skip
				// gets around Flash bug in certain IE 5.5 versions
				if(substr_count($_SERVER['HTTP_USER_AGENT'],'MSIE 5.5')) {
					$iebug = "\r\n\t".'classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"';
					global $nocache;
					$nocache = true;
				}
*/// ^^^
				$mediacode = '
				<object type="application/x-shockwave-flash"'
				  .$iebug.'
				  data="'.$mediapath.'">
				<param name="movie" value="'.$mediapath.'" />
				<p class="warning">You need to upgrade your browser to be able to play this music in the background. Streaming music requires the free Flash plugin (version 6 or greater).</p>
				</object>					
				';
			}
			if(strlen($section)) {
				$mediaplay = "\r\n\t\t\t\t".'<div class="notes first"><p class="notice first">Now playing '.$document->description.'.</p><p>It may take a few seconds to begin.</p></div>'."\r\n\r\n";
			}

			// replaces notes tags with html
			$notes = preg_replace(array("'<notes>'","'</notes>'"),array("\t\t\t<div class=\"notes\">","</div>"), $document->notes);
			// adds css markup to first notes paragraph
			$notes = preg_replace("'^([\s\S]*?)<p>'","$1<p class=\"first\">",$notes);
			// adds copyright notice if necessary
			$copynotice = '';
			if($document->copyright['owner'] != '?'
			 && $document->copyright['year'] >= 1923
			 && !$document->copyright['cc']) {
				$copynotice = '
				<div class="notes">
					<p class="first">We do not have permission to publish the lyrics:</p>
					<p>Copyright '.$document->copyright['year'].' '.$document->copyright['owner'].'</p>
				</div>
				';
			} elseif($document->copyright['owner'] == '?') {
				$copynotice = '
				<div class="notes">
					<p class="first">The copyright status of this song is uncertain. If you have more information, please <a href="'.level_url().'contact/?subject='.urlencode($document->title).'">contact us</a>.</p>
				</div>
				';
			}
			$html = "\r\n\t\t\t"
			.'<div class="document lyrics">'
			.$mediaplay
			."\r\n\t\t\t\t".'<div class="verses">'
			."\r\n\t"
			.$parsed
			.'
			</div>'
			.($document->notes ? "\r\n\t".apply_formatting($notes) : '')
			.$copynotice
			.$mediacode
			."\r\n"
			."\t\t\t".'</div>
			';
			
			// clear $parsed
			$parsed = '';
			return $html;
		}
		// only run parsing if it's a parsable page, i.e., a non-TOC TextDoc or BibleDoc, or query
		if((get_parent_class($document) == 'document' && $document->section != '') || get_parent_class($document) == 'querylist') {
			if(get_class($document) != 'musicdoc' ) {
				// if not displaying the whole document, check to see if this section is an image, which will have no page margins
				if($document->this_pNum !== false && $document->parts[$document->this_pNum]->sections[$document->this_sNum]->type == 'image') {
					$document->xml = preg_replace("'(<image(?:[^>]*)>)[\s\S]*?(</image>)'","$1$2",$document->xml);
					$classline = 'image';
				} else {
					$classline = 'document page';
				}
				if($document->type == 'texts' || $document->type == 'help') {
					$this->encode_xhtml($document->xml);
				}
				global $parsed;
				parse_xml_texts($document->xml);
//				$parsed = $document->xml; // <--ONLY USED FOR DEBUGGING
				if($document->type == 'texts' || $document->type == 'help') {
					apply_firsts($parsed);
				}
				$html = '
				<div class="'.$classline.'">'."\r\n\r\n"
				.$document->warning
				.$parsed
				."\r\n\r\n".'
				</div>
				';
				// clear $parsed
				$parsed = '';
				return $html;
			}
		}
	}
	
	function encode_xhtml(&$xml) { //encodes xhtml, so it won't be parsed
		$xml = preg_replace("'(<xhtml[^>]*?>)([\s\S]+?)(?=</xhtml>)'e","stripslashes('$1').rawurlencode('$2')",$xml);
	}
}

class hSideTable extends HTML {
	var $pre;
	var $post;
	var $final;
	function hSideTable($type,$section,&$document) {
		$this->pre = '
		<table summary=""><tr>
		<th>
		';
		if(get_parent_class($document) == 'document' || (get_parent_class($document) == 'listing' && !$document->section) ) {
			$copyline = format_copyright($document->author,$document->copyright,$document->title,'Text');
			if(get_parent_class($document) == 'listing') {
				$copyline = format_copyright($document->publisher,$document->copyright,$document->title,'Text');
				$titleline = format_orphan($document->pub_title);
				$authoryear = format_author($document->publisher);
				
				//global database variable array passed to database connection from included "f_dbase.php"
				global $db, $rewrite_title_url;
				db_connect($db);
				if($type == 'texts') {
					$r = mysql_query("SELECT date,collection,title FROM ". $db['tt3_texts'] ." WHERE collection LIKE '".$document->collection."%' ORDER BY date DESC");
					$rTexts=mysql_fetch_assoc($r);
					$rewrite_title_url = $rTexts['collection']; // make sure title rewriting is updated for magazine collection titles
					$title_url = title_url_format($rTexts['title']);
					$html = "\r\n"
					."\r\n".'<div class="recent"><table summary="" class="first"><tr><td class="top"><div class="date"><p>'.convert_date($rTexts['date']).'</p></div></td></tr>'
					."\r\n".'<tr><td class="icon"><a href="'.level_url().'texts/'.$title_url.'/" title="'.title_tooltip_format($rTexts['title']).'"><img src="'.level_url().'library/texts/'.$title_url.'/'.$title_url.'.jpg" alt="'.title_tooltip_format($rTexts['title']).'" width="120" height="120" /></a></td></tr>'
					."\r\n".'<tr><td class="bottom"><div class="title"><p><a href="'.level_url().'texts/'.$title_url.'/">'.$rTexts['title'].'</a></p></div></td></tr></table></div>';
				} elseif($type == 'music') {
					$r = mysql_query("SELECT * FROM ". $db['tt3_scores'] ." WHERE collection LIKE '%".$document->collection."%' ORDER BY sib DESC, title DESC");
					$arrKeysigs = array('C'=>'C','Am'=>'C','G'=>'G','Em'=>'G','D'=>'D','Bm'=>'D','A'=>'A','Fsm'=>'A',
						'E'=>'E','Csm'=>'E','B'=>'B','Gsm'=>'B','Fs'=>'Fs','Dsm'=>'Fs','Cs'=>'Cs','Asm'=>'Cs',
						'F'=>'F','Dm'=>'F','Bb'=>'Bb','Gm'=>'Bb','Eb'=>'Eb','Cm'=>'Eb',
						'Ab'=>'Ab','Fm'=>'Ab','Db'=>'Db','Bbm'=>'Db','Gb'=>'Gb','Ebm'=>'Gb','Cb'=>'Cb','Abm'=>'Cb');

					$rM=mysql_fetch_assoc($r);
					// The follow method should be the same as the one given in 'classes_feeds.php'
					// matches the latest collection given
					preg_match("'<([^<:]*?):([^:/]*?)/([^/:]*?):([^/>]*?)>$'",$rM['collection'],$cm);

					$arrM = mysql_fetch_assoc(mysql_query("SELECT title FROM ". $db['tt3_music'] ." WHERE collection LIKE '%".addslashes($cm[0])."%'"));
					$title_url = title_url_format($arrM['title']);
					
					$score_icon = $arrKeysigs[str_replace('#','s',preg_replace("',.+'",'',$rM['keytone']))];
					
					$html = "\r\n"
					."\r\n".'<div class="recent"><table summary="" class="first"><tr><td class="top"><div class="date"><p>'.convert_date($rM['sib']).'</p></div></td></tr>'
					."\r\n".'<tr><td class="icon"><a href="'.level_url().'music/'.$title_url.'/score'.($cm[4] ? '_'.$cm[4] : '').'/"><img src="'.level_url_webfront().'score_icon/keysig_'.$score_icon.'.gif" alt="'.title_tooltip_format($arrM['title']).'" width="120" height="60" /></a></td></tr>'
					."\r\n".'<tr><td class="bottom"><div class="title"><p><a href="'.level_url().'music/'.$title_url.'/">'.$arrM['title'].'</a></p></div></td></tr></table></div>';
				}
				// disconnect from database
				db_disconnect($db);
	
				$this->html = '
				<div class="sidebar">
					<h1 class="first">'.$titleline.'</h1>'
					.($authoryear ? '
					<p class="author">'.$authoryear.'</p>
					<p class="red last">Copyright: '.$copyline.'</p>'
					: '')
					.($document->summary ? '
					<fieldset>
					<p>'.apply_formatting($document->summary).'</p>
					</fieldset>'
					: '<hr />').'
					<p>Most Recent:</p>
					'.$html.'
				</div>
				';
			} elseif($type=='music') {
				// assigns subject margins depending on whether scripture reference will follow
				$subject_class = ($document->scripture ? 'first' : 'first last');
				$titleline = format_orphan($document->title);
				$authoryear = format_author($document->author);
	
				$this->html = '
				<div class="sidebar">
					<h1 class="first">'.$titleline.'</h1>
					<p class="author">'.$authoryear.'</p>
					<p class="red last">Copyright: '.$copyline.'</p>
					<hr />
					<p class="'.$subject_class.'">Main subject: '.$document->subject.'</p>'
// @CD scripture search only online
					.($document->scripture ? "\r\n\t\t\t\t\t".'<p class="green last">Scripture: <a href="http://library.timelesstruths.org/search/?query=bible&amp;passage='.$document->scripture.'" class="www">'.$document->scripture.'</a></p>' : '')
// ^^^
					.$this->list_media($section,$document)
					.'</div>
				';
			} else {
				if($type=='help') {
					$root = 'www';
				} else {
					$root = 'library';
				}
				$subject_class = ($type=='help' || $type=='texts' ? 'first' : 'first last');
				if($document->type != 'bible') {
					$imageline = '<p><img src="'.level_url().$root.'/'.$type.'/'.title_url_format($document->title).'/'.title_url_format($document->title).'.jpg" alt="'.$document->title.'" width="120" height="120" /></p>';
					$editline = '<p class="last">Last edited: '.date("F j, Y",filemtime($root.'/'.$type.'/'.title_url_format($document->title).'/'.title_url_format($document->title).'.xml')).'</p>';
				}
				if($document->type == 'texts') {
					$publine = '<p>Published: '.date("Y",strtotime($document->date)).'</p>';
				}
				$titleline = format_orphan($document->title);
				$authoryear = format_author($document->author);
// @CD url arguments not included
				// $anchorline = '?anchor='.$document->excerpt_anchor.'#'.str_replace("%27","'",rawurlencode($document->excerpt_anchor));
// ^^^				
				$excerptline = '<a href="'.level_url().$type.'/'.title_url_format($document->title).'/'.title_url_format($document->excerpted).'/'.$anchorline.'">'.apply_formatting($document->excerpted).'</a>';

				$this->html = '
				<div class="sidebar">
					<h1 class="first">'.$document->title.'</h1>'
					."\r\n\t\t\t\t\t".$imageline
					."\r\n\t\t\t\t\t".'<p class="author">'.$authoryear.'</p>
					<p class="last">Copyright: '.$copyline.'</p>
					<hr />
					<p class="'.$subject_class.'">Main subject: '.$document->subject.'</p>'
					.$publine
					.$editline
					.(strlen($document->excerpt) ? "\r\n"
						.'<fieldset>
						<p>'.apply_formatting($document->excerpt).'</p>'
						.($document->excerpted ? "\r\n".'<p class="excerpted"><i>from</i> '.$excerptline.'</p>' : '')
						.'</fieldset>' : '')
					."\r\n".'</div>
				';
			}
		} else { // class is SiteSplit
			if($type == 'welcome') { $type = ''; } // DEBUGGING ONLY
			if($type == 'texts' || $type == '') { // get latest texts
				//global database variable array passed to database connection from included "f_dbase.php"
				global $db, $rewrite_title_url;

				db_connect($db);
				$results = mysql_query("SELECT date,collection,title FROM ". $db['tt3_texts'] ." ORDER BY date DESC");
				// disconnect from database
				db_disconnect($db);
				// grab 3 latest texts
				for($i=0; $i < ($type == '' ? 1 : 3); $i++) {
					$rTexts=mysql_fetch_assoc($results);
					$rewrite_title_url = $rTexts['collection']; // make sure title rewriting is updated for magazine collection titles
					$title_url = title_url_format($rTexts['title']);

					// key has 'b' prefix to precede music in reverse order
					$html['b'.strtolower(str_replace('_','',$title_url))] = "\r\n"
					."\r\n".'<table summary=""><tr><td class="top"><div class="date"><p>'.convert_date($rTexts['date']).'</p></div></td></tr>'
					."\r\n".'<tr><td class="icon"><a href="'.level_url().'texts/'.$title_url.'/" title="'.title_tooltip_format($rTexts['title']).'"><img src="'.level_url().'library/texts/'.$title_url.'/'.$title_url.'.jpg" alt="'.title_tooltip_format($rTexts['title']).'" width="120" height="120" /></a></td></tr>'
					."\r\n".'<tr><td class="bottom"><div class="title"><p><a href="'.level_url().'texts/'.$title_url.'/">'.$rTexts['title'].'</a></p></div></td></tr></table>';
				}
				$titleline = 'Recent ';
			}
			if($type == 'music' || $type == '') { // get latest scores
				//global database variable array passed to database connection from included "f_dbase.php"
				global $db;
				db_connect($db);
				$r = mysql_query("SELECT * FROM ". $db['tt3_scores'] ." ORDER BY sib DESC, title DESC");
				$arrKeysigs = array('C'=>'C','Am'=>'C','G'=>'G','Em'=>'G','D'=>'D','Bm'=>'D','A'=>'A','Fsm'=>'A',
					'E'=>'E','Csm'=>'E','B'=>'B','Gsm'=>'B','Fs'=>'Fs','Dsm'=>'Fs','Cs'=>'Cs','Asm'=>'Cs',
					'F'=>'F','Dm'=>'F','Bb'=>'Bb','Gm'=>'Bb','Eb'=>'Eb','Cm'=>'Eb',
					'Ab'=>'Ab','Fm'=>'Ab','Db'=>'Db','Bbm'=>'Db','Gb'=>'Gb','Ebm'=>'Gb','Cb'=>'Cb','Abm'=>'Cb');
				// grab 3 latest scores
				for($i=0; $i < ($type == '' ? 1 : 3); $i++) {
					$rM=mysql_fetch_assoc($r);
					// The follow method should be the same as the one given in 'classes_feeds.php'
					// matches the latest collection given
					preg_match("'<([^<:]*?):([^:/]*?)/([^/:]*?):([^/>]*?)>$'",$rM['collection'],$cm);

					$arrM = mysql_fetch_assoc(mysql_query("SELECT title FROM ". $db['tt3_music'] ." WHERE collection LIKE '%".addslashes($cm[0])."%'"));
					$title_url = title_url_format($arrM['title']);
					
					$score_icon = $arrKeysigs[str_replace('#','s',preg_replace("',.+'",'',$rM['keytone']))];
					
					// key has 'a' prefix to follow texts in reverse order
					$html ['a'.$rM['sib'].strtolower(str_replace('_','',$title_url))] = "\r\n"
					."\r\n".'<table summary=""><tr><td class="top"><div class="date"><p>'.convert_date($rM['sib']).'</p></div></td></tr>'
					."\r\n".'<tr><td class="icon"><a href="'.level_url().'music/'.$title_url.'/score'.($cm[4] ? '_'.$cm[4] : '').'/" title="'.title_tooltip_format($rM['title']).'"><img src="'.level_url_webfront().'score_icon/keysig_'.$score_icon.'.gif" alt="'.title_tooltip_format($rM['title']).'" width="120" height="60" /></a></td></tr>'
					."\r\n".'<tr><td class="bottom"><div class="title"><p><a href="'.level_url().'music/'.$title_url.'/">'.$arrM['title'].'</a></p></div></td></tr></table>';
				}
				$titleline = 'Recent ';
				// disconnect from database
				db_disconnect($db);
			}
			if($type == '') { $titleline = 'Recent Additions'; }
			// sets the content of the side panel
			
			$this->html = "\r\n"
			.'<div class="sidebar'.($type == '' || $type == 'texts' || $type == 'music' ? '"><div class="recent">' : ' site">')
			."\r\n\t".'<h1 class="first">'.$titleline.ucfirst($type).'</h1>';
			if(is_array($html)) {
				krsort($html);
				foreach($html as $content) {
					$this->html .= $content;
				}
			} else {
				// $document->sidebar set by the files called from classes_site.php
				$this->html .= $document->sidebar;
			}
			$this->html .= "\r\n"
			.($type == '' || $type == 'texts' || $type == 'music' ? '</div>' : '').'</div>';
		}
		$this->post = '
		</th>
		<td>
		';
		$this->final = '
		</td>
		</tr></table>
		';
	}
	
	function list_media($section,&$document) {
		$html = '';
		
		// if no score is available, skip score data
		if(!$document->score[0]->sib) {
			if($document->copyright['owner'] != '?'
			 && $document->copyright['year'] >= 1923
			 && !$document->copyright['cc']) {
			 	return; // cannot provide sheet music for copyrighted songs
			 } else {
				global $uacrawler, $nocache;
				if($uacrawler) { // CRAWLERS DON'T NEED TO REQUEST SHEET MUSIC
					$nocache = true;
				} else {
					$subjectline = urlencode('Request: '.$document->title);
					$commentsline = urlencode('I would like to have the sheet music for this song added to your priority list. Thank you.');
					$html .= "\r\n\t\t\t\t".'<fieldset>'
					.'<p><a class="blue" href="'.level_url().'contact/?subject='.$subjectline.'&amp;comments='.$commentsline.'">Request sheet music</a>'
					."\r\n\t\t\t\t".'</fieldset>
					';
				}
			}
			return $html;
		}

		foreach($document->score as $s_id => $score) {
			$title = title_url_format($document->title);
			$id = $score->id;
			$url = level_url().'music/'.$title.'/';
			$url_score = level_url().'music/'.$title.'/score'.($id ? '_'.$id : '').'/';
			$url_midi = level_url().'music/'.$title.'/midi'.($id ? '_'.$id : '').'/';
			$url_hifi = level_url().'music/'.$title.'/hifi'.($id ? '_'.$id : '').'/';
			$url_lofi = level_url().'music/'.$title.'/lofi'.($id ? '_'.$id : '').'/';
			$url_lowm = level_url().'music/'.$title.'/lowm'.($id ? '_'.$id : '').'/';
			
			// provide link to midi-in-background if not playing, or link to plain lyrics if playing
			$midi_line = '';
			if($score->mid) {
				if($section=='midi'.($id ? '_'.$id : '')) {
					$midi_line = '<p><img src="'.level_url_webfront().'linked_midi.gif" alt="Playing Midi" /> <a href="'.$url.'">stop</a>';
				} else {
// @CD id added				
					$midi_line = '<p><a '.(substr($section,0,4) != 'midi' ? 'id="next-mid'.$id.'"' : '').' href="'.$url_midi.'" title="Play Midi"><img src="'.level_url_webfront().'link_midi.gif" alt="Play Midi" /></a> <a href="'.$url_midi.'">play</a>';
// ^^^
				}
				$source_midi = level_url().'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.($id ? '_'.$id : '').'.mid';
// @CD no download				
				$midi_line .= ' midi</p>';// [<a class="source" href="'.$source_midi.'" title="Download midi source file">.mid</a>]</p>';
// ^^^				
			}
			// provide link to hifi-in-background if not playing, or link to plain lyrics if playing
			$hifi_line = '';
			if((int)$score->hi) { // hifi is a decimal time value, so '0.0' must be converted to (int)
				if($section=='hifi'.($id ? '_'.$id : '')) {
					$hifi_line = '<p><img src="'.level_url_webfront().'linked_hifi.gif" alt="Playing HiFi MP3" /> <a href="'.$url.'">stop</a>';
				} else {
// @CD id added				
					$hifi_line = '<p><a '.(!$section ? 'id="next-mp3'.$id.'"' : '').' href="'.$url_hifi.'" title="Play HiFi MP3"><img src="'.level_url_webfront().'link_hifi.gif" alt="Play HiFi MP3" /></a> <a href="'.$url_hifi.'">play</a>';
// ^^^
				}
				// filesize comparisons relative from calling file, which is _urlhandler, already "leveled", at root of site
				$source_hifi = 'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.($id ? '_'.$id : '').'_hi.mp3';
// @CD no download				
				$hifi_line .= ' high quality mp3</p>';// [<a class="source" href="'.level_url().$source_hifi.'" title="Download HiFi mp3 source file ('.(int)(@filesize($source_hifi)/1024).' KB)">.mp3</a>]</p>';
// ^^^				
			}
/* @CD skip
			// provide link to lofi-in-background if not playing, or link to plain lyrics if playing
			$lofi_line = '';
			if($score->lo) {
				if($section=='lofi'.($id ? '_'.$id : '')) {
					$lofi_line = '<p><img src="'.level_url_webfront().'linked_lofi.gif" alt="Playing LoFi MP3" /> <a href="'.$url.'">stop</a>';
				} else {
					$lofi_line = '<p><a href="'.$url_lofi.'" title="Play LoFi MP3"><img src="'.level_url_webfront().'link_lofi.gif" alt="Play LoFi MP3" /></a> <a href="'.$url_lofi.'">play</a>';
				}
				$source_lofi = 'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.($id ? '_'.$id : '').'_lo.mp3';
				$lofi_line .= ' dial-up quality mp3</p>'; [<a class="source" href="'.level_url().$source_lofi.'" title="Download LoFi mp3 source file ('.(int)(@filesize($source_lofi)/1024).' KB)">.mp3</a>]</p>';
			}
			// provide link to lofi-WMA-in-background if not playing, or link to plain lyrics if playing
			$lowm_line = '';
			if($score->wma) {
				if($section=='lowm'.($id ? '_'.$id : '')) {
					$lowm_line = '<p><img src="'.level_url_webfront().'linked_lowm.gif" alt="Playing LoFi Windows Media Audio" /> <a href="'.$url.'">stop</a>';
				} else {
					$lowm_line = '<p><a href="'.$url_lofi.'" title="Play LoFi Windows Media Audio"><img src="'.level_url_webfront().'link_lowm.gif" alt="Play LoFi Windows Media Audio" /></a> <a href="'.$url_lowm.'">play</a>';
				}
				$source_lowm = 'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.($id ? '_'.$id : '').'_lo.wma';
				$lowm_line .= ' dial-up quality <span title="Windows Media Audio">WMA</span> [<a class="source" href="'.level_url().$source_lowm.'" title="Download LoFi WMA source file ('.(int)(@filesize($source_lowm)/1024).' KB)">.wma</a>]</p>';
			}
*/// ^^^
			
			// if title is long, and the last word is short, "attach" it to the next one so it won't hang by itself
			$titleline = preg_replace("'^(.{25,}?)(?: (\S{1,4}))$'","$1&nbsp;$2",$score->title);
			$keyline = preg_replace(array("'b'","'s'"),array("&#9837;","&#9839;"), $score->key); // converts flats (b) and sharps (s) notation to HTML characters
			if(substr_count($keyline,'&#')) {
				$keyline = '<span title="'.preg_replace(array("'&#9837;'","'&#9839;'"),array(" flat"," sharp"),$keyline).'">'.$keyline.'</span>'; // for browsers that don't render flats and sharps notation, spell out
			}

			$html .= "\r\n\t\t\t\t"
			.'<fieldset>'
			.($score->title ? "\r\n\t\t\t\t".'<p class="scoretitle">'.$titleline.'</p>' : '')
			."\r\n\t\t\t\t".'<p>'.format_author($score->author).'</p>'
			."\r\n\t\t\t\t".'<p'.($score->key ? ' class="last"' : '').'>Copyright: '.format_copyright($score->author,$score->copyright,$score->title,'Sound').'</p>'
			.($score->key ? "\r\n\t\t\t\t<hr />\r\n\t\t\t\t".'<p class="first">Key: <a href="'.level_url().'music/_/'.$score->key.'/?sortby=key">'.$keyline.'</a></p>' : '')
			.($score->meter ? "\r\n\t\t\t\t".'<p>Meter: '.$score->meter.'</p>' : '')
// @CD sib download not provided; id added
			.($score->sib ? "\r\n\t\t\t\t".'<p><a '.(substr($section,0,4) == 'midi' ? 'id="next-sib'.$id.'"' : '').' href="'.$url_score.'" title="Show Score"><img src="'.level_url_webfront().'link_score.gif" alt="Show Score" /></a> <a href="'.$url_score.'">view</a> score sheet music'/* [<a class="source" href="'.level_url().'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.($id ? '_'.$id : '').'.sib" title="Download score source file">.sib</a>]*/.'</p>' : '')
// ^^^
			."\r\n\t\t\t\t".$midi_line
			."\r\n\t\t\t\t".$hifi_line
			."\r\n\t\t\t\t".$lofi_line
			."\r\n\t\t\t\t".$lowm_line
			."\r\n\t\t\t\t".'</fieldset>
			';
		}
		return $html;
	}
}

class hScore extends HTML {
	function hScore($title,$section) {
// @CD add to _sib folder if not present
		if(!file_exists('@cd/_sib/'.$title.substr($section,5).'.sib')) {
			copy('library/music/'.substr($title,0,1).'/'.$title.'/'.$title.substr($section,5).'.sib',
				'@cd/_sib/'.$title.substr($section,5).'.sib');
		}
// ^^^
		
		// file note type and zoom width always set to default for caching
		// THESE VALUES MUST BE EXAMINED FOR CUSTOMIZING AFTER EXTRACTING FROM CACHE
		$score_file = level_url().'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.substr($section,5).'.sib';
		$width = 760;
		$height = $width * 1.3 + 26; // letter size ratio
		$this->html = '
		<div id="scorch" style="width:'.$width.'px; height:'.$height.'px;"> 
		<object
			  type="application/x-sibelius-score"
			  data="'.$score_file.'" 
			  style="width:100%; height:100%;">
			<param name="src" value="'.$score_file.'" />
			<param name="scorch_minimum_version" value="3000" />
			<param name="scorch_shrink_limit" value="100" />
		</object>
		</div>
		';
	}
}

class hSource extends HTML {
	function hSource($source) {
		if(!count($source)) { return; } // return if no sources given
		
		$this->html = '
		<div class="source"><ul>
		Source'.(count($source) > 1 ? 's' : '').':';

		foreach($source as $s) {
			$pub_line = format_author($s->s_publisher,false,false,true);
			$title_line = ($s->title ? ($pub_line ? '<i title="'.$pub_line.'">'.$s->title.'</i>' : $s->title) : '');
			$id_line = ($s->id ? ' ('.$s->id.')' : '');
			$notes = ($s->notes ? ($title_line ? '; ' : '') . $s->notes : '');
			$this->html .= "\r\n".'<li>'.$title_line.$id_line.$notes.'</li>';
		}
		
		$this->html .= '
		</ul></div>
		';
	}
}

// generates jump-menu list and Table of Contents
function doc_outline(&$document, $type, $toc = false) {
	// returns jump menu code, changes toc xml passed by reference
	$xml = '';
	
	// all documents have at least a Whole Document view and Table of Contents
	$menu =
	"\r\n\t".'<option value="_"'.($document->section == '_' ? ' selected="selected"' : '').'>Whole Document</option>'
	."\r\n\t".'<option value=""'.($toc ? ' selected="selected"' : '').'>Table of Contents</option>';
	
	// shorter alias
	$parts = &$document->parts;
	for($i=0; $i < count($parts); $i++) {
		// adds menu option group whether or not part toc data is present
		$menu .= "\r\n\t".'<optgroup'.($parts[$i]->type == 'preface' ? ' class="preface"' : '').' label="'.$parts[$i]->toc->title.'">';
		if($parts[$i]->type == 'break') {
			// adds part "breaks" 
			$xml .= '<hr />'."\r\n";
		}
		if($parts[$i]->toc->xml && $parts[$i]->type != 'list') {
			$toc_xml = $parts[$i]->toc->xml;
			parse_xml_texts($toc_xml);
			global $parsed;
			apply_firsts($parsed);
			$toc_xml = $parsed;
			$xml .= $toc_xml."\r\n";
			// clear $parsed
			$parsed = '';
		}
		// if list is prefatory, enclose in div
		$xml .= ($parts[$i]->type == 'preface' ? '<div class="preface">'."\r\n" : '');
		// if list is a list, enclose in div, and insert list "tab" ul
		if($parts[$i]->type == 'list') {
			$xml .= '<div class="preface list" id="list'.$i.'">'."\r\n"
				.'<ul class="tab_open"><li><a href="#list'.$i.'">'.$parts[$i]->toc->title.'</a></li></ul>'."\r\n"
				.'<ul class="open">'."\r\n";
		} else {
			$xml .= '<ul>'."\r\n";
		}

		for($j=0; $j < count($parts[$i]->sections); $j++) {
			// if section is an "image" (image only) or "illustration" (image mostly, with some text, e.g. caption), add image icon
			if($parts[$i]->sections[$j]->type && substr_count('image,illustration',$parts[$i]->sections[$j]->type)) {
				$menu_prefix = '&#8864; ';
				$item_class = ' class="i"';
			} else { // clear settings
				$menu_prefix = '';
				$item_class = '';
			}
			$item_start = '<li'.$item_class.'>';
			if($document->type == 'bible') {
				$working_title = $parts[$i]->sections[$j]->id;
				$working_prefix = ($document->title == 'Psalms' ? 'Psalm ' : 'Chapter ');
			} else {
				$working_title = $parts[$i]->sections[$j]->title;
			}
			$url_st = title_url_format($working_title);
			$url_t = title_url_format($document->title);
			// gets current section title (if not Table of Contents or Whole Document) for testing if it matches current jump menu option
			if(!$toc && $document->section != '_') {
				if($document->type == 'bible') {
					$section = $parts[$document->this_pNum]->sections[$document->this_sNum]->id;
				} else {
					$section = $parts[$document->this_pNum]->sections[$document->this_sNum]->title;
				}
			}
			// if section type is music and score is available, add link
			if($parts[$i]->sections[$j]->type == 'music') {
				if(file_exists('library/music/'.substr($url_st,0,1).'/'.$url_st.'/'.$url_st.'.sib')) {
					$item_start = '<li class="score_link"><a class="score_link" href="'.level_url().'music/'.$url_st.'/score/" title="View Score Sheet Music">'
						.'<img src="'.level_url_webfront().'link_score.gif" alt="View Score Sheet Music" /></a>';
				}
				$item_link = '<a href="'.level_url().'music/'.$url_st.'/">';
				// adds to jump menu options, formatting specially to instruct _urlhandler.php to send directly to music title, selecting if it matches current title
				$menu .= "\r\n\t". '<option value="music/'.$url_st.'/"'.(title_url_format($section)==$url_st ? ' selected="selected"' : '').'>&#9835; '.$working_prefix.apply_formatting($working_title).'</option>';
			} else {
				// non-music sections must exactly match url: $url_bitz[2]
				global $url_bits;
				$item_link = '<a href="'.level_url().$type.'/'.$url_t.'/'.$url_st.'/">';
				// adds to jump menu options, selecting if it matches current title
				$menu .= "\r\n\t". '<option value="'.$url_st.'"'.($url_bits[2]==$url_st ? ' selected="selected"' : '').'>'.$menu_prefix.$working_prefix.apply_formatting($working_title).'</option>';
			}
			$subtitle = $parts[$i]->sections[$j]->subtitle;
			// adds to toc xml
			$xml .= "\t".$item_start.$item_link
				.apply_formatting($working_prefix.$working_title).'</a>'.($subtitle ? '<br />'.$subtitle : '').'</li>'."\r\n";
			// if last section in part, close jump menu group and Table of Contents list
			if($j == count($parts[$i]->sections) - 1) {
				$menu .= "\r\n\t".'</optgroup>';
				$xml .= '</ul>'."\r\n";
				// if list is prefatory close div
				$xml .= ($parts[$i]->type == 'preface' ? '</div>'."\r\n" : '');
				// if list is list (e.g., of illustrations) close div
				$xml .= ($parts[$i]->type == 'list' ? '</div>'."\r\n" : '');
			}
		}
	}
	if($toc) { $document->xml = $xml; }
	return $menu;
}

// generates jump-menu and (listing or index TOC)
function load_listing($type,&$document) {
	$listdata = (substr_count('|tune|composer|composed|key|meter|',$document->sortby) ? 'scores' : 'lyrics');
	
	$index = !$document->section;
	
	if($index && substr_count('title',$document->sortby)) {
		//global database variable array passed to database connection from included "f_dbase.php"
		global $db;
		if(!$db['link']) {
			db_connect($db);
			$db_collection = ($document->collection == '_' ? '%' : $document->collection);
			$results = mysql_query("SELECT title,title_sort,url FROM ". $db["tt3_indices"] ." WHERE type = '".$type."' AND collection LIKE '%/".$db_collection."/%' AND title_sort LIKE '".$document->sortby."|%'");
			while($r = mysql_fetch_assoc($results)) {
				// grabs the first character after the pipe: field = sortby|title
				$indexed = strtoupper( substr($r['title_sort'],strpos($r['title_sort'],'|')+1,1) );
				if(!$indices[$indexed]) {
					$indices[$indexed]['title'] = $r['title'];
					$indices[$indexed]['url'] = $r['url'];
				} else {
					$indices[$indexed]['after'] = true;
				}
			}
			db_disconnect($db);
		}
	}
	
	global $rewrite_title_url;
	// returns jump menu code, changes listing xml passed by reference
	$xml = '';
	
	// all listings have at least a Whole List view and sort Index
	$menu =
	"\r\n\t".'<option value="_">Whole List</option>'
	."\r\n\t".'<option value=""'.($index ? ' selected="selected"' : '').'>'.ucfirst($document->sortby).' Index</option>'
	."\r\n\t".'<optgroup>';

	if($index) {
		$xml .= '<h2 class="first">'.ucfirst($document->sortby).' Index'.($document->order == 'descending' ? ' (descending)': '').'</h2>';
		$xml .= '<ul>'."\r\n";
	}
	//shorter alias
	$items = &$document->items;
	// flag for looking for previous section title
	$pFlag = ($document->section ? true : false);
	// flag for looking for next section title immediately set if at Index
	$nFlag = ($document->section ? false : true);
	if($document->section == '_') {
		$document->next_title = ucfirst($document->sortby).' Index';
		$nFlag = false;
	}
	// looping variable to compare with object's matching variable to determine if new section is needed
	$sort_m = false;
	foreach($items as $key => $item) {
		if($sort_m !== $item->sort_match) {
			$sort_m = $item->sort_match;
			// save it as the title immediately next after the current one; turn flag off
			if($nFlag) {
				$document->next_title = $item->sort_real;
				$nFlag = false;
			}
			// filters new section based on requested $document->section
			if(title_url_format($item->sort_real) == $document->section || $document->section == '_') {
				$si++;
				// spells out accidentals notation for browsers that don't render flats and sharps in HTML entities
				if(substr_count($item->sort_real,'&#')) {
					$item->sort_real = '<span title="'.preg_replace(array("'&#9837;'","'&#9839;'"),array(" flat"," sharp"),$item->sort_real).'">'.$item->sort_real.'</span>';
				}
				// if section is a number, it must be linked specially
				$section_url = ($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real));
// @CD does not include section links
				$section_link = /*'<a href="'.level_url().$document->type.'/'.$document->collection.'/'.$section_url.'/'.add_query().'"'>'.*/$item->sort_real;//.'</a>';
// ^^^				
				$sort_section =
				"\r\n\t".'<tr><td class="section_head"'.($type != 'texts' ? ' colspan="2"' : '').'><div class="left"><p>'.$section_link.'</p></div><div class="right"><p><a href="#logo" title="Return to top">^</a></p></div></td></tr>';
				if($type == 'texts') {
					$sort_section .=
					"\r\n\t".'</table>'
					."\r\n\t".'<table summary="">'."\r\n\t";
				}
				$pFlag = false; // stop looking for previous section title
				 // if not at Whole List, start looking for next section title
				if($document->section != '_') { $nFlag = true; }
				// if no previous title being found yet, and this is not the whole list,
				// this indicates the current title is the first section, so the previous is the Whole List
				if(!$document->prev_title && $document->section != '_') {
					$document->prev_title = 'Whole List';
				}
			} else {
				// save it in case it is the title immediately previous to the current one
				if($pFlag) { $document->prev_title = $item->sort_real; }
			}
			if($index) {
				if(substr_count('title',$document->sortby)) {
					$xml .= "\r\n\t".'<li class="index">'
						.'<div class="item"><a href="'.($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real)).'/?sortby='.$document->sortby.($document->order == 'descending' ? '&amp;order=descending' : '').'">'.$item->sort_real.'</a></div>'
						.'<div class="excerpt"><span><a href="'.level_url().$indices[$item->sort_real]['url'].'">'.$indices[$item->sort_real]['title'].'</a>'.($indices[$item->sort_real]['after'] ? ', ... ' : '').'</span></div>'
						.'</li>';
				} else {
					$xml .= "\r\n\t".'<li><a href="'.($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real)).'/?sortby='.$document->sortby.($document->order == 'descending' ? '&amp;order=descending' : '').'">'.$item->sort_real.'</a></li>';
				}
			}
			$menu .= "\r\n\t".'<option value="'.($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real)).'"'.(title_url_format($item->sort_real) == $document->section ? ' selected="selected"' : '').'>'.$item->sort_real.'</option>';
		} else {
			$sort_section = '';
		}

		// if listing texts, update collection name, in case a title url needs rewriting
		if($type == 'texts') { $rewrite_title_url = $item->collection; }
		
		$url_title = title_url_format($item->title);
		$tooltip_title = title_tooltip_format($item->title);

		// variables used if sorting by scores
		$scoretitle;
		$scoreauthor;
		$scoremeter;
		$scorekey;
		if($type == 'music') {
			// inserts links to Scorch pages, if available
			$score_links = '';
			if($item->score) {
				foreach($item->score as $s_id => $score) {
					$s_t = '_'.strtolower(str_replace('_','',title_url_format($score->title))).'_';
					// if sorting by score data, score must match title key

					if((substr_count($key,$s_t) && $listdata == 'scores') || $listdata != 'scores') {
						if($score->sib) {
							// create tooltip-compatible title|author string
							$tooltip = ($score->title ? $score->title.' | ' : '').preg_replace("'^.+>(.*?)<[\s\S]+$'","$1",$score->author);
							$score_links .= '<a href="'.level_url().'music/'
							// append score id, if any, to link
							.title_url_format($item->title).'/score'.($score->id ? '_'.$score->id : '')
							.'/" title="'.title_tooltip_format($tooltip).'">'
							.'<img src="'.level_url_webfront().'link_score.gif" alt="'.title_tooltip_format($tooltip).'" /></a> ';
						} elseif($listdata == 'scores') {
							continue 2; // if sorting by scores and no score is available, skip
						}
						$scoretitle = $score->title;
						$scoreauthor = preg_replace("'^.+>(.*?)<[\s\S]+$'","$1",$score->author);
						$scoremeter = $score->meter;
						$scorekey = preg_replace(array("'b'","'s'"),array("&#9837;","&#9839;"), $score->key); // converts flats (b) and sharps (s) notation to HTML characters
						if(substr_count($scorekey,'&#')) {
							$scorekey = '<span title="'.preg_replace(array("'&#9837;'","'&#9839;'"),array(" flat"," sharp"),$scorekey).'">'.$scorekey.'</span>'; // for browsers that don't render flats and sharps notation, spell out
						}
						// the limitations of listing by scores prevents multiple scores per listing
						if($listdata == 'scores') { break; }
					}
				}
			}
		} elseif($type == 'texts') {
			if($document->sortby == 'issue') { // search engines should prioritize sorting by issue page
				$coll = str_replace('_',' ',$document->collection);
				$coll_prefix = (substr_count('|Foundation Truth|Treasures of the Kingdom|Dear Princess|',$coll) ? $coll.' ' : '');
			}
			$text_header =
			"\r\n\t".'<tr><td class="icon" rowspan="3"><a href="'.level_url().'texts/'.$url_title.'/" title="'.$coll_prefix.$tooltip_title.'"><img src="'.level_url().'library/texts/'.$url_title.'/'.$url_title.'.jpg" alt="'.$coll_prefix.$tooltip_title.'" width="120" height="120" /></a></td>'
			."\r\n\t\t".'<td class="top" colspan="2"><div class="right"><p>'.convert_date($item->date).'</p></div></td></tr>';
		}
		// filters item based on requested $document->section
		if(title_url_format($item->sort_real) == $document->section || $document->section == '_') {
			$sj++;
			// if under copyright (only applies to music sorted by number), display symbol
			if($document->sortby == 'number' && $item->copyright['owner'] != '?' && $item->copyright['year'] >= 1923 && !$item->copyright['cc']) {
				$copyright = '<span class="red">&copy;</span> ';
			} else {
				$copyright = '';
			}
			// if sorting by scores, uses score data if present
			$titleline = ($listdata == 'scores' && strlen($scoretitle) ? $scoretitle : $item->title);
			// if year data is present, list only first year given
			$authorline = ($listdata == 'scores' ? $scoreauthor : $item->author).($item->year ? ', '.preg_replace("'(\d{4}).*'","$1",$item->year) : '');
			$subjectline = ($listdata == 'scores' ? $scoremeter : ($document->sortby == 'scripture' ? $item->scripture : $item->subject));
			//adds, if any present, (score key) or (collection music number, if all '_' collections are not being listed)
			if($scorekey && $listdata == 'scores') {
				$parenline = ' <span> ('.$scorekey.')</span>';
			} elseif($item->coll_id && $document->collection != '_') {
				$parenline = ' <span> (#'.$item->coll_id.')</span>';
			} else {
				$parenline = false;
			}
			static $tableclass; // flag for setting first table to have 0 margin-top
			$xml .= "\r\n\r\n\t"
			.'<table'.(!$tableclass ? ' class="first"' : '').' summary="">'
			.$sort_section
			 //adds 
			.$text_header
// @CD copyrighted songs not linked
			."\r\n\t".'<tr><td class="title">'.$copyright.'<span class="title">'.$score_links.(substr_count($item->blurb,"/  ...") ? apply_formatting($titleline) : '<a href="'.level_url().$type.'/'.$url_title.'/">'.apply_formatting($titleline).'</a>').'</span>'
// ^^^		
			.$parenline.'<span> | '.str_replace(' ','&nbsp;',$authorline).'</span></td>'
			."\r\n\t".'<td class="subject"><span>'.$subjectline.'</span><a href="#"></a></td></tr>'
			// simple formatting of em dash only, as curly quotes are not distinguishable, and not easily searched from brower
			.($listdata != 'scores' ? "\r\n\t".'<tr><td class="blurb" colspan="2"><div>'.str_replace('--','&#8212;',$item->blurb).'</div></td></tr>': '')
			."\r\n\t".'</table>';
			$tableclass = true;
		}
	}
	// closes jump menu group
	if(!$xml && $document->section) {
		$menu .= "\r\n\t".'<option value="" selected="selected">&#8212;no match&#8212;</option>';
		$xml = '<h3>No matches found. Try some other options.</h3>';
	} elseif($document->section) {
		// displays # of items (in # of sections, if more than 1)
		$xml = '<h3 class="first">'.$sj.' item'.($sj > 1 ? 's' : '').($si > 1 ? ' in '.$si.' '.str_replace('composed','year',$document->sortby).' sections' : '').':</h3>'."\r\n\r\n" . $xml;
	}
	if($index) {
		$xml .= '</ul>'."\r\n";
	}
	$menu .= "\r\n\t".'</optgroup>';

	$document->xml .= $xml;
	return $menu;
}
?>