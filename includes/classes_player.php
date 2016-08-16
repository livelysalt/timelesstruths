<?php
// classes_player.php

// global variables available from _urlhandler.php:
// $type	- always is 'music'
// $title	- defaults to '', otherwise is the title of the lyrics associated with the MIDI
// $section - defaults to '', otherwise is 'midi_#', where '_' is present if '#' is a non-'0' MIDI id

define(MIDI_DELAY, 3); // default number of seconds to extend midi playback

// the following might be encountered if users take a shortcut from the lyrics page
if($title) {
	if(!$section) {
		$section = (!$_GET['musicplayer'] || $_GET['musicplayer'] == 'midi' ? 'midi' : 'lofi');
		$forward = true;
	}
	if(!$_GET['musicplayer']) {
		if($section == 'lofi' || $section == 'hifi') {
			$_GET['musicplayer'] = 'mp3';
		} else {
			$_GET['musicplayer'] = 'midi';
		}
		$forward = true;
	}
	if($forward) {
		header('Location: http://'. NORMALIZED_DOMAIN .'music/'.$title.'/'.$section.'/?musicplayer='.($_GET['musicplayer'] ? $_GET['musicplayer'] : 'midi').($_GET['delay'] ? '&delay='.(int)$_GET['delay'] : '').(isset($_GET['stop']) ? '&stop' : '') );
		exit;
	}
}
// redirect from MP3 and MIDI form request
if($_POST['tune']) {
	$_POST['tune'] = ($_POST['musicplayer'] == 'mp3' ? preg_replace("'/(.*?)(?=[_/])'",'/'.$_POST['quality'],urldecode($_POST['tune']),1) : urldecode($_POST['tune']) );
	header('Location: http://'. NORMALIZED_DOMAIN .'music/'. $_POST['tune'] /*preg_replace("'/(.*?)(?=[_/])'",'/'.$_POST['quality'],urldecode($_POST['tune']),1)*/.'?musicplayer='.($_POST['musicplayer']).($_POST['delay'] != MIDI_DELAY ? '&delay='.(int)$_POST['delay'] : '') );
	exit;
}

require_once "includes/f_dbase.php";
require_once "includes/f3_common.php";

require_once "includes/f3_cache.php";
// if successful, populates $html with cached file
if(!manage_cache("extract")) {

	require_once "getid3/getid3.php";
	// create html for page
	$player = new Player();
	// collect html
	$html = $player->output();
	// cache page
	manage_cache("insert");
}

echo $html;

// log visit
//$table = "stats_tt3"; include "_stats/write_stat.php";




//////////////////////////////////////////////////////////////////////

class Player {
	var $html;
	
	var $h_meta;
	var $h_body;
	var $h_navbar;
	var $h_info;
	
	var $music;
	
	function Player() {
		if($_GET['musicplayer']) {
			$this->music = new Music();
		}

		$this->h_meta = new hMeta($this->music);
		$this->h_body = new hBody();
		$this->h_navbar = new hNavBar($this->music);
		$this->h_info = new hInfo($this->music);
	}
	
	function output() {
		$this->stitch();
		return $this->html;
	}
	
	function stitch() {
		global $title;
		$this->html = false;
		$this->html .= $this->h_meta->pre;
		$this->html .= $this->h_meta->meta;
		$this->html .= $this->h_meta->style;
		if($title) { $this->html .= $this->h_meta->script; }
		$this->html .= $this->h_meta->post;
		
		$this->html .= $this->h_body->pre;
		$this->html .= $this->h_body->logo;
		$this->html .= $this->h_navbar->nav;
		$this->html .= $this->h_info->html;
		$this->html .= $this->h_body->post;
	}
}

class Music {
	var $option_list;
	
	var $type;
	var $lyrics;
	var $lyrics_url;
	var $id;
	var $id_url;
	var $quality;
	var $title;
	var $author_xml;
	var $copyright_xml;
	var $key;
	var $meter;
	var $miditime;
	var $getID3;
	
	var $prev = array('title'=>'','lyrics_url'=>'', 'music_url'=>'');
	var $next = array('title'=>'','lyrics_url'=>'', 'music_url'=>'');
	
	var $code;
	var $download;
	
	function Music() {
		global $title,$section;
		if($title) {
			$this->lyrics_url = $title;
			$this->id = (substr($section,5) ? substr($section,5) : 0);
			$this->id_url = ($this->id ? '_'.$this->id : '');
		}

		//global database variable array passed to database connection from "f_dbase.php"
		global $db, $title;
		db_connect($db);

		if($_GET['musicplayer'] == 'mp3') {
			$this->type = 'mp3';
			$this->quality = '_'.substr($section,0,2);
			
            $path_base = $path = 'library/music/'.substr($this->lyrics_url,0,1).'/'.$this->lyrics_url.'/'.$this->lyrics_url.$this->id_url;
            if ($this->quality == '_hi' && file_exists($path_base.'.mp3')) $this->quality = '';
			$path = $path_base.$this->quality.'.mp3';
			$this->download = '<a class="source" href="'.level_url().$path.'" title="Download '.($this->quality == '_hi' ? 'HiFi' : 'LoFi').' mp3 source file ('.(int)(@filesize($path)/1024).' KB)">.mp3</a>';
			$this->code = "\r\n".'<div class="playtime"><object name="fStream" id="fStream" type="application/x-shockwave-flash"'
					.' data="'.level_url_webfront().'f_stream2.swf?mp3='.urlencode(level_url().$path).'&amp;nextSong=true">'
					.'<param name="movie" value="'.level_url_webfront().'f_stream2.swf?mp3='.urlencode(level_url().$path).'&amp;nextSong=true" /><param name="scale" value="exactfit" />'
					.'<p class="warning">You need to upgrade your browser to be able to play this music in the background. Streaming music requires the free Flash plugin (version 6 or greater).</p>'
					.'</object></div>';
					
			// defaults to 'hi' quality mp3s
			$sql = "SELECT s.*, m.title AS m_title FROM tt3_scores s
                    LEFT JOIN tt3_music m ON (m.url = s.m_url)
                    WHERE s.". (substr($section,0,2) ? substr($section,0,2) : 'hi');
			$results = mysql_query($sql);
		} elseif($_GET['musicplayer'] == 'midi') {
			$this->type = 'midi';
			$this->getID3 = new getID3;
			
			$path = 'library/music/'.substr($this->lyrics_url,0,1).'/'.$this->lyrics_url.'/'.$this->lyrics_url.$this->id_url.'.mid';
			$this->download = '<a class="source" href="'.level_url().$path.'" title="Download midi source file">.mid</a>';
			if(substr_count($_SERVER['HTTP_USER_AGENT'],'MSIE') || substr_count($_SERVER['HTTP_USER_AGENT'],'Opera')) {
				$this->code = "\r\n".'<bgsound src="'.level_url().$path.'" loop="1" />';
			} else {
				$this->code = "\r\n".'<embed src="'.level_url().$path.'" hidden="true" autostart="true" loop="false" />';
			}
			
			$results = mysql_query("SELECT * FROM ". $db['tt3_scores'] ." WHERE mid");
		}
		// if no results were given for some reason, return to Music Player home
		if(!$results) {
			header('Location: http://'. NORMALIZED_DOMAIN .'music/?musicplayer');
			exit;
		}

		$arr_list = array();
		while($rScores = mysql_fetch_assoc($results)) {
			$s_title = title_unbracket($rScores['title']);

			if($title && $rScores['m_url'] == $this->lyrics_url && $rScores['s_id'] == $this->id) {
				$this->lyrics     = $rScores['m_title'];
				$this->title      = $s_title;
				$this->author_xml = $rScores['author'];
				$this->copyright_xml = $rScores['copyright'];
				$this->key = $rScores['keytone'];
				$this->meter = $rScores['meter'];
				if($this->type == 'midi') {
					$info = $this->getID3->analyze('library/music/'.substr($this->lyrics_url,0,1).'/'.$this->lyrics_url.'/'.$this->lyrics_url.$this->id_url.$this->quality.'.mid');
					$this->miditime = $info['playtime_seconds'];
				}
			}
			if ($this->type == 'mp3') { // MP3 sorted by lyrics title
				$lsort = strtolower(str_replace('_','',$rScores['m_url']));
			} elseif($this->type == 'midi') { // MIDI sorted by score title (tune name)
				$lsort = strtolower(str_replace('_','',title_url_format($s_title)));
			}
			$arr_list[$lsort]['lyrics']   = $rScores['m_title'];
			$arr_list[$lsort]['id_url']   = $this->id_url;
			$arr_list[$lsort]['title']    = $s_title;
			$arr_list[$lsort]['midi_url'] = $rScores['m_url'].'/midi'.$this->id_url.'/';
			$arr_list[$lsort]['mp3_url']  = $rScores['m_url'].'/'.substr($section,0,2).($title ? 'fi' : 'hifi').$this->id_url.'/';
		}
		
		if($this->type == 'midi') {
			$this->code .= "\r\n".'<div class="playtime"><object name="fStream" id="fStream" type="application/x-shockwave-flash"'
					.' data="'.level_url_webfront().'f_stream.swf?miditime='.round($this->miditime,1).'">'
					.'<param name="movie" value="'.level_url_webfront().'f_stream.swf?miditime='.round($this->miditime,1).'" /><param name="scale" value="exactfit" />'
					.'</object></div>';
		}
		
		// if title not found, return to Music Player home
		if($title && !$this->title) {
			header('Location: http://'. NORMALIZED_DOMAIN .'music/?musicplayer');
			exit;
		}
		
		// list sorted according to lyrics title
		ksort($arr_list);

		$search_prev = ($title ? true : false);
		$search_next = ($title ? false : true);
		foreach($arr_list as $key => $arr_item) {
			if($search_next) {
				$this->next['title'] = $arr_item['title'];
				$this->next['lyrics_url'] = title_url_format($arr_item['lyrics']);
				$this->next['music_url'] = $arr_item[$this->type.'_url'];
				$search_next = false;
			}
			if($key == strtolower(str_replace('_','',title_url_format(($this->type == 'mp3' ? $this->lyrics : $this->title)))) ) {
				$search_prev = false;
				$search_next = true;
			}
			if($search_prev) {
				$this->prev['title'] = $arr_item['title'];
				$this->prev['lyrics_url'] = title_url_format($arr_item['lyrics']);
				$this->prev['music_url'] = $arr_item[$this->type.'_url'];
			}
			if(!$title) {
				$this->option_list .= "\n".'<option value="'.$arr_item[$this->type.'_url'].'">'.($this->type == 'midi' ? $arr_item['title'] : $arr_item['lyrics']).'</option>';
			}
		}
		
		// closes database connection
		db_disconnect($db);
	}
}

class hMeta {
	var $html;
	function hMeta($music) {
		$this->pre = '
		<?xml version="1.0"?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
		
		<head>';
		$this->meta = '
			<title>&#x266b; '.($music->title ? $music->title : 'Timeless Truths Music Player').'</title>
			<meta name="description" content="A free online library of books, music, magazines, and more." />
			<meta http-equiv="content-type" content="text/html; charset=utf-8" />
			<meta http-equiv="Content-Style-Type" content="text/css" />';
		$this->style = '
			<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_player.css" />';
		$this->script = '
			<script type="text/javascript">
			<!--
			function nextSong() {
				if(timerID) { clearTimeout(timerID); }
				
				var a_next = document.getElementById("next");
				if(a_next) {
					window.location = a_next.href;
				} else {
					window.location = "'.level_url().'music/?musicplayer='.$music->type.'";
				}
			}'.($_GET['musicplayer'] == 'midi' ? '
			function settime() {
				// default to '.MIDI_DELAY.'-second delay
				timerID = setTimeout("nextSong()", '.(int)(($music->miditime + (isset($_GET['delay']) ? $_GET['delay'] : MIDI_DELAY) ) * 1000).');
			}' : '').($_GET['musicplayer'] == 'mp3' ? '
			function fStream_DoFSCommand(command, args) {
				if (command == "nextSong") {
					timerID = setTimeout("nextSong()", 1000);
				}
			}
			// Hook for Internet Explorer
			if (navigator.appName && navigator.appName.indexOf("Microsoft") != -1 && 
				  navigator.userAgent.indexOf("Windows") != -1 && navigator.userAgent.indexOf("Windows 3.1") == -1) {
				document.write("<script language=VBScript\> \n");
				document.write("on error resume next \n");
				document.write("Sub fStream_FSCommand(ByVal command, ByVal args)\n");
				document.write("  call fStream_DoFSCommand(command, args)\n");
				document.write("end sub\n");
				document.write("</script\> \n");
			}' : '')
			// asynchronous Google Analytics tracking
			.'var _gaq = _gaq || []; _gaq.push(["_setAccount","UA-1872604-1"],["_setDomainName",".timelesstruths.org"],["_trackPageview"]);'
			.'
			//-->
			</script>';
		$this->post = '
		</head>';
	}
}

class hBody {
	var $pre;
	var $post;
	function hBody() {
		global $title;
		$musicplayer = 'musicplayer'.($_GET['musicplayer'] ? '='.$_GET['musicplayer'] : '');
		$delay = ($_GET['delay'] ? '&amp;delay='.$_GET['delay'] : '');
		$this->pre = "\r\n"
		.'<body'.($title && $_GET['musicplayer'] == 'midi' && !isset($_GET['stop']) ? ' onload="settime()"' : '').'>';
		$this->logo = "\r\n"
		.'<div id="header">
		<div id="logo"><a href="'.level_url().'music/?musicplayer'.$delay.'"><span>Timeless Truths Music Player</span></a></div>'
			.'<div id="globalnav">
			<div'.($_GET['musicplayer'] == 'mp3' ? ' class="current"' : '').' id="tab-left"><a href="'.level_url().'music/?musicplayer=mp3">MP3</a></div>
			<div'.($_GET['musicplayer'] == 'midi' ? ' class="current"' : '').' id="tab-right"><a href="'.level_url().'music/?musicplayer=midi'.$delay.'">MIDI</a></div>
			<div id="site-links"><a href="'.level_url().'music/?musicplayer'.$delay.'" title="Music Player start page">Home</a> | <a href="'.level_url().'help/More_Help/Music_Player/" target="wTimeless" onclick="wTimeless = window.open(this.href,\'wTimeless\'); wTimeless.focus();" title="Learn about the Music Player">Help</a></div></div>
		</div>';


		$google_analytics = '<script type="text/javascript">(function(){if(document.location.hostname=="localhost"){return;}var ga=document.createElement("script");ga.type="text/javascript";ga.async=true;ga.src="http://www.google-analytics.com/ga.js";var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(ga,s);})();</script>';
		$this->post = "\r\n"
		.$google_analytics
		.'</body>'."\r\n"		
		.'</html>';
	}
}

class hNavBar {
	var $nav;
	function hNavBar($music) {
		global $title;
		$delay = ($_GET['delay'] ? '&amp;delay='.(int)$_GET['delay'] : '');
		$musicplayer = 'musicplayer='.$_GET['musicplayer'];
		$play = (isset($_GET['stop']) ? '<a href="./?'.$musicplayer.$delay.'">Play</a>' : ($_GET['musicplayer'] && !$title ? '<a href="'.level_url().'music/'.$music->next['music_url'].'?'.$musicplayer.$delay.'" title="'.title_tooltip_format($music->next['title']).'">Play</a>' : '') );
		$this->nav = "\r\n\t".'<div class="navbar music">'
			.'<div class="right">
			'.($play ? $play : ($title ? '<a href="./?musicplayer='.$_GET['musicplayer'].'&amp;delay='.$_GET['delay'].'&amp;stop">Stop</a>' : '<span class="disabled">Play</span>') ).'
			| &nbsp;&nbsp;<span'.($music->prev['lyrics_url'] ? '><a id="prev" href="'.level_url().'music/'.$music->prev['music_url'].'?'.$musicplayer.$delay.'" title="Previous: '.title_tooltip_format($music->prev['title']).'">&lt;&lt;</a>' : ' class="disabled">&lt;&lt;').'</span>&nbsp;&nbsp;
			| &nbsp;&nbsp;<span'.($music->next['lyrics_url'] ? '><a id="next" href="'.level_url().'music/'.$music->next['music_url'].'?'.$musicplayer.$delay.'" title="Next: '.title_tooltip_format($music->next['title']).'">&gt;&gt;</a>' : ' class="disabled">&gt;&gt;').'</span>'
			.'</div>'
			.'<div class="left">'
			.($title ? '<a href="../" target="wTimeless" onclick="wTimeless = window.open(this.href,\'wTimeless\'); wTimeless.focus(); parent.focus();" title="'.title_tooltip_format($music->lyrics).' [Open full page in new window]">View Lyrics</a>' : '')
			.'</div>'
			."\r\n\t".'</div>';
	}
}

class hInfo {
	var $html;
	function hInfo($music) {
		if($music->title) {
			$this->html = "\r\n".'<div class="info '.(!isset($_GET['stop']) ? 'musicplay' : '').'">
				'.(!isset($_GET['stop']) ? $music->code : '').'
				<fieldset>
					<p><span class="title">'.$music->title.'</span> ['.$music->download.']</p>
					<p>'.format_author($music->author_xml).'</p>
					<p class="last">Copyright: <a class="red" rel="license" href="http://creativecommons.org/licenses/publicdomain/" target="wCreative" onclick="wCreative = window.open(this.href,\'wCreative\'); wCreative.focus();" title="View license details in new window">Public Domain</a></p>
					<hr />
					<p class="first">Key: '.preg_replace(array("'b'","'(?<!\&)\#'"),array("&#9837;","&#9839;"), $music->key).'</p>
					<p>Meter: '.$music->meter.'</p>
				</fieldset>'
				."\r\n".'</div>';
		} else {
			if($_GET['musicplayer'] == 'midi') {
				$this->html = "\r\n".'<div class="home">
						<p class="title first">Music Player > MIDI</p>
						<form id="flist" name="flist" action="./?musicplayer=midi" method="post">
						<p><label for="tune">Start with:</label>
						<select style="width:180px;" name="tune" id="tune">
						'.$music->option_list.'
						</select><input type="hidden" name="musicplayer" value="midi" /></p>
						<p>Select a tune, and click <input type="submit" value="Play" /></p>
						<p><label for="delay">Set delay:</label>
						<input type="text" name="delay" style="width:25px" value="'.($_GET['delay'] ? $_GET['delay'] : MIDI_DELAY).'" /> seconds</p>
						</form>'
					."\r\n".'</div>';
			} elseif($_GET['musicplayer'] == 'mp3') {
				$this->html = "\r\n".'<div class="home">
						<p class="title first">Music Player > MP3</p>
						<form id="flist" name="flist" action="./?musicplayer=mp3" method="post">
						<p><label for="tune">Start with:</label>
						<select style="width:180px;" name="tune" id="tune">
						'.$music->option_list.'
						</select><input type="hidden" name="musicplayer" value="mp3" /></p>
						<p>Select a tune, and click <input type="submit" value="Play" /></p>
						<p><input type="radio" name="quality" id="hifi" value="hifi" checked="checked" /> <label for="hifi">high quality</label>
						<input type="radio" name="quality" id="lofi" value="lofi" /> <label for="lofi">dial-up quality</label></p>
						</form>'
					."\r\n".'</div>';
			} else {
				//global database variable array passed to database connection from "f_dbase.php"
				global $db;
				$this->getID3 = new getID3;
				db_connect($db);
				$rMid = mysql_fetch_row(mysql_query("SELECT COUNT(*) FROM ". $db['tt3_scores'] ." WHERE mid"));
				$rHi = mysql_fetch_row(mysql_query("SELECT COUNT(*) FROM ". $db['tt3_scores'] ." WHERE hi"));
/*				$rHi = mysql_query("SELECT hi FROM ". $db['tt3_scores'] ." WHERE hi");
						
				while($rS = mysql_fetch_assoc($rHi)) {
					$this->mp3time += $rS['hi'];
				}
				$rHi[0] = (int)($this->mp3time/60);*/

				$this->html = "\r\n".'<div class="home">
						<p class="title first">Timeless Truths Music Player</p>
						<p>Use the tabs above to select the type of music you\'d like to hear.</p>
						<p><b>MP3</b> contains '.$rHi[0].' recordings
						<br /><b>MIDI</b> contains '.$rMid[0].' tunes</p>'
					."\r\n".'</div>';
				// closes database connection
				db_disconnect($db);
			}
		}
	}
}
?>