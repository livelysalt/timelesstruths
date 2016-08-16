<?php

// flag for generating CD content
$cd = false;

/*
function getmicrotime() { 
	list($usec, $sec) = explode(" ",microtime()); 
	return ((float)$usec + (float)$sec); 
} 

if($_COOKIE['timeless_stats']) {
	$t1 = getmicrotime();
}
*/

// common functions
require_once "includes/f3_common.php";

// if url is not in correct url format, redirect
if( ($type_url = strtolower($type)) != $type
	|| ($title_url = title_url_format($title)) != $title
	|| ($section_url = title_url_format($section)) != $section) {
	// url built up as available: all $types should be lowercase, all $titles and $sections according to f3_common title_url_format syntax
	$relative_url = ($type ? $type_url.'/'.($title ? $title_url.'/'.($section ? $section_url.'/' : '') : '') : '');
	header('Location: http://'. NORMALIZED_DOMAIN .$relative_url);
	exit;
}

// database function always necessary
require_once "includes/f_dbase.php";
// classes_html always required to produce html page
// use the '@cd' version for generating CD content
require_once "includes/classes_html".($cd ? "@cd" : "").".php";
// only one of query, listing, document, or sitesplit required
if(isset($_GET['query'])) {
	require_once 'includes/classes_listing_query.php';
} elseif(is_collection($title)) {
	require_once "includes/classes_listing.php";
} elseif($title) {
	require_once "includes/classes_doc.php";
	// f3_parser required for parsing documents
	require_once "includes/f3_parser.php";
	if($type == 'music') {
		// f3_parse_lyrics required for parsing music lyrics
		require_once "includes/f3_parse_lyrics.php";
	}
} elseif(!$title) {
	require_once "includes/classes_site.php";
}

// home page set to 'welcome' for $type checking purposes
if(!$type) $type = 'welcome';

// use the '@cd' version for generating CD content
require_once "includes/f3_cache".($cd ? "@cd" : "").".php";
// if successful, populates $html with cached file
if(!manage_cache("extract")) {
	// create html for page
	$page = new Page($type, $title, $section);
	// collect html
	$html = $page->output();
	// add dynamic custom anchor if requested
	if(strlen($_GET['anchor'])) {
		// ignores quotes and markup
		$anchor_find = preg_replace("' '","( |&#8220;|&#8221;|<.*?>)+",preg_quote($_GET['anchor']));
		$anchor_find = str_replace("\\'","&#8217;",$anchor_find);
		// set anchor at first phrase (outside tags) matching argument
		$html = preg_replace("'(<div class=\"content\"[\s\S]*?".">[^<]*)"
			."(".$anchor_find."[\s\S]*?</body>)'"
			,"$1<a name=\"".str_replace("%5C%27","'",rawurlencode($_GET['anchor']))."\"></a>$2"
			,$html,1);
	}
	// cache page
	if (!DEV) manage_cache("insert");
}

// if user has specified non-default settings for score zoom and note type, modify html
if(stristr($_SERVER['REQUEST_URI'],'/score')) {
    $format = (isset($_COOKIE['m_score_format']) ? $_COOKIE['m_score_format'] : DEFAULT_M_SCORE_FORMAT);
    $zoom   = (isset($_COOKIE['m_score_zoom'])   ? $_COOKIE['m_score_zoom']   : DEFAULT_M_SCORE_ZOOM);
    $notes  = (isset($_COOKIE['m_score_notes']) && $_COOKIE['m_score_notes'] == '+' ? 'shaped' : DEFAULT_M_SCORE_NOTES);

    $html = preg_replace("/ga\('send','pageview'\)/","ga('send','pageview',{'dimension1':'$format','dimension2':'$notes'})",$html);

	if ($zoom != DEFAULT_M_SCORE_ZOOM) {
		// removes standard zoom selection
		$html = preg_replace("'(<select id=\"score-zoom\"[\s\S]*?)( selected(?:=\"selected\")?)'","$1",$html);
		// applies user zoom selection
		$html = preg_replace("'(<select id=\"score-zoom\"[\s\S]*?value=\"$zoom\")'","$1 selected",$html);
        // resizes score
		$html = preg_replace("'(id=\"score\" style=\"width):\d*(px; height):\d*(px;)'","$1:".$zoom."$2:".($zoom * 1.3 + 26)."$3",$html);
	}
    preg_match("/\#score \[data-src=\"(.*?)\"\]/",$html,$m_cache);
    $data_src_cache = $m_cache[1];
    $data_src = "data-src-{$format}-{$notes}";
    preg_match("/$data_src=\"(.*?)\"/",$html,$m_cfg);
    $data_src_cfg = $m_cfg[1];

    if ($data_src_cache != $data_src_cfg) {
        // removes default selections
        $html = preg_replace("'(<select id=\"score-format\"[\s\S]*?)( selected(?:=\"selected\")?)'","$1",$html);
        $html = preg_replace("'(<select id=\"score-notes\"[\s\S]*?)( selected(?:=\"selected\")?)'","$1",$html);
        // applies user selections
        $html = preg_replace("'(<select id=\"score-format\"[\s\S]*?value=\"$format\")'","$1 selected",$html);
        $html = preg_replace("'(<select id=\"score-notes\"[\s\S]*?value=\"$notes\")'","$1 selected",$html);

        // show requested resource configuration if available
        if ($data_src_cfg) {
            $src = $data_src_cfg;
            // copied from /classes_html.php->hScore{}
            if ($format == 'pdf') {
                $score_html =
 /* [2015-01-24]    <object> won't fit-to-container in Chrome <http://forums.asp.net/t/1877403.aspx?Issue+with+embedded+pdf+object+in+chrome+browsers>
//  [2015-01-24]    <object> recommended for Safari mobile    <http://stackoverflow.com/questions/19654577/html-embedded-pdf-iframe> 
                    '<object id="s_pdf" type="application/pdf" data="'.$src.'">
                        <embed src="'.$src.'" type="application/pdf" />
                    </object>';
/*/
                '<iframe id="s_pdf" frameborder="0" src="'.$src.'">
                    <div class="error-message">
                    <p>Sorry, your browser does not support iframes, which are used to display the PDF score.</p>
                    </div>
                </iframe>';
//*/
            }
            if ($format == 'sib') {
                $score_html =
                '<object id="s_sib"
                      type="application/x-sibelius-score"
                      data="'.$src.'">
                    <param name="src" value="'.$src.'" />
                    <param name="scorch_minimum_version" value="3000" />
                    <param name="scorch_shrink_limit" value="100" />
                    <param name="shrinkwindow" value="0" />
                    <div class="error-message">
                    <p>To view, play, and print the sheet music, you need to install a plugin:</p>
                    <ul><li><a class="red" href="http://www.sibelius.com/scorch/">Get the free Scorch plugin</a></li></ul>
                    <p>If you are using Firefox or some other Mozilla browser: when installing the plugin, on the first screen select the Manually Choose Browser Directory option, and then you will need to select the directory where your browser plugins are stored, such as C:\Program Files\Mozilla Firefox\plugins.</p>
                    <p>For more help, see <a class="blue" href="'.level_url().'help/More_Help/Sheet_Music_Scorch_Plugin/">Sheet Music&#8212;Scorch Plugin</a>.</p>
                    </div>
                </object>';
            }
        } else {
            preg_match("'<title>(.+) >'",$html,$m);
            $title = $m[1];
            $subjectline  = urlencode("Request: $title (.{$format})");
            $commentsline = urlencode("I would like to have the .{$format} sheet music for this song added to your priority list. Thank you.");
            $url          = urlencode(str_replace('?updatecache','',"http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"));
            $score_html = '<div class="error-message">
                <p>Sorry, the sheet music is not available in this format (.'.$format.'):</p>
                <ul><li><a class="blue" href="'.level_url().'contact/?subject='.$subjectline.'&amp;comments='.$commentsline.'&amp;url='.$url.'">Request sheet music</a></li></ul>
                </div>';
        }
        $html = preg_replace("'<!--#score:start-->([\s\S]+?)<!--#score:end-->'",$score_html,$html);
    }
}

// outputs page
header('Content-type: text/html; charset=utf-8');

echo $html;
