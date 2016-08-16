<?php

date_default_timezone_set('America/Los_Angeles');

define('NORMALIZED_LOCALHOST',   (!stristr($_SERVER['HTTP_HOST'], 'timelesstruths.org') ? $_SERVER['HTTP_HOST'].'/' : ''));
define('NORMALIZED_DOMAIN',      NORMALIZED_LOCALHOST . 'library.timelesstruths.org/');
define('DEFAULT_M_SCORE_FORMAT', 'pdf');
define('DEFAULT_M_SCORE_NOTES',  'standard');
define('DEFAULT_M_SCORE_ZOOM',   760);
define('FILENAME_JS_CSS_DATE',   '2016-08-01');

// reduces multiple sequential forward slashes
if(($r_uri = preg_replace("'//'",'/',$_SERVER['REQUEST_URI'])) != $_SERVER['REQUEST_URI']) {
	header('Location: http://'.$_SERVER['HTTP_HOST'].$r_uri);
}
// trims off trailing arguments
if(substr_count($_SERVER['REQUEST_URI'],'?')) {
	$uri = substr($_SERVER['REQUEST_URI'],0,strpos( $_SERVER['REQUEST_URI'],'?'));
} else {
	$uri = $_SERVER['REQUEST_URI'];
}
// removes first directory if it begins with 'timelesstruths.org', as is the case on localhost/timelesstruths.org/
$uri = preg_replace("'^/(([^\.]+\.)?timelesstruths\.org/)?'",'',$uri);
// trims /'s from both ends of URI
$url = trim($uri,'/');
// splits URL at /'s into array
if(strlen($url)) {
	$url_bits = explode('/', $url);
}
////////////////////////////////////////////////////////////////////////////////////
if ($_GET['dev'] == 'true') {
    setcookie('dev','true', time()+60*60*24*30/*30 days*/,'/','.'.$_SERVER['HTTP_HOST']);
}
define('DEV',($_COOKIE['dev']=='true'));
if ($_GET['ver'] == '4') {
    setcookie('ver','4', time()+60*60*24*30/*30 days*/,'/','.'.$_SERVER['HTTP_HOST']);
}
////////////////////////////////////////////////////////////////////////////////////

preg_match("'[google|msn]bot'",$_SERVER['HTTP_USER_AGENT'],$uacrawler);
if($uacrawler) {
	// Crawlers don't need to contact us, so truncate url arguments
	if($url_bits[0] == 'contact' && strlen($_SERVER['QUERY_STRING'])) {
		header('Location: http://'. NORMALIZED_DOMAIN .'contact/');
		exit;
	}
	// Crawlers don't need to listen to music, so redirect from Music Player
	if(substr_count($_SERVER['QUERY_STRING'],'musicplayer')) {
		header('Location: http://'. NORMALIZED_DOMAIN .'music/');
		exit;
	}
}
// all requests must end with one forward slash
preg_match("'^([^\?]+?)(/+)?(\?.*)?$'",$_SERVER['REQUEST_URI'],$rm);
if(strlen($rm[2]) != 1) {
	header('Location: http://'.$_SERVER['HTTP_HOST'].str_replace('%20','_',stripslashes($rm[1])).'/'.$rm[3]);
	exit;
}
// if section argument is present from jump menu request, redirect to appropriate url
if(isset($_GET['section'])) {
	// a text that has sections which should be redirected to music titles will prefix the section argument with 'music/'
	preg_match("'^music/.+$'",$_GET['section'],$sm);
	if($sm) {
		$relative_url = $_GET['section'];
	} else {
		$relative_url = $url_bits[0].'/'.$url_bits[1].'/'.($_GET['section'] ? $_GET['section'].'/' : '');
	}
	$query = '?'.preg_replace("'section=".urlencode($_GET['section'])."(&|)'",'',$_SERVER['QUERY_STRING']);
	$query = ($query == '?' ? '' : $query);
//	log_change('SECTION'.$relative_url.$query);
	header('Location: http://'. NORMALIZED_DOMAIN .$relative_url.$query);
	exit;
}
// if trying to sort All Music by number,
// or trying to sort non-periodical by issue,
// or trying to sort periodical by title, (or by issue, which is default)
// or trying to sort non-Bible by title only, which is default,
// or trying to sort Bible by collection, THEN redirect to default
if( ($_GET['sortby'] == 'number' && $url_bits[1] == '_') ||
	($_GET['sortby'] == 'issue' && !substr_count('|Foundation_Truth|Treasures_of_the_Kingdom|Dear_Princess|','|'.$url_bits[1].'|')) ||
	(($_GET['sortby'] == 'title' || $_GET['sortby'] == 'issue') && substr_count('|Foundation_Truth|Treasures_of_the_Kingdom|Dear_Princess|','|'.$url_bits[1].'|')) ||
	($_GET['sortby'] == 'title' && $url_bits[0] != 'bible' && !$_GET['order']) ||
	($_GET['sortby'] == 'collection' && $url_bits[0] == 'bible' && !$_GET['order'])
    ) {
	$relative_url = $url_bits[0].'/'.$url_bits[1].'/'.($url_bits[2] ? $url_bits[2].'/' : '').
		($_GET['sortby'] == 'issue' && $_GET['order'] == 'ascending' ? '?order=ascending' : '');
//	log_change('NUMBER'.$relative_url);
	header('Location: http://'. NORMALIZED_DOMAIN .$relative_url);
	exit;
}
if ($_GET['sortby'] == 'scores') $_GET['sortby'] = 'published';
// if changing to a new sortby...
if($_GET['sortlast']) {
	// if a category had been selected, make new sortby request for whole list
	if($_GET['sortlast'] != $_GET['sortby'] && strlen($url_bits[2])) {
		$relative_url = $url_bits[0].'/'.$url_bits[1].'/';
	// otherwise only remove sortlast
	} else {
		$relative_url = $url_bits[0].'/'.$url_bits[1].'/'.(strlen($url_bits[2]) ? $url_bits[2].'/' : '');
	}
//	log_change('SORTLAST'.$relative_url.$query);
	header('Location: http://'. NORMALIZED_DOMAIN .$relative_url.'?'. preg_replace("'&sortlast=([^&]*)'",'',$_SERVER['QUERY_STRING']));
	exit;
}
// leave out unecessary arguments
/* [2016-05] disabled
preg_match("'(&order=ascending)'",$_SERVER['QUERY_STRING'],$qm);
if(strlen($qm[1])) {
	$query = '?'.preg_replace("'(&order=ascending)'",'',$_SERVER['QUERY_STRING']);
	$relative_url = $url_bits[0].'/'.$url_bits[1].'/'.(strlen($url_bits[2]) ? $url_bits[2].'/' : '');
	header('Location: http://'. NORMALIZED_DOMAIN .$relative_url.$query);
	exit;
}
 */
// [2015-02-23] forwards separate /pdf/ url to universal /score/ sheet music url
preg_match("'(music/.+/)pdf(_\d)?/'",$_SERVER['REQUEST_URI'],$rm);
if ($rm) {
    setcookie("updatecache",'1',0,"/");
    header('HTTP/1.1 301 Permanent redirect');
    header('Location: http://'. NORMALIZED_DOMAIN .$rm[1]."score".$rm[2]."/?format=pdf");
    exit;
}
// [score] convert url arguments to set cookies: format | zoom | notehead type
//           [1]                                    [2]                   [3]                          [4]
preg_match("'(music/.+/score(?:_\d)?/)\?(?:&?format=(sib|pdf))?(?:&?notes=(standard|shaped))?(?:&?zoom=(\d+))?'",$_SERVER['REQUEST_URI'],$rm);
if ($rm && ($rm[2] || $rm[3] || $rm[4])) {
	// only change cookie value if url argument is present
    if($rm[2]) setcookie("m_score_format",($rm[2] != DEFAULT_M_SCORE_FORMAT ? $rm[2] : ''), time()+100000000,"/","",0);
    if($rm[3]) setcookie("m_score_notes", ($rm[3] == 'shaped' ? '+' : '')           , time()+100000000,"/","",0);
	if($rm[4]) setcookie("m_score_zoom",  ($rm[4] != DEFAULT_M_SCORE_ZOOM   ? $rm[4] : ''), time()+100000000,"/","",0);
	header('Location: http://'. NORMALIZED_DOMAIN . $rm[1] . (isset($_GET['updatecache']) ? '?updatecache' : ''));
	exit;
}
// [2016-07-14] forwards /hifi/ /lofi/ /lowm/ to audio player
//           [1]    [2]   [3]             [4]
preg_match("'(music/(.+)/)(hifi|lofi|lowm)(_\d)?/'",$_SERVER['REQUEST_URI'],$rm);
if ($rm) {
    $href = "../../library/music/". $rm[2][0] ."/". $rm[2] ."/". $rm[2] . $rm[4] .".mp3";
    setcookie("player-load-href",$href,0,"/");
    header('HTTP/1.1 301 Permanent redirect');
    header('Location: http://'. NORMALIZED_DOMAIN .$rm[1]);
    exit;
}
// [audio] convert url arguments to set cookies: quality
//                   [1]
preg_match("'quality=(standard|low)'",$_SERVER['REQUEST_URI'],$rm);
if ($rm && ($rm[1])) {
    // only change cookie value if url argument is present
    if($rm[1]) setcookie("t_audio_quality",($rm[1] == 'low' ? 'low' : '') ,time()+100000000,"/",'.'.$_SERVER['HTTP_HOST'],0);
    header('Location: http://'. $_SERVER['HTTP_HOST'] . preg_replace("'quality=(standard|low)'",'',$_SERVER['REQUEST_URI']) . (isset($_GET['updatecache']) ? '&updatecache' : ''));
    exit;
}

//print_r($url_bits); exit;
// if not one of the standard directory types, redirect to home page
if($url_bits[0] && !substr_count('/bible/texts/music/about/search/help/contact/feeds/subscription/','/'.$url_bits[0].'/')) {
	header('Location: http://'. NORMALIZED_DOMAIN .'search/?404='.$url_bits[0]);
	exit;
}

// forward Bible from old location to new
if ($url_bits[0] == 'bible') {
    $book_or_collection = $url_bits[1]; // `Genesis` or `New_Testament` or `_`
    $chapter_or_section = $url_bits[2]; // `5` or `_` or `F`
    
    if (substr_count('/_/Old_Testament/New_Testament/',$book_or_collection)) {
        $q = '';
    } else {
        $q = str_replace("_",' ',$book_or_collection);
        $is_chapter = preg_match("'\d'",$chapter_or_section);
        if ($q) {
            $q = (!$is_chapter ? "book: " : '') . $q . ($is_chapter ? " $chapter_or_section" : '');
        }
    }
    $uri = 'http://'. str_replace('library.','bible.',NORMALIZED_DOMAIN) . $q;
    header('HTTP/1.1 301 Permanent redirect');
    header("Location: $uri");
    exit;
}

//*/////////////////////////////////////////////////////////////////////////////////////
function log_change($logline) {
	if(!file_exists('logline.txt')) { return; }

	$fp = fopen('logline.txt', 'ab');
	fwrite($fp,"\r\n--------------\r\n".$logline);
	fclose($fp);
}
/////////////////////////////////////////////////////////////////////////////////////*/
// populates standard variables to pass to page-generation functions
$type = $url_bits[0]; $title = $url_bits[1]; $section = $url_bits[2];

if(isset($_GET['query'])) {
	// if not at search page, or nothing querried, then forward
	if(!substr_count($_SERVER['REQUEST_URI'],'search/') || !$_GET['query']) {
		header('Location: http://'. NORMALIZED_DOMAIN .'search/');
		exit;
	}
	include '_query.php';
	if($_GET['output']) { exit; } // if specific output is defined, the query should not be displayed within the context of the Library
}

if($type == 'feeds') {
	include 'includes/classes_feeds.php';
} elseif($type == 'subscription') {
	include 'includes/classes_subscription.php';
} elseif($type == 'music' && isset($_GET['musicplayer'])) {
	include 'includes/classes_player.php';
} else {
	include '_page.php';
}
?>