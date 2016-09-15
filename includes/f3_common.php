<?php

// FILE f_common.php | common functions

// global boolean set to collection name if text title should be rewritten for url use;
// this only applies to magazine collections,
// where "Number 1 (November 1996)" of "Dear Princess" should be rewritten "Dear_Princess_1"
$rewrite_title_url = false;

// function takes a url string, run urldecode(), stripslashes(), strip_tags(), and replaces all other unsafe characters
function title_url_format($title) {
/*	global $rewrite_title_url;
	if($rewrite_title_url) {
		// very unlikely to have numbers through 100, but just in case
		preg_match("'^Number (\d{1,3})'",$title,$tm);
		// rewrites title only if it matches
		if($tm[1]) { $title = preg_replace("' '","_",$rewrite_title_url).'_'.$tm[1]; }
	}*/
	// replace with unaccented characters
	$title = str_replace(array('&eacute;'),
						 array('e'),
						 $title);
	// replaces music notation flats -> 'b', sharps -> 's', removes other HTML entities
	$title = preg_replace(array("'&#9837;'","'&#9839;'","'\&\#?(.*?)\;'"),array('b','s',''), $title);

	$title = strip_tags( stripslashes(urldecode($title)) );
	$title = preg_replace(array("' |/|[\-]+'","'[\"\'\,\.\!\?\:\(\)]'"),array('_',''),$title);
	return $title;
}

// function takes a name string, puts the last name first, and applies title_url_format()
function author_url_format($author, $url=true) {
	// rearranges Jr. and Sr. suffixes, if any, and last name, if any, e.g.: James Brown, Jr. -> Brown, James, Jr.; does not arrange authors numeric values, e.g.: Psalm 117 -> Psalm 117
	$author = preg_replace("'^(.*?)(?: ([^ ]+))(, [JS]r\.)?$'e","(is_numeric('$2') ? '$1 $2' : '$2, $1$3')",$author);
	// applies url formatting unless requested otherwise
	$author = ($url ? title_url_format($author) : $author);
	return $author;
}

function title_unbracket($title) {
	return preg_replace("'\{[^{}]*\}'",'',$title);
}

// function takes a url string, run strip_tags(), and encodes special characters
function title_tooltip_format($title) {
	return preg_replace(array("'\"(\w)'","'(\w)\"'","'\-\-'","'_'"),array("&ldquo;$1","$1&rdquo;","&mdash;"," "),strip_tags($title));
}

// taking the current "level" of the url, pads front to return relatively to the root of the site
function level_url() {
	global $url_bits;
	for($i=0; $i < count($url_bits); $i++) {
		$leveler .= '../';
	}
	return $leveler;
}

//
function level_url_webfront() {
    // the -/ pseudo folder enables dev branch switching (see root .htaccess)
    return level_url() .'-/assets3/';
}

//
function level_library() {
    return (DEV ? '../../' : '');
}

function add_query() {
	$query = $_SERVER['QUERY_STRING'];
	// does not pass on 'query'
	if($_GET['query']) { $query = ''; }
	// does not pass on 'anchor' or 'updatecache'
	$query = preg_replace("'[&]?(updatecache|anchor=[^&]*)'",'',$query);
	$query = ($query ? '?'.$query : '');
	return $query;
}

// checks if $string exactly matches some library collection
function is_collection($string) {
	$string = title_url_format($string);
	// looks for Bible collection
	preg_match("'>(Whole_Bible|Old_Testament|New_Testament)<'",">".$string."<",$cm);
	if($cm[1]) { return $cm[1]; }
	// looks for Texts collection
	preg_match("'>(All_Texts|Foundation_Truth|Treasures_of_the_Kingdom|Dear_Princess|Books|Articles)<'",">".$string."<",$cm);
	if($cm[1]) { return $cm[1]; }
	// looks for Music collection
	preg_match("'>(All_Music|Select_Hymns|Evening_Light_Songs|Echoes_from_Heaven_Hymnal|The_Blue_Book|Sing_unto_the_Lord)<'",">".$string."<",$cm);
	if($cm[1]) { return $cm[1]; }
	// checks for all collections of some library type, or fails
	return ($string=='_' ? $string : false);
}

// checks if page is a music score page
function is_score() {
	global $url_bits;
	$strScore = substr($url_bits[2],0,5);
    return ($strScore == 'score') ? 'score' : false;
}

// checks if page is an audio-capable page (mp3 or MIDI)
function is_audio($class) {
	global $url_bits;

	if(strtolower($class) == 'document') {
		if(substr_count('/bible/texts/help/','/'.$url_bits[0].'/') && $url_bits[2]) {
			return true;
/*		} elseif($url_bits[0] == 'music' && substr_count('/midi/hifi/lofi/','/'.substr($url_bits[2],0,4).'/')) {
			return true;*/
		}
	}
	return false;
}

// transforms YYYYMMDD in "edited" field to: Month D, YYYY
function convert_date($date_mysql) {
	// removes any dashes from YYYY-MM-DD
	$date_mysql = str_replace("-","",$date_mysql);
	// matches digit sets
	preg_match("'(\d{4})(\d{2})(\d{2})'",$date_mysql,$date);
	// formats date from sets
	return date("F j, Y",mktime(0,0,0,$date[2],$date[3],$date[1]));
}

// finds out if media is available
function get_media(&$media, $title, $id = false, $relpath = false, $id3 = false) {
	// creates file path for media by title with alternate id appended if present
	$title_str = title_url_format($title);
	$title_path = $relpath."library/music/".substr($title_str,0,1)."/$title_str/$title_str".($id ? "_$id" : '');

	// indicate if Sibelius score exists
	if(stream_resolve_include_path($title_path.'.sib')) { $media['sib'] = date("Y/m/d",filectime($title_path.'.sib'));
	} else { $media['sib'] = false; }
	// indicate if PDF score exists
	if(stream_resolve_include_path($title_path.'.pdf')) { $media['pdf'] = date("Y/m/d",filectime($title_path.'.pdf'));
	} else { $media['pdf'] = false; }
	// indicate if midi sequence exists
	if(stream_resolve_include_path($title_path.'.mid')) { $media['mid'] = date("Y/m/d",filectime($title_path.'.mid'));
	} else { $media['mid'] = false; }
	// indicate if hi-fi audio exists
	if(stream_resolve_include_path($title_path.'_hi.mp3') && $id3) {
		// extracts time from cache
		if(mysql_num_rows( ($r = mysql_query("SELECT getID3 FROM tt3_getID3 WHERE title ='". addslashes($title)."'")) )) {
			$rS=mysql_fetch_assoc($r);
			$media['hi'] = $rS['getID3'];
		} elseif(class_exists(getID3)) {
			static $getID3;
			if(!$getID3) { $getID3 = new getID3; }
			$info = $getID3->analyze($title_path.'_hi.mp3');
			$media['hi'] = $info['playtime_seconds'];
			// if mp3 time not cache table, insert
			if(!mysql_query("SELECT COUNT(*) FROM tt3_getID3 (title,getID3) WHERE title ='". addslashes($title)."'")) {
				if(!mysql_query("INSERT INTO tt3_getID3 VALUES ('".addslashes($title)."','".$media['hi']."')") ) {
					echo "<b>FAILED</b> to insert $title mp3 time";
				}
			}
		}
	} else { $media['hi'] = false; }
	// indicate if lo-fi audio exists
	if(stream_resolve_include_path($title_path.'_lo.mp3')) { $media['lo'] = date("Y/m/d",filectime($title_path.'_lo.mp3'));
	} else { $media['lo'] = false; }
	// indicate if lo-fi Windows Media audio exists
	if(stream_resolve_include_path($title_path.'_lo.wma')) { $media['wma'] = date("Y/m/d",filectime($title_path.'_lo.wma'));
	} else { $media['wma'] = false; }
	
	return ($media['sib'] || $media['mid'] || $media['hi']);
}

// gets score data from scores database; if $id is not given LIKE '_' will match any single id character in database
function get_score_data($title,&$score, $ar_id = array('_')) {
	// requires existing database connection
	foreach($ar_id as $id) {
//		$db_title = '/'.addslashes($title).':'.$id.'>';
//		$sql = "SELECT * FROM tt3_scores WHERE collection LIKE '%$db_title%'";
        $db_title = title_url_format($title);
        $sql = "SELECT * FROM tt3_scores WHERE m_url = '$db_title'";
//echo $sql;		
		if(mysql_num_rows( ($r = mysql_query($sql)) )) {
			while($rS=mysql_fetch_assoc($r)) {
				// matches all collections given
//				preg_match("'<([^<:]*?):([^:/]*?)/(".preg_quote($title,"'")."):(".str_replace('_','.',$id).")>'",$rS['collection'],$cm);
//				$s_id = $cm[4];
                $s_id = $rS['s_id'];
				$s_title = title_unbracket($rS['title']);
				$s_author = $rS['author'];
                preg_match("'<copyright(?:| year=\"(.*?)\")(?:| cc=\"(.*?)\")(?:| tt=\"(.*?)\")>(.*?)</copyright>'", $rS['copyright'], $sm);
				$s_copyyear = $sm[1]; $s_copycc = $sm[2]; $s_copytt = $sm[3]; $s_copyowner = $sm[4];
				$s_meter = $rS['meter'];
				// only uses first key listed
				$s_key = preg_replace("',.+'",'',$rS['keytone']);
				$s_sib = (int)str_replace('-','',$rS['sib']);
				$s_pdf = (int)$rS['pdf'];
				$s_mid = (int)$rS['mid'];
				$s_hi = $rS['hi'];
				$s_lo = (int)$rS['lo'];
				$s_wma = (int)$rS['wma'];
				$score[$s_id] = new Score($s_id,$s_title,$s_author,array('year'=>$s_copyyear,'cc'=>$s_copycc,'tt'=>$s_copytt,'owner'=>$s_copyowner),$s_meter,$s_key,$s_sib,$s_pdf,$s_mid,$s_hi,$s_lo,$s_wma);
			}
		} else {
			$s_media = array();
			if(get_media($s_media, $title)) {
				$s_sib = str_replace('/','',$s_media['sib']); // date
				$s_pdf = str_replace('/','',$s_media['pdf']); // date
				$s_mid = str_replace('/','',$s_media['mid']); // date
				$s_hi = $s_media['hi']; // duration
				$s_lo = str_replace('/','',$s_media['lo']); // date
				$s_wma = str_replace('/','',$s_media['wma']); // date
			}
		}
	}
	ksort($score);
}

function get_bible_book($book,$name=false,$url=false) {
	// 1-based array of books of the Bible
	static $arrBooks = array('',
		'Genesis',		'Exodus',			'Leviticus',		'Numbers',			'Deuteronomy',
		'Joshua',		'Judges',			'Ruth',				'1 Samuel',			'2 Samuel',
		'1 Kings',		'2 Kings',			'1 Chronicles',		'2 Chronicles',		'Ezra',
		'Nehemiah',		'Esther',			'Job',				'Psalms',			'Proverbs',
		'Ecclesiastes',	'Song of Solomon',	'Isaiah',			'Jeremiah',			'Lamentations',
		'Ezekiel',		'Daniel',			'Hosea',			'Joel',				'Amos',
		'Obadiah',		'Jonah',			'Micah',			'Nahum',			'Habakkuk',
		'Zephaniah',	'Haggai',			'Zechariah',		'Malachi',
		
		'Matthew',		'Mark',				'Luke',				'John',				'Acts',
		'Romans',		'1 Corinthians',	'2 Corinthians',	'Galatians',		'Ephesians',
		'Philippians',	'Colossians',		'1 Thessalonians',	'2 Thessalonians',	'1 Timothy',
		'2 Timothy',	'Titus',			'Philemon',			'Hebrews',			'James',
		'1 Peter',		'2 Peter',			'1 John',			'2 John',			'3 John',
		'Jude',			'Revelation');

	if($name) {
		if($url) {
			return str_replace(' ','_',$arrBooks[$book]);
		} else {
			return $arrBooks[$book];
		}
	} else {
		return array_search($book, $arrBooks);
	}
}

function apply_formatting($string,$pre = false) {
	if($pre) { // formatting to be applied before parsing
		// arrays for replacing quotes
		//lsquo - left single quote
		//rsquo - right single quote
		//ldquo - left double quote
		//rdquo - right double quote
		$arr_pre_search_quote = array(
			"'(?<=[> ])\"( ?)<scripture'",			//1-beginning quote of scripture in dialog				|" scripture
			"'</scripture>(\s*?)\"(?!\[^/])(\W)'e",	//2-ending quote of scripture in dialog					|> "</
			"'</scripture>(\s*?)\'(?!\<[^/])(\W)'",	//3-ending quote of scripture in single-quote dialog	|> '</
			"'(>|\s)\"<([^>/]*?)>'",		//4-beginning double quote before beginning tag					| "<tag>
			"'(>|\"?\s)\'<([^>/]*?)>'",		//5-beginning (double and) single quote before beginning tag	|" '<tag>
			"'<([^>/]*?)( /)?>\"\s\''",		//6-beginning double and single quote after beginning tag		|<tag[ /]>" '
			"'<([^>/]*?)( /)?>\"(<|\s)'",	//7-beginning quote after beginning tag							|<tag[ /]>"
			"'>\"</([^>]*?)>'",				//8-tag and ending quote before ending tag						|>"</tag>
			"'\s\"</([^>]*?)>'",			//9-space and ending quote before ending tag					| "</tag>
			"'/([^>]+?)>\"(\W)'",			//0-ending double quote after ending tag						|</tag>"
			"'/([^>]+?)>\'(\s\")?(\W)'e",	//1-ending single (and double) quote after ending tag			|</tag>' "
			"'ldquo;<note>'"				//2-corrects wrong-facing double-quote prior to note link
			);
		$arr_pre_replace_quote = array(
			"&amp;ldquo;$1<scripture",		//1
			"'</scripture>'.('$1' ? '&amp;nbsp;' : '').'&amp;rdquo;$2'",	//2
			"</scripture>$1&amp;rsquo;$2",	//3
			"$1&amp;ldquo;<$2>",			//4
			"$1&amp;lsquo;<$2>",			//5
			"<$1$2>&amp;ldquo;&amp;nbsp;'",	//6
			"<$1$2>&amp;ldquo;$3",			//7
			">&amp;rdquo;</$1>",			//8
			"&amp;nbsp;&amp;rdquo;</$1>",	//9
			"/$1>&amp;rdquo;$2",			//0
			"'/$1>&amp;rsquo;'.('$2' ? '&amp;nbsp;&amp;rdquo;' : '').'$3'",	//1
			"rdquo;<note>"					//2
			);
			
		$string = preg_replace($arr_pre_search_quote, $arr_pre_replace_quote, $string);
		return $string;
	} else { // formatting quotes applied after parsing
		// arrays for replacing typography
		$arr_search_type = array(
			"'\-\-'",		//1 - em dashes
			"'\.{3}\.'",	//2 - terminating horizontal ellipsis
			"'\.{3}([\s])?'",	//3 - horizontal ellipsis
			"'LORD\'S'",	//4 - fullcap LORD'S
			"'(?<!THE |AND )LORD(?!S)'",	//5 - fullcap LORD
			"'(?<=Lord )GOD'"	//6 - fullcap GOD
			);
		$arr_replace_type = array(
			// discretionary space (8203) not supported enough yet
			"&mdash;",/*&#8203;",*/							//1
			"&hellip;.",									//2
			"&hellip;$1",/*&#8203;",*/						//3
			"L<span class=\"KJVsc\">ORD&rsquo;S</span>",	//4
			"L<span class=\"KJVsc\">ORD</span>",			//5
			"G<span class=\"KJVsc\">OD</span>"				//6
			);
		// arrays for replacing quotes
		$arr_search_quote = array(
			"'\'(s|ll|d|t|ve"
				."|(?:t|T)(?:il|is|was|will|were|would)"
				."|(?:n|N)eath|(?:m|M)id|(?:r|R)ound|(?:g|G)ainst|(?:c|C)ause|em"
				."|(?:\d\d))"
					."(\W|$)'",			//1-common contractions
			"'\'(n|N)\''",				//2-double contraction for "and" ['n']
			"'\"\s\'(\S)'",				//3-words preceeded by [" ']
			"'(\S)\'\s\"'",				//4-words followed by [' "]
			"'\"(\w)'",					//5-words preceeded by ["]
			"'(\W)\'(\w)'",				//6-words preceeded by [']
			"'(\s|\()\'(\w)'",			//7-words preceeded by ['][(']
			"'(\s|\()\"(|\S)'",			//8-words preceeded by ["][("]
			"'(\S)\''",					//9-words ending with [']
			"'([^\s>])\"'",				//0-words ending with ["]
			"'([.,!)?])\''",			//1-words ending with [.'][,'][?'][!'][)']
			"'([.,!)?])\"'",			//2-words ending with [."][,"][?"][!"][)"]
			"'\'(\?|\!|\;)'",			//3-words ending with ['?]['!][';]
			"'\"(\?|\!|\;)'",			//4-words ending with ["?]["!][";]
			"'\;\'(\W)'",				//5-HTML-encoded characters followed by [']
			"'\;\"(\W)'",				//6-HTML-encoded characters followed by ["]
			"'^\'(\S)'",				//7-line begins with [']
			"'^\"(\S)'",				//8-line begins with ["]
			"'^\"$'",					//9-line only contains ["]
			"'^\" \('",					//0-line begins with [" (] - odd exception
			"'>\" &#'"					//1-lyrics line begins with [" '] - BAD EXCEPTION
			);
		$arr_replace_quote = array(
			"&rsquo;$1$2",			//1
			"&rsquo;$1&rsquo;",		//2
			"&ldquo;&nbsp;&lsquo;$1",	//3
			"$1&rsquo;&nbsp;&rdquo;",	//4
			"&ldquo;$1",			//5
			"$1&lsquo;$2",			//6
			"$1&lsquo;$2",			//7
			"$1&ldquo;$2",			//8
			"$1&rsquo;",			//9
			"$1&rdquo;",			//0
			"$1&rsquo;",			//1
			"$1&rdquo;",			//2
			"&rsquo;$1",			//3
			"&rdquo;$1",			//4
			";&rsquo;$1",			//5
			";&rdquo;$1",			//6
			"&lsquo;$1",			//7
			"&ldquo;$1",			//8
			"&rdquo;",				//9
			"&rdquo; (",			//0
			">&ldquo; &#"			//1
			);
		// merge arrays
		// mdash - em dash
		$arr_search = array_merge($arr_search_type, $arr_search_quote);
		$arr_replace = array_merge($arr_replace_type,$arr_replace_quote);
		
		$formatted = preg_replace($arr_search, $arr_replace, $string);
		
		// "undoes" quotes replacement in elements
		// is considered "in element" if followed by >
		$arr_undo_search = array(
			"'\&(l|r)squo\;(?=[^<]*?>)'",	//1 - left|right single quotes
			"'\&(l|r)dquo\;(?=[^<]*?>)'"	//2 - left|right double quotes
			);
		$arr_undo_replace = array(
			"\'",	//1
			"\""	//2
			);
			
		return preg_replace($arr_undo_search, $arr_undo_replace, $formatted);
	}
}

function apply_firsts(&$doc_html) {
	$arr_search = array(
		"'((?:<blockquote|div class=\"(?:blurb|note)\"[^>]*)>(?:[^<]*?)<p class=\"(?:.*?))(\")'",	//1 - first quoted paragraph with existing class attribute
		"'((?:<blockquote|div class=\"(?:blurb|note)\"[^>]*)>(?:[^<]*?)<p)(>)'"						//2 - first quoted paragraph without existing class attribute
		);
	$arr_replace = array(
		"$1 first$2",			//1
		"$1 class=\"first\"$2"	//2
		);
		
	$doc_html = preg_replace($arr_search,$arr_replace,$doc_html);
}

function format_orphan($string) {
	// prevents orphan words {w < 4} from hanging in narrow columns
	// if string is long {s > 25}, and the last word is short, "attach" it to the next one so it won't hang by itself
	return preg_replace("'^(.{25,}?)(?: (\S{1,4}))$'","$1&nbsp;$2",$string);
}

function format_filesize($file) {
    $size = (int)(@filesize($file)/1024); // convert to KB
    if ($size < 500) return "$size KB";
    $size = round(($size/1024),1); // convert to MB
    return "$size MB";
}

function format_author($author_xml, $role=false, $year=false, $nohtml=false) {
	global $url_bits;
	//												  1   2     3                   4                     5                6
	preg_match_all("'<(?:author|publisher)(?: year=\"(p)?(c|b)?(\d+)\")?(?: work=\"(.*?)\")?(?: status=\"(attributed)\")?>(.*?)<'",$author_xml,$am);
	for($i = 0; $i < count($am[0]); $i++) {
		// reset values;
		$authortip = false;
		$authorlink = false;
		$authorline = false; $authorline_nohtml = false;
		$yeartip = false;
		$yearline = false;
		
		if($am[4][$i]) {
			$authortip = $am[4][$i];
			$authorline = preg_replace(
				array("'arranged'","'altered'","'translated'","'melody'","'harmon(y|ized)'","'refrain'","'verse[s]?'","'edited'","'revised'"),
				array( "arr.",      "alt.",     "tr.",         "mel.",    "har.",            "ref.",     "v.",         "ed.",     "rev."), $authortip);
		}
		if($am[5][$i]) {
			$authorline = '<i title="'.$authortip.' attributed to '.$am[6][$i].'">'.$authorline.' attr. to</i> ';
			$authorline_nohtml = $authortip.' attributed to '.$am[6][$i];
		} elseif($am[4][$i]) {
			$authorline = '<i title="'.$authortip.($am[6][$i] ? ' by '.$am[6][$i] : '').'">'.$authorline.($am[6][$i] ? ' by ' : '').'</i>';
			$authorline_nohtml = $authortip.($am[6][$i] ? ' by '.$am[6][$i] : '');
		}
		$authorlink = level_url().$url_bits[0].'/_/'.author_url_format($am[6][$i]).'/?sortby='.$role;
		$authorline .= ($role ? '<a href="'.$authorlink.'">'.$am[6][$i].'</a>' : $am[6][$i]);
		$authorline_nohtml = ($authorline_nohtml ? $authorline_nohtml : $am[6][$i]);
		if($am[1][$i] || $am[2][$i]) {
			$yeartip = ($am[1][$i] ? 'published ' : '').($am[2][$i] == 'c' ? 'circa ' : ($am[2][$i] == 'b' ? 'before ' : '')).$am[3][$i];
			$yearline = '<i title="'.$yeartip.'">'.($am[1][$i] ? 'pub.' : '').($am[2][$i] == 'c' ? 'ca.' : ($am[2][$i] == 'b' ? 'bef.' : '')).'</i>';
		}
		if($am[3][$i]) {
			$yearline = ', '.$yearline.$am[3][$i];
		}
		$authoryear .= ($i > 0 ? '<br />' : '').$authorline.$yearline;
		$authoryear_nohtml .= ($i > 0 ? '; ' : '').$authorline_nohtml.($authorline_nohtml && $yeartip ? ', ' : '').$yeartip;
		$lastyear = $am[3][$i];
	}
	if($year) {
		return $lastyear; // if only year is requested, return last year found
	} else {
		if($nohtml) {
			return $authoryear_nohtml;
		} else {
			return $authoryear;
		}
	}
}

function format_copyright($xml_author,$arr_copyright,$title,$media,$link=true) {
	// assembles copyright status based on (by order of precedence): cc license, Public Domain ownership, and year
	if($arr_copyright['cc'] && $arr_copyright['cc'] != 'publicdomain/') {
		$copytext = 'CC License';
	} elseif($arr_copyright['cc'] == 'publicdomain/'
	 || ($arr_copyright['year'] && $arr_copyright['year'] < 1923) ) {
		$arr_copyright['cc'] = 'publicdomain/';
		$copytext = 'Public Domain';
	} elseif(!$arr_copyright['year'] && !$arr_copyright['owner']) {
		// if no copyright year or owner is specified...
		// if no CC license is given, apply public domain license if latest author year is prior to 1923 (or is not given)
		if(!$arr_copyright['cc'] && format_author($xml_author,false,true) < 1923) {
			$arr_copyright['cc'] = 'publicdomain/';
		}
		$copytext = 'Public Domain';
	} elseif($arr_copyright['owner'] == '?' && !$arr_copyright['year']) {
		$copytext = 'copyright status is <span title="please contact us if you have more information">uncertain</span>';
	} else {
		$copytext = 'copyright '. $arr_copyright['year']
		          . ($arr_copyright['owner'] == '?' ? ', status is uncertain' : '');
	}
	$author = preg_replace("'^.+>(.*?)<[\s\S]+$'","$1",$xml_author);

	switch($arr_copyright['cc']) {
		case "publicdomain/":
			$code = 
				($link ? 'copyright status is <!-- Creative Commons Public Domain -->'
					.'<a class="red" rel="license" href="http://creativecommons.org/publicdomain/mark/1.0/">'.$copytext.'</a>'
					.'<!-- /Creative Commons Public Domain -->'
				: '')
				.'
<!--

<rdf:RDF xmlns="http://web.resource.org/cc/"
xmlns:dc="http://purl.org/dc/elements/1.1/"
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
<Work rdf:about="">
<dc:title>'.$title.'</dc:title>
<dc:rights><Agent>
  <dc:title>'.$author.'</dc:title>
</Agent></dc:rights>
<dc:type rdf:resource="http://purl.org/dc/dcmitype/'.$media.'" />
<license rdf:resource="http://web.resource.org/cc/PublicDomain" />
</Work>

<License rdf:about="http://web.resource.org/cc/PublicDomain">
<permits rdf:resource="http://web.resource.org/cc/Reproduction" />
<permits rdf:resource="http://web.resource.org/cc/Distribution" />
<permits rdf:resource="http://web.resource.org/cc/DerivativeWorks" />
</License>

</rdf:RDF>

-->';
			break;
		case "by/1.0/":
		case "by/2.0/":
		case "by/2.5/":
		case "by/3.0/":
		case "by/":
			$code = 
				($link ? 'copyright is <!-- Creative Commons License -->'
					.'<a class="red" rel="license" href="http://creativecommons.org/licenses/by/4.0/">'.$copytext.'</a>'
					.'<!-- /Creative Commons License -->'
				: '')
				.'
<!--

<rdf:RDF xmlns="http://web.resource.org/cc/"
xmlns:dc="http://purl.org/dc/elements/1.1/"
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
<Work rdf:about="">
<dc:title>'.$title.'</dc:title>
<dc:type rdf:resource="http://purl.org/dc/dcmitype/'.$media.'" />
<license rdf:resource="http://creativecommons.org/licenses/by/1.0/" />
</Work>

<License rdf:about="http://creativecommons.org/licenses/by/1.0/">
<permits rdf:resource="http://web.resource.org/cc/Reproduction" />
<permits rdf:resource="http://web.resource.org/cc/Distribution" />
<requires rdf:resource="http://web.resource.org/cc/Notice" />
<requires rdf:resource="http://web.resource.org/cc/Attribution" />
<permits rdf:resource="http://web.resource.org/cc/DerivativeWorks" />
</License>

</rdf:RDF>

-->';
			break;
		default:
			$code = $copytext;
			break;
	}

    $tt_copyright_fmt = "%s <a class='blue' rel='license' href='http://library.timelesstruths.org/help/Management_and_Policies/Copyrights/#%s'>%s</a>";

    switch($arr_copyright['tt']) {
        case "personal":
            $code = sprintf($tt_copyright_fmt, 'copyright is', 'PersonalUse', 'Personal Use');
            break;
        case "uncertain":
            $code = sprintf($tt_copyright_fmt, 'copyright status is', 'Uncertain', 'Uncertain');
            break;
    }

	return $code;
}

function mkdir_path($dir) {
	if(!is_dir($dir)) {
		if(substr_count($dir,"/") > 1) {
			mkdir_path(substr( $dir,0,strrpos( substr($dir,0,strlen($dir)-1) ,"/")+1 ));
			$oldumask = umask(0);
			@mkdir($dir, 0777);
			umask($oldumask);
		} else {
			$oldumask = umask(0);
			@mkdir($dir, 0777);
			umask($oldumask);
		}
	}
}

function delete_path($file) {
    if (isset($_REQUEST['purge'])) echo $file ."<hr />";
	if (file_exists($file)) {
		chmod($file,0777);
		if (is_dir($file)) {
			$handle = opendir($file); 
			while($filename = readdir($handle)) {
				if ($filename != '.' && $filename != '..') {
					delete_path($file.'/'.$filename);
				}
			}
			closedir($handle);
			return rmdir($file);
		} else {
			return unlink($file);
		}
	}
}

?>