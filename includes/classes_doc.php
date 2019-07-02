<?php
/* basic class with general metadata, markup variable and function for returning that markup */
class Document {
	var $type;
	var $collection;
	var $title;
	var $url_title;
	var $author; // BibleDoc: "King James Version"
	var $copyright = array('year'=>'','cc'=>'','owner'=>''); // BibleDoc: 'owner' = "Public Domain"
	var $subject; // BibleDoc: false
	
	var $prev_title = false; // previous section title
	var $next_title = false; // next section title
	// MusicDoc does not currently utilize the two following variables
	var $section; // taken from the initialization call or jump menu section argument
	var $this_pNum; // not used by MusicDoc
	var $this_sNum; // not used by MusicDoc
	var $parts = array(); // list of section titles and type
	
	var $xml;
	function output($param="xml") {
		return $this->$param;
	}
}

class BibleDoc extends Document {
	var $excerpt; // beginning of book
	
	function BibleDoc($type,$title,$section) {
		$this->type = $type;
		$this->title = $title;
		$this->url_title = $title;
		$this->section = $section;
		// populates TextDoc object variables from XML file
		extract_xml_text($type,$this);
	}
}

class TextDoc extends Document {
	var $date;
	var $excerpt;
	var $excerpted; // section title of excerpt
	var $excerpt_anchor; // text for dynamic anchor of excerpt
	
	function TextDoc($type,$title,$section) {
		$this->type = $type;
		$this->title = $title;
		$this->url_title = $title;
		$this->section = $section;
		// populates TextDoc object variables from XML file
		extract_xml_text($type,$this);
	}
}

class MusicDoc extends Document {
	var $description; // a short description of the music section for browser title bar
	var $scripture;
	var $score = array();
	
	var $notes;
	var $source = array();
	
	function MusicDoc($title,$section) {
		$this->type = 'music';
		$this->title = $title;
		$this->url_title = $title;
		$this->section = $section;
		// populates MusicDoc object variables from XML file
		extract_xml_music($this);
	}
	function numScores() {
		return count($this->score);
	}
}

class Score {
	var $id;
	var $title;
	var $author;
	var $copyright = array('year'=>'','cc'=>'','tt'=>'','owner'=>'');
	var $meter;
	var $key;
	
	var $sib;
	var $pdf;
	var $mid;
	var $hi;
	var $lo;
	var $wma;
	
	function Score($id='0',$title,$author,$copyright,$meter,$key='',$sib,$pdf,$mid,$hi,$lo,$wma) {
		$this->id = $id;
		$this->title = $title;
		$this->author = $author;
		$this->copyright = $copyright;
		$this->meter = $meter;
		$this->key = $key;
		$this->sib = $sib;
		$this->pdf = $pdf;
		$this->mid = $mid;
		$this->hi = $hi;
		$this->lo = $lo;
		$this->wma = $wma;
	}
}

class Source {
	var $code;
	var $title;
	var $id;
	var $notes;
	
	var $s_url;
	var $s_publisher;
	var $s_copyright = array('year'=>'','cc'=>'','owner'=>'');
	var $s_notes;
	
	function Source($code,$title,$id,$notes,$s_url='',$s_publisher='',$s_copyright='',$s_notes='') {
		$this->code = $code;
		$this->title = $title;
		$this->id = $id;
		$this->notes = $notes;
		
		$this->s_url = $s_url;
		$this->s_publisher = $s_publisher;
		$this->s_copyright = $s_copyright;
		$this->s_notes = $s_notes;
	}
}


class Section {
	var $id = false; // only present in BIBLE
	var $type = false; // has no purpose for <toc>s
	var $title = false;
	var $subtitle = false;
	var $blurb = false;
	var $xml = false; // not necessary for Table of Contents
}

class Part {
	var $type = false; // either "break", "preface", or not present
	var $toc = false; // may or may not be present
	var $sections = array();
}

function extract_xml_music(&$document) {
	$title = $document->title;
    $section = $document->section;
	//global database variable array passed to database connection from "f_dbase.php"
	global $db;
	db_connect($db);
	// removes all illegal characters from database title for comparison
	$sql_replace = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(title,
                	'.',''),',',''),':',''),'!',''),'?',''),'\"',''),'\'',''),'(',''),')',''),'--','_'),'-','_'),' ','_')";
	$results = mysql_query("SELECT * FROM ". $db['tt3_music'] ." WHERE BINARY $sql_replace = '$title'");
	// if there is no such file, try alternatives
	if(!mysql_num_rows($results)) {
        // as a second try, look for possible forwarding
        $results = mysql_query("SELECT url_to FROM ". $db['tt3_aka'] ." WHERE url_from = 'music/$title'");
        if(mysql_num_rows($results)) {
            $r = mysql_fetch_assoc($results);
            mysql_query("UPDATE ". $db['tt3_aka'] ." SET hits = hits + 1, date_lasthit = NOW() WHERE url_from = 'music/$title'");
            header('Location: http://'. NORMALIZED_DOMAIN .$r['url_to'].'/'. ($section ? "$section/" : ''));
            //TODO
            exit;
        }
	    
/*		// as a second try, looks for title starting with request
		$results = mysql_query("SELECT title FROM ". $db['tt3_music'] ." WHERE $sql_replace LIKE '".title_url_format($title)."%' LIMIT 1");
		if(mysql_num_rows($results)) {
			$rMusic = mysql_fetch_assoc($results);
			header('Location: http://'. NORMALIZED_DOMAIN .'music/'.title_url_format($rMusic['title']).'/'.($document->section ? $document->section.'/' : ''));
			exit;
		}
		// for a third try, looks for title with any part of request
		$results = mysql_query("SELECT title FROM ". $db['tt3_music'] ." WHERE $sql_replace LIKE '%$title%' LIMIT 1");
		if(mysql_num_rows($results)) {
			$rMusic = mysql_fetch_assoc($results);
			header('Location: http://'. NORMALIZED_DOMAIN .'music/'.title_url_format($rMusic['title']).'/');
			exit;
		}
		header('Location: http://'. NORMALIZED_DOMAIN .'search/?404='.$title.'&in='.$document->type);
*/
		// finally ships off to music search
		$title = preg_replace("'(^|_)(I)(d|ll|m)(_)'","$1$2'$3$4",$title);
		header('Location: http://'. NORMALIZED_DOMAIN .'search/?query=music&q=title%3A'.str_replace('_',' ',$title));
		exit;
	}
	if($rMusic = mysql_fetch_assoc($results)) {
		// search for previous and next title based on alphabetical order
		// $f_... formatted titles for comparison, regular for real title
		$f_pTitle = false; $pTitle = '';
		$f_nTitle = false; $nTitle = '';
		$results = mysql_query("SELECT title,copyright FROM ". $db['tt3_music']);
		while($rTitles=mysql_fetch_assoc($results)) {
			$dbTitle = strtolower(str_replace('_','',title_url_format($rTitles['title'])));
			$thisTitle = strtolower(str_replace('_','',$title));
			// gets copyright year, license, and owner, if any
            preg_match("'<copyright(?:| year=\"(.*?)\")(?:| cc=\"(.*?)\")(?:| tt=\"(.*?)\")>(.*?)</copyright>'", $rTitles['copyright'], $cm);
            $document->copyright = array('year'=>$cm[1],'cc'=>$cm[2],'tt'=>$cm[3],'owner'=>$cm[4]);
			// excludes navigating to a copyrighted item
			if($cm[4] != 'Public Domain'
			 && $cm[1] >= 1923
			 && $cm[2] == '') {
				continue;
			}
			// selects previous title if the the current db title is "less than" the selected title and greater than any earlier previous title, if any
			if(strcmp($dbTitle,$thisTitle) < 0 && (strcmp($dbTitle,$f_pTitle) > 0 || $f_pTitle == false)) {
				$f_pTitle = $dbTitle;
				$pTitle = $rTitles['title'];
			}
			// selects next title if the the current db title is "greater than" the selected title and less than any earlier next title, if any
			if(strcmp($dbTitle,$thisTitle) > 0 && (strcmp($dbTitle,$f_nTitle) < 0 || $f_nTitle == false)) {
				$f_nTitle = $dbTitle;
				$nTitle = $rTitles['title'];
			}
		}
		
		get_score_data($rMusic['title'],$document->score);
		if( (is_score() == 'score' && !$document->score[0]->sib) /*|| (is_score() == 'pdf' && !$document->score[0]->pdf)*/ ) {
			// if there is no score, redirect to lyrics page
			header('Location: http://'. NORMALIZED_DOMAIN .'music/'.$title.'/');
			exit;
		}

		$document->collection = $rMusic['collection'];
		$document->title = $rMusic['title'];
		$document->author = $rMusic['author'];
		// gets copyright year, license, and owner, if any
		preg_match("'<copyright(?:| year=\"(.*?)\")(?:| cc=\"(.*?)\")(?:| tt=\"(.*?)\")>(.*?)</copyright>'", $rMusic['copyright'], $cm);
		$document->copyright = array('year'=>$cm[1],'cc'=>$cm[2],'tt'=>$cm[3],'owner'=>$cm[4]);
		// gets the first subject listed
		$subjects = preg_split("','",$rMusic['subject']);
		$document->subject    = $subjects[0];
        $document->subjects   = $subjects;
		$document->scripture  = $rMusic['scripture'];
		$document->prev_title = $pTitle;
		$document->next_title = $nTitle;
		// encapsulate xml in <lyrics> tags so multiple verses have the same heirarchy for parsing
		$document->xml = '<lyrics>'.$rMusic['verses'].'</lyrics>';
		$document->notes = $rMusic['notes'];
		
		// gets source information, linking source code label, if any, to source list data
		preg_match_all("'<source(?: code=\"(.*?)\")?(?: id=\"(.*?)\")?>(.*?)</source>'",$rMusic['source'],$sm);
		foreach($sm[0] as $key => $value) {
			$s = array();
			if($sm[1][$key]) {
				$results = mysql_query("SELECT * FROM ". $db['tt3_source_list'] ." WHERE code = '".$sm[1][$key]."'");
				while($rMS=mysql_fetch_assoc($results)) {
					$s['title'] = $rMS['title'];
					$s['url'] = $rMS['url'];
					$s['publisher'] = $rMS['publisher'];
					// gets copyright year, license, and owner, if any
                    preg_match("'<copyright(?:| year=\"(.*?)\")(?:| cc=\"(.*?)\")(?:| tt=\"(.*?)\")>(.*?)</copyright>'", $rMS['copyright'], $cm);
					$s['copyright'] = array('year'=>$cm[1],'cc'=>$cm[2],'tt'=>$cm[3],'owner'=>$cm[4]);					
					$s['notes'] = $rMS['notes'];
				}
			}
			$document->source[$key] = new Source($sm[1][$key],$s['title'],$sm[2][$key],$sm[3][$key],$s['url'],$s['publisher'],$s['copyright'],$s['notes']);
		}
	}
	// closes database connection
	db_disconnect($db);
}

function extract_xml_text($type,&$document) {
	$title = $document->title;
	$section = $document->section;
	// the Bible files are uniquely sorted with number prefix to preserve book order in filesystem
	//global database variable array passed to database connection from "f_dbase.php"
	global $db;
	db_connect($db);
	
	if($type == 'bible') {
		$sql_replace = "REPLACE(title,' ','_')";
		$results = mysql_query("SELECT number FROM ". $db['tt3_bible'] ." WHERE '$title' = $sql_replace");
		
		$rBible = mysql_fetch_assoc($results);
		$title = str_pad($rBible['number'],2,'0',STR_PAD_LEFT).'_'.$title;
		$document->number = $rBible['number'];
	} else {
		// removes all illegal characters from database title for comparison
		$sql_replace = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(title,'.',''),',',''),':',''),'!',''),'?',''),'\"',''),'\'',''),'(',''),')',''),'-','_'),' ','_')";
		$results = mysql_query("SELECT * FROM ". $db['tt3_texts'] ." WHERE '$title' = $sql_replace");
		// if there is no such title, try alternatives
		if(!mysql_num_rows($results)) {
		    // as a second try, look for possible forwarding
		    $results = mysql_query("SELECT url_to FROM ". $db['tt3_aka'] ." WHERE url_from = 'texts/$title'");
            if(mysql_num_rows($results)) {
                $r = mysql_fetch_assoc($results);
                mysql_query("UPDATE ". $db['tt3_aka'] ." SET hits = hits + 1, date_lasthit = NOW() WHERE url_from = 'texts/$title'");
                header('Location: http://'. NORMALIZED_DOMAIN .$r['url_to'].'/'. ($section ? "$section/" : ''));
                //TODO
                exit;
            }
			// as a third try, looks for title starting with request
			$results = mysql_query("SELECT title FROM ". $db['tt3_texts'] ." WHERE $sql_replace LIKE '$title%' LIMIT 1");
			if(mysql_num_rows($results)) {
				$rTexts = mysql_fetch_assoc($results);
				header('Location: http://'. NORMALIZED_DOMAIN .$type.'/'.title_url_format($rTexts['title']).'/');
				exit;
			}
			// for a fourth try, looks for title with any part of request
			$results = mysql_query("SELECT title FROM ". $db['tt3_texts'] ." WHERE $sql_replace LIKE '%$title%' LIMIT 1");
			if(mysql_num_rows($results)) {
				$rTexts = mysql_fetch_assoc($results);
				header('Location: http://'. NORMALIZED_DOMAIN .$type.'/'.title_url_format($rTexts['title']).'/');
				exit;
			}
		}
	}
	// closes database connection
	db_disconnect($db);

	if($type=="help") {
		$root = "www";
	} else {
		$root = "library";
	}
	$xml_path = "$root/$type/".($type != 'bible' ? "$title[0]/" : '')."$title/$title.xml";
	
//print_r($GLOBALSL); exit;	
//exit;
	// if there is no such file, redirect to search page
	if(!($xml_path = stream_resolve_include_path($xml_path))) {
		header('Location: http://'. NORMALIZED_DOMAIN .'search/?404='.($type == 'bible' ? substr($title,3) : $title).'&in='.$document->type);
		exit;
	}

	$xml = file_get_contents($xml_path);

	// extracts header from file
	preg_match("'<header>[\s\S]*?</header>'",$xml, $arr_header);
	if($type != 'bible' ) {
		// extracts data from header
		preg_match("'<date>(.*?)</date>'", $arr_header[0], $hm);
			$h_date = (strlen($hm[1]) ? $hm[1] : false); // BibleDoc does not contain this field
	}
	preg_match("'<collection>(.*?)</collection>'", $arr_header[0], $hm);
		$h_collection = $hm[1];
	preg_match("'<title>(.*?)</title>'", $arr_header[0], $hm);
		$h_title = $hm[1];
	preg_match("'((?:<author.*?/author>\s+)+)'", $arr_header[0], $hm);
		$h_author = $hm[1];
	preg_match("'<subject>(.*?)</subject>'", $arr_header[0], $hm);
		$h_subject = $hm[1]; // BibleDoc does not contain this field
	preg_match("'<excerpt(?: from=\"(.*?)\"(?: anchor=\"(.*?)\"|)|)>(.*?)</excerpt>'", $arr_header[0], $hm);
		$h_excerpted = $hm[1]; $h_excerpt_anchor = $hm[2]; $h_excerpt = $hm[3];
		
	// extracts parts from file
	$xml_parts = extract_xml_nodes($xml,'part');
	
	// numbers of this part and section
	$thisP = false; $thisS = false;
	// title of previous section
	$pTitle = false;
	// flag for looking for title not set if at Table of Contents or Whole Document
	$pFlag = ($section=='' || $section=='_' ? false : true);
	// title of next section
	$nTitle = false;
	// flag for looking for title immediately set if at Table of Contents or Whole Document
	$nFlag = ($section=='' || $section=='_' ? true : false);
	// a "second-chance" section match; if no complete section title match is found, $section2 tries matching the first part of the title
	$section2 = false;
	// array to store parts
	$parts = array();
	$pi = 0; // initializes parts counter
	foreach($xml_parts as $arr_p) {
		$parts[$pi] = new Part();
		preg_match("'part type=\"(.*?)\">'",$arr_p,$pm); // matches section type
		$parts[$pi]->type = $pm[1];
		// extracts TOC header of part, if any
		preg_match("'<part.*?>[\s]*?<toc>([\s\S]*?)</toc>'",$arr_p, $arr_toc);
		if($arr_toc[0]) {
			$parts[$pi]->toc = new Section();
			preg_match("'<title>(.*?)</title>'",$arr_toc[0],$tm);
			$parts[$pi]->toc->title = $tm[1];
			$parts[$pi]->toc->xml = $arr_toc[1];
		}
		// extracts sections from part
		$xml_sections = extract_xml_nodes($arr_p,'section');
		// array to store sections
		$sections = array();
		$si = 0; // initializes sections counter
		foreach($xml_sections as $arr_s) {
			$parts[$pi]->sections[$si] = new Section();
			if($type == 'bible') {
				preg_match("'<section id=\"(.*?)\">'",$arr_s,$sm); // matches section type
				$parts[$pi]->sections[$si]->id = $sm[1];
				// gets the section that the id exactly matches the requested section
				preg_match("'>".$parts[$pi]->sections[$si]->id."<'","'>".$section."<'", $tm);
			} else {
				preg_match("'<section type=\"(.*?)\">'",$arr_s,$sm); // matches section type
				$parts[$pi]->sections[$si]->type = $sm[1];
				preg_match("'<title>(.*?)</title>'",$arr_s,$sm); // matches only title in section
				$parts[$pi]->sections[$si]->title = str_replace('&amp;','&',$sm[1]); // entity conversion is required for compatibility with general parsing
				preg_match("'<heading type=\"subtitle\">(.*?)</heading>'",$arr_s,$sm); // matches only subtitle in section
				$parts[$pi]->sections[$si]->subtitle = $sm[1];
				preg_match("'<toc><blurb>(.*?)</blurb></toc>'",$arr_s,$sm); // matches only blurb in section
				// if no blurb is given, and there is only one item in the table of contents, show an excerpt
				if (count($xml_parts) == 1 && count($xml_sections) == 1 && !$sm[1]) {
					preg_match("'<p>([\s\S]{250,300}) '",$arr_s,$sm);
					$sm[1] = strip_tags($sm[1]) . '&hellip; [<a href="'.title_url_format($parts[$pi]->sections[$si]->title).'/">read&nbsp;more</a>]';
				}
				$parts[$pi]->sections[$si]->blurb = $sm[1];
				// see also f3_parser.php
				if($parts[$pi]->sections[$si]->type == 'music') {
					// a music section may have the same title as another section, and therefore gets a special MUSIC_ prefix in the url
					preg_match("'>".$section."<'","'>MUSIC_".title_url_format(strip_tags($parts[$pi]->sections[$si]->title))."<'", $tm);
				} else {
					// gets the section that the requested section exacly matches the title, when url formatted
					preg_match("'>".$section."<'","'>".title_url_format(strip_tags($parts[$pi]->sections[$si]->title))."<'", $tm);
				}
				// gets the section that the requested section matches the first part of the title, when url formatted and case insensitive
				preg_match("'>".strtolower($section)."'","'>".strtolower(title_url_format(strip_tags($parts[$pi]->sections[$si]->title)))."'", $tm2);
				if($tm2[0]) { $section2 = title_url_format(strip_tags($parts[$pi]->sections[$si]->title)); }
				// gets the section that the requested section matches any part of the title, when url formatted and case insensitive
				preg_match("'".strtolower($section)."'","'".strtolower(title_url_format(strip_tags($parts[$pi]->sections[$si]->title)))."'", $tm3);
				if($tm3[0]) { $section3 = title_url_format(strip_tags($parts[$pi]->sections[$si]->title)); }
			}

			if($tm[0]) {
				$parts[$pi]->sections[$si]->xml = $arr_s;
				// stores this part and section numbers
				$thisP = $pi; $thisS = $si;
				$pFlag = false; // stop looking for previous section title
				$nFlag = true; // start looking for next section title
				// if "previous" flag has been turned off without any previous title being found yet,
				// this indicates the current title is the first section, so the previous is the Table of Contents
				if(!$pFlag && !$pTitle) {
					$pTitle = 'Table of Contents';
				}
			} else {
				// if Whole Document is requested, include this section
				if($section == '_') {
					$document->xml .= $arr_s;
				}
				// save it in case it is the title immediately previous to the current one
				if($pFlag) {
					if($type == 'bible') {
						$pTitle = $parts[$pi]->sections[$si]->id;
					} else {
						// a music section gets a special prefix
						$pTitle = ($parts[$pi]->sections[$si]->type == 'music' ? 'MUSIC_' : '').$parts[$pi]->sections[$si]->title;
					}
				}
				// save it as the title immediately next after the current one; turn flag off
				if($nFlag) {
					if($type == 'bible') {
						$nTitle = $parts[$pi]->sections[$si]->id;
					} else {
						// a music section gets a special prefix
						$nTitle = ($parts[$pi]->sections[$si]->type == 'music' ? 'MUSIC_' : '').$parts[$pi]->sections[$si]->title;
					}
					$nFlag = false;
				}
			}
			$si++; // ups sections counter
		}
		$pi++; // up parts counter
	}
	// if no perfectly matching section was found, and not already at TOC or Whole Document, redirect to TOC or partial match
	if($thisP === false && $thisS === false && !substr_count('//_/','/'.$document->section.'/')) {
	    // if no initial or partial match, look for forwarding
        global $db;
        db_connect($db);
	    $results = mysql_query("SELECT url_to FROM ". $db['tt3_aka'] ." WHERE url_from = 'texts/$title/{$document->section}'");
        db_disconnect($db);
	    if (!($section2 || $section3) && mysql_num_rows($results)) {
            $r = mysql_fetch_assoc($results);
            mysql_query("UPDATE ". $db['tt3_aka'] ." SET hits = hits + 1, date_lasthit = NOW() WHERE url_from = 'texts/$title/{$document->section}'");
            header('Location: http://'. NORMALIZED_DOMAIN .$r['url_to'].'/');
            exit;
        }
		header('Location: http://'. NORMALIZED_DOMAIN .$type.'/'.$title.'/'.(($section2 || $section3) ? ($section2 ? $section2 : $section3).'/'.($_GET['anchor'] ? '?anchor='.$_GET['anchor'].'#'.rawurlencode($_GET['anchor']): '') : ''));
		exit;
	}
	
	if($type != 'bible') {
		$document->date = $h_date;
	}
	$document->collection = $h_collection;
/*	if(substr_count("Foundation Truth|Treasures of the Kingdom|Dear Princess",$document->collection)) {
		global $rewrite_title_url;
		$rewrite_title_url = $document->collection;
	}*/
	$document->title = $h_title;
	$document->author = $h_author;
	// gets copyright year, license, and owner, if any
    preg_match("'<copyright(?:| year=\"(.*?)\")(?:| cc=\"(.*?)\")(?:| tt=\"(.*?)\")>(.*?)</copyright>'", $arr_header[0], $cm);
    $document->copyright = array('year'=>$cm[1],'cc'=>$cm[2],'tt'=>$cm[3],'owner'=>$cm[4]);
	// gets the first subject listed
    $subjects = preg_split("','",$h_subject);
    $document->subject   = $subjects[0];
    $document->subjects  = $subjects;
	$document->excerpt   = $h_excerpt;
	$document->excerpted = $h_excerpted;
	$document->excerpt_anchor = $h_excerpt_anchor;
	$document->parts = $parts;
	$document->this_pNum = $thisP;
	$document->this_sNum = $thisS;
	$document->prev_title = $pTitle;
	$document->next_title = $nTitle;
	// if section requested is Whole Document, it has already been passed to the xml variable
	if($section == '_') {
		// when in Whole Document mode, Table of Contents is considered to follow it, to correspond with jump menu representation
		$document->next_title = 'Table of Contents';
	} else {
		$document->xml = $parts[$thisP]->sections[$thisS]->xml;
	}
//	print_r($document);
	// encapsulate xml in <document> tags so single-section and multi-section have the same heirarchy for parsing
	$document->xml = '<document>'.$document->xml.'</document>';
}

function extract_xml_nodes(&$xml,$node) {
	// extracts parts from file
	preg_match_all("'<$node'", $xml,$nodes_starts,PREG_OFFSET_CAPTURE);
	preg_match_all("'/$node>'",$xml,$nodes_ends,  PREG_OFFSET_CAPTURE);
	$xml_nodes = array();
	for ($i = 0; $i < count($nodes_starts[0]); $i++) {
		$pos_start = $nodes_starts[0][$i][1];
		$pos_end = $nodes_ends[0][$i][1] + strlen($nodes_ends[0][$i][0]);
		$xml_nodes[] = substr($xml,$pos_start,$pos_end - $pos_start);
	}
	return $xml_nodes;
}

?>
