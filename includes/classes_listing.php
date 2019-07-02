<?php
// classes_list.php
class Listing {
	var $type;
	var $collection;
	var $title; // for browser title bar purposes, title is usually the same as collection
	
	// optional; used for publications and songbooks
	var $pub_title;
	var $publisher;
	var $copyright = array('year'=>'','cc'=>'','owner'=>'');
	var $summary;
	
	var $sortby;
	var $order;
	
	var $prev_title = false; // previous section title
	var $next_title = false; // next section title
	var $section; // taken from the initialization call or jump menu section argument
	var $sections = array(); // array of objects listing section titles and number of items per title keyed to $sort_match
	
	var $items = array();
	
	function getHeader() {
		$fpath = 'library/'.$this->type.'/'.$this->collection.'.xml';

    if(stream_resolve_include_path($fpath)) {
			$fp = fopen($fpath,'r');
			$header = fread($fp,1000); // should be long enough to grab all of header
			fclose($fp);
			
			preg_match("'<header>\s*<title>(.*?)<'",$header,$hm);
			// if no published title given, use collection title
			$this->pub_title = $hm[1];
			preg_match("'<header[\s\S]*?((?:<publisher.*?</publisher>\s+)+)[\s\S]*?</header>'", $header, $hm);
			$this->publisher = $hm[1];
			preg_match("'<copyright(?: year=\"(.*?)\")?(?: cc=\"(.*?)\")?(?: tt=\"(.*?)\")?>(.*?)<'",$header,$hm);
			$this->copyright['year'] = $hm[1]; $this->copyright['cc'] = $hm[2]; $this->copyright['tt'] = $hm[3]; $this->copyright['owner'] = $hm[4];
			preg_match("'<summary>(.*?)</summary>'",$header,$hm);
			$this->summary = $hm[1];
		}
		if(!$this->pub_title) { $this->pub_title = $this->title; }
	}
}

class BibleListing extends Listing {
	function BibleListing($collection,$section) {
		$this->type = 'bible';
		$this->collection = $collection;
		$this->section = $section;
		if($this->collection == '_') {
			$this->title = "Whole Bible";
		} else {
			$this->title = str_replace('_',' ',$this->collection);
		}
		getItems('bible',$this,$collection);
	}
}

class TextsListing extends Listing {
	var $date;
	
	function TextsListing($collection,$section) {
		$this->type = 'texts';
		$this->collection = $collection;
		$this->section = $section;
		if($this->collection == '_') {
			$this->title = "All Texts";
		} else {
			$this->title = str_replace('_',' ',$this->collection);
		}
		getItems('texts',$this,$collection);
		$this->getHeader();
	}
}

class MusicListing extends Listing {
	function MusicListing($collection,$section) {
		$this->type = 'music';
		$this->collection = $collection;
		$this->section = $section;
		if($this->collection == '_') {
			$this->title = "All Music";
		} else {
			$this->title = str_replace('_',' ',$this->collection);
		}
		getItems('music',$this,$collection);
		$this->getHeader();
	}
}

class ListItem {
	var $collection;
	var $title;
	var $author; // BibleDoc: "King James Version"
	var $copyright = array('year'=>'','cc'=>'','owner'=>''); // BibleDoc: 'owner' = "Public Domain"
	var $subject; // BibleDoc: false
	var $blurb;
	// variable formatted with another variables contents, according to the current sortby
	var $sort_real;
	var $sort_match;
}

class BibleItem extends ListItem {
	var $number;
}

class TextsItem extends ListItem {
	var $date;
}

class MusicItem extends ListItem {
	var $coll_id;
	var $scripture;
	var $score = array();
}

if(!class_exists("Score")) {
	class Score {
		var $id;
		var $title;
		var $author;
		var $copyright = array('year'=>'','cc'=>'','owner'=>'');
		var $meter;
		var $key;
		
		var $sib;
		var $mid;
		var $hi;
		var $lo;
		var $wma;
		
		function Score($id='0',$title,$author,$copyright,$meter,$key='',$sib,$mid,$hi,$lo,$wma) {
			$this->id = $id;
			$this->title = $title;
			$this->author = $author;
			$this->copyright = $copyright;
			$this->meter = $meter;
			$this->key = $key;
			$this->sib = $sib;
			$this->mid = $mid;
			$this->hi = $hi;
			$this->lo = $lo;
			$this->wma = $wma;
		}
	}
}

class ListSection {
	var $title;
	
	function ListSection($title) {
		$this->title = $title;
	}
}

function getItems($type,&$document,$collection='_',$sortby = 'title',$order = 'ascending') {
	if($_GET['sortby']) {
		$sortby = $_GET['sortby'];
	} elseif($type == 'bible') {
		$sortby = 'collection';
	} elseif(substr_count('|Foundation Truth|Treasures of the Kingdom|Dear Princess|','|'.$document->title.'|')) {
		$sortby = 'issue';
		$_GET['order'] = ($_GET['order'] ? $_GET['order'] : 'descending');
	}

	/* Currently no need for Bible index pages */
	if($type == 'bible' && !$document->section) {
		$args = ($sortby != 'collection' ? '?sortby='.$sortby : '').($_GET['order'] == 'descending' ? ($sortby != 'collection' ? '&' : '?').'order='.$_GET['order'] : '');
		header('Location: http://'. NORMALIZED_DOMAIN .'bible/'.$collection.'/_/'.$args);
		exit;
	}

	// when extracting from database table by section, should use the unaltered url values
	$dbsection = $document->section;
	// when sorting by number, the url representation lacks the '> ' prefix of the hundred's group
	if($sortby == 'number' && $document->section && $document->section != '_') { $document->section = '>_'.$document->section; }
    if($sortby == 'published') {
        $_GET['order'] = ($_GET['order'] ? $_GET['order'] : 'descending');
    }
	$order = ($_GET['order'] == 'descending' ? 'descending' : 'ascending');
	// if sorting by scores, use scores database table
	if($type == 'music') {
		$dbtable = (substr_count('|tune|composer|composed|meter|key|',$sortby) ? 'tt3_scores' : 'tt3_music');
	}

	$document->sortby = $sortby;
	$document->order = $order;

	// underscore converted to space for matching either MYSQL LIKE/REGEXP or PHP preg_*
	$db_collection = str_replace('_',' ', $collection);
	//global database variable array passed to database connection from included "f_dbase.php"
	global $db;
	db_connect($db);
	
	// if section of music collection given starts with a number, and is being sorted by default (which is title), try to find music title with that collection id
	if($type=='music' && !$_SERVER['QUERY_STRING'] && is_numeric(substr($document->section,0,1))) {
		$results = mysql_query("SELECT title FROM ". $db["tt3_music"] ." WHERE collection LIKE '%<$db_collection:".$document->section."/%'");
		if(mysql_num_rows($results)) {
			$arr_Item = mysql_fetch_assoc($results);
			header('Location: http://'. NORMALIZED_DOMAIN .'music/'.title_url_format($arr_Item['title']).'/');
			exit;
		}
	} else {
		// if not listing all of a music collection
		if($db["tt3_$type"] == 'tt3_music' /*&& $document->section != '_'*/) {
			if($dbtable == 'tt3_music') {
                $sql = "SELECT ci.c_id,
                        ci.index,
                        CONCAT('/',SUBSTR(LPAD(REPLACE(ci.index,'A',''),3,'0'),1,1),'00/') AS s_number,
                        GROUP_CONCAT(DISTINCT(CONVERT(s.s_id,CHAR))) AS s_ids,
                        m.title, m.author, m.copyright, m.subject, m.scripture, m.verses
                        FROM tt3_music_collections c
                        LEFT JOIN tt3_music_collection_indices ci ON (ci.c_code = c.code)
                        LEFT JOIN tt3_scores s ON (s.title = ci.s_title)
                        LEFT JOIN tt3_music m ON (m.url = ci.m_url)
                        WHERE c.title LIKE '". trim($db_collection) ."%'"
                        . ($dbsection != '_' ? " AND m.s_$sortby LIKE '%/$dbsection/%'" : '') ."
                        GROUP BY m.title"
                        . ($sortby == 'number' ? " HAVING s_number LIKE '%/$dbsection/%'" : '');
			} else {
                $sql = "SELECT ci.c_id,
                        ci.index,
                        CONCAT('/',SUBSTR(LPAD(REPLACE(ci.index,'A',''),3,'0'),1,1),'00/') AS s_number,
                        GROUP_CONCAT(DISTINCT(CONVERT(s.s_id,CHAR))) AS s_ids,
                        s.title, s.author, s.copyright, s.meter, s.keytone, s.sib, s.mid, s.hi, s.lo,
                        m.title AS m_title
                        FROM tt3_music_collections c
                        LEFT JOIN tt3_music_collection_indices ci ON (ci.c_code = c.code)
                        LEFT JOIN tt3_scores s ON (s.title = ci.s_title)
                        LEFT JOIN tt3_music m ON (m.url = ci.m_url)
                        WHERE c.title LIKE '". trim($db_collection) ."%'"
                        . ($dbsection != '_' ? " AND s.s_$sortby LIKE '%/$dbsection/%'" : '') ."
                        GROUP BY s.title"
                        . ($sortby == 'number' ? " HAVING s_number LIKE '%/$dbsection/%'" : '');
			}
            $results = mysql_query($sql);
//echo $sql ."<hr />";    
            			// loads arrays of available sections
			$sql = "SELECT * FROM ".$db['tt3_music_list']." WHERE collection = '".$collection."'";
			$rSorts=mysql_fetch_assoc(mysql_query($sql));
		} else {
		    $sql = "SELECT * FROM tt3_$type WHERE collection REGEXP '".str_replace(' ','.',$db_collection)."+'";
			$results = mysql_query($sql);
		}
	}
	$items = &$document->items; // shorter alias

	$sort_key;   // unique value array is sorted by
	$sort_match; // value other items are compared with to determine if they come in the same section
	$sort_real;  // value displayed at the top of each section
	while($rItem=mysql_fetch_assoc($results)) {
		// gets copyright year, license, and owner, if any
		preg_match("'<copyright(?: year=\"(.*?)\")?(?: cc=\"(.*?)\")?(?: tt=\"(.*?)\")?>(.*?)</copyright>'", $rItem['copyright'], $cm);
		// only includes copyrighted items if sorting by number
		if($sortby != 'number'
		 && $cm[1] >= 1923
		 && !$cm[2]
         && !$cm[3]) {
			continue;
		}
		if($type == 'music') {
			$score = array();
			if($dbtable == 'tt3_music' || !$document->section /*|| $document->section == '_'*/) {
                $ar_sid = explode(',',$rItem['s_ids']);
				get_score_data($rItem['title'],$score,$ar_sid);

				// extracts first two lines from first verse
				preg_match("'^<verse(?:[^>]*)>(.*?)(?:<br />(.*?))?<(?:/verse|br)'",$rItem['verses'],$vm);
			} elseif($dbtable == 'tt3_scores') {
                $ar_sid = explode(',',$rItem['s_ids']);
				
				foreach($ar_sid as $key => $id) {
					$s_title  = title_unbracket($rItem['title']);
					$s_author = $rItem['author'];
					preg_match("'<copyright(?: year=\"(.*?)\")?(?: cc=\"(.*?)\")?(?: tt=\"(.*?)\")?>(.*?)</copyright>'", $rItem['copyright'], $sm);
					$s_copyyear = $sm[1]; $s_copycc = $sm[2]; $s_copytt = $sm[3]; $s_copyowner = $sm[4];
					$s_meter = $rItem['meter'];
					// only uses first key listed
					$s_key = preg_replace("',.+'",'',$rItem['keytone']);
					$s_sib = (int)str_replace('-','',$rItem['sib']);
					$s_mid = (int)$rItem['mid'];
					$s_hi  = $rItem['hi'];
					$s_lo  = (int)$rItem['lo'];
					$s_wma = (int)$rItem['wma'];
					$score[$key] = new Score($id,$s_title,$s_author,array('year'=>$s_copyyear,'cc'=>$s_copycc,'tt'=>$s_copytt,'owner'=>$s_copyowner),$s_meter,$s_key,$s_sib,$s_mid,$s_hi,$s_lo,$s_wma);
				}
			}
		}

		$si = 0; // score index
		do {
			$si++;
            if (isset($rItem['m_title']) && $rItem['m_title'] != $rItem['title']) {
                $rItem['title'] = $rItem['m_title'];
            }
			{ // calculates year data from author data
				if(substr_count('|tune|composer|composed|meter|key|','|'.$sortby.'|')) {
					if($score) {
						$sj = 0;
						foreach($score as $object) {
							$sj++;
							if($sj == $si) {
								$year_source = $object->author;
							}
						}
					} else {
						$year_source = false; // resets value
					}
				} else {
					$year_source = $rItem['author'];
				}
				preg_match_all("'\d{4}'",$year_source,$am); // match all dates in author markup
				$s_year = false; // resets value
				foreach($am[0] as $key => $year) {
					$s_year .= ($key > 0 ? ';' : '').$year;
				}
			}

			switch($sortby) {
				case 'collection': // BIBLE only
					$split = "|"; // fake value; should not occur in this field
					$sort_key   = str_pad($rItem['number'],2,'0',STR_PAD_LEFT); // real sorting value = padded, 2-place-value number
					$sort_match = $rItem['collection']; // matches by Old Testament and New Testament
					$sort_real  = $sort_match; // DISPLAY VALUE
					break;
				case 'issue': // TEXTS (Foundation Truth|Treasurse of the Kingdom|Dear Princess) only
					$split = "|"; // fake value; should not occur in this field
					preg_match("'Number (\d+).*(\d{4})'",$rItem['title'],$tm);
					$sort_key   = $tm[2].str_pad($tm[1],3,'0',STR_PAD_LEFT); // real sorting value = YYYY (year) and padded, 3-place-value issue number
					$sort_match = $tm[2]; // matches by YYYY (year)
					$sort_real  = $sort_match; // DISPLAY VALUE
					break;
				case 'title':
				case 'tune': // MUSIC scores only
					$split = "|"; // fake value; splitting should not occur in this field
					if($sortby == 'tune') {
						if($score) {
							$sj = 0;
							foreach($score as $object) {
								$sj++;
								if($sj == $si) {
									$sort_key = str_replace('_','',title_url_format($object->title));
								}
							}
						} else { // if sorting by score data, and no score data is available, skip
							continue 2;
						}
					} else {
						$sort_key = str_replace('_','',title_url_format($rItem['title']));
						// real sorting value on magazines = magazine title and padded, 3-place-value issue number
						$sort_key = preg_replace("'Number(\d+).*(\d{4})'e","str_pad('$1',3,'0',STR_PAD_LEFT)",$sort_key);
					}
					$sort_match = substr($sort_key,0,1); // matches by the first (sorting) character in title
					$sort_real  = strtoupper($sort_match); // real value displayed is a capital character
					break;
				case 'verse':   $sortby = 'blurb'; // from BIBLE
				case 'excerpt': $sortby = 'blurb'; // from TEXTS
				case 'lyrics':  $sortby = 'blurb'; // from MUSIC
				case 'blurb': // object variable actually sorting by
					$split = "|"; // fake value; splitting should not occur in this field
					$sort_key   = str_replace('_','',title_url_format($type == 'music' ? $vm[1] : $rItem['excerpt']));
					$sort_match = substr($sort_key,0,1); // matches by the first (sorting) character in lyrics
					$sort_real  = strtoupper($sort_match); // real value displayed is a capital character
					break;
				case 'author':
				case 'composer': // MUSIC scores only
					$split = "|"; // does not 'naturally' occur; must be added
					if($sortby == 'composer') {
						if($score) {
							$sj = 0;
							foreach($score as $object) {
								$sj++;
								if($sj == $si) {
									// author is certain, as that is the basis of score inclusion
									$sort_key = preg_replace("'/author>\s+<author'","/author>|<author",$object->author);
								}
							}
						} else { // if sorting by score data, and no score data is available, skip
							continue 2;
						}
					} else {
						$sort_key = preg_replace("'/author>\s+<author'","/author>|<author",$rItem['author']);
					}
					$sort_match = false; // MUST BE DETERMINED IN THE for LOOP
					$sort_real  = false; // MUST BE DETERMINED IN THE for LOOP
					break;
				case 'subject':
					$split = ",";
					$sort_key   = $rItem['subject'];
					$sort_match = false; // MUST BE DETERMINED IN THE for LOOP
					$sort_real  = false; // MUST BE DETERMINED IN THE for LOOP
					break;
				case 'scripture': // MUSIC lyrics only
					$split = "; ";
					// if no scripture is listed, skip
					if(!strlen($rItem['scripture'])) { continue 2; }
					$sort_key   = $rItem['scripture'];
					$sort_match = false; // MUST BE DETERMINED IN THE for LOOP
					$sort_real  = false; // MUST BE DETERMINED IN THE for LOOP
					break;
				case 'meter': // only occurs with scores
					$split = "|"; // fake value; splitting should not occur in this field
					if($score) {
						$sj = 0;
						foreach($score as $object) {
							$sj++;
							if($sj == $si) {
								// if no meter is listed, skip
								if(!strlen($object->meter)) { continue 3; }
								$sort_key = str_pad(preg_replace(
									array("'^(.*)( D)( )?'e",         "'[ R]'","'(\d{1,2})'e",                    "'\.'"),
									array("'$1.$1'.('$3' ? '.' : '')",'',      "str_pad('$1',2,'0',STR_PAD_LEFT)",''),
									$object->meter),16,'0')
									.(substr_count($object->meter,'R') ? 'R' : ' ');
								// removes brackets, which notate irregular refrain
								$sort_key   = preg_replace("'[\[\]]'",'',$sort_key);
								$sort_match = substr($sort_key,0,4); // matches based on the first two lines of meter
								$sort_real  = ltrim(substr($sort_key,0,2),'0').'.'.ltrim(substr($sort_key,2,2),'0');
								// $s_meter used for sub-sorting within each section
							}
						}
					} else { // if sorting by score data, and no score data is available, skip
						continue 2;
					}
					break;
				case 'number': // MUSIC only
					$split = '|'; // fake value; should not occur in this field
                    $sort_key   = str_pad($rItem['index'],3,'0',STR_PAD_LEFT). str_pad(substr($rItem['index'],3,1),'0'); // real sorting value = padded, 3-place-value + possible 'a' number
					$sort_match = substr($sort_key,0,1); // matches by hundreds place
					$sort_real  = '> '.$sort_match.'00';
					break;
				case 'key': // MUSIC scores only
					$split = "|"; // fake value; splitting should not occur in this field
					if($score) {
						$sj = 0;
						foreach($score as $object) {
							$sj++;
							if($sj == $si) {
								// if no key is listed, skip
								if(!strlen($object->key)) { continue 3; }
								// if more than one key given, takes only the first one
								$sort_key = str_pad(preg_replace("',.+'",'',$object->key),3,"0");
								$sort_match = $sort_key; // matches based on padded key
								$sort_real = preg_replace(array("'b'","'s'"),array("&#9837;","&#9839;"), $object->key); // converts flats (b) and sharps (s) notation to HTML characters
							}
						}
					} else { // if no score data is available, skip
						continue 2;
					}
					break;
				case 'year':
				case 'composed': // MUSIC scores only
					$split = ";";
					if(!$s_year) { // if no year data is available, skip
						continue 2;
					}
					$sort_key   = $s_year;
					$sort_match = false; // MUST BE DETERMINED IN THE for LOOP
					$sort_real  = false; // MUST BE DETERMINED IN THE for LOOP
					break;
                case 'scores': // MUSIC lyrics only
/*                    $split = "|"; // fake value; splitting should not occur in this field
                    if($score) {
                        $sj = 0;
                        foreach($score as $object) {
                            $sj++;
                            if($sj == $si) {
                                $sort_key = $object->sib;
                            }
                        }
                    } else {
                        continue 2; // if sorting by score date, and no score is available, skip
                    }
                    $sort_match = substr($sort_key,0,6); // matches by months
                    // matches digit sets
                    preg_match("'(\d{4})(\d{2})(\d{2})'",$sort_key,$date);
                    if(!$date[1]) {
                        continue 2; // if sorting by score date, and no score date is available, skip
                    } else {
                        // formats Month Year date from sets
                        $sort_real = date("F Y",mktime(0,0,0,$date[2],$date[3],$date[1]));
                    }
                    break;*/
//				// THE FOLLOWING OPTION IS 'INVISIBLE'; IT SHOULD NOT BE INCLUDED IN FORM SORT OPTIONS
				case 'published': // MUSIC scores and TEXTS only
					$split = "|"; // fake value; splitting should not occur in this field
					if($type == 'music') {
						if($score) {
							$sj = 0;
							foreach($score as $object) {
								$sj++;
								if($sj == $si) {
									$sort_key = (!strlen($object->sib) ? '0000' : $object->sib);
								}
							}
						} else { // if sorting by score data, and no score data is available, skip
							continue 2;
						}
						$sort_match = substr($sort_key,0,6); // matches by months
						// matches digit sets
						preg_match("'(\d{4})(\d{2})(\d{2})'",$sort_key,$date);
						if(!$date[1]) {
							continue 2; // if sorting by score date, and no score date is available, skip
						} else {
							// formats Month Year date from sets
							$sort_real = date("F Y",mktime(0,0,0,$date[2],$date[3],$date[1]));
						}
					} else {
						$sort_key = $rItem['date'];
						// matches digit sets
						preg_match("'(\d{4})-?(\d{2})-?(\d{2})'",$sort_key,$date);
						$sort_match = $date[1].$date[2];//substr($sort_key,0,6); // matches by months
						if($date[1] == '0000') {
							continue 2; // if sorting by score date, and no score date is available, skip
						} else {
							// formats Month Year date from sets
							$sort_real = date("F Y",mktime(0,0,0,$date[2],$date[3],$date[1]));
						}
					}
					break;
				default:
					continue 2; // if not sorting by legitimate value, skip
					break;
			}
			// sets the value for the title bar; author is reset later
			if($document->section == title_url_format($sort_real)) {
				$document->section_real = $sort_real;
			}
			
			// cuts out unnecessary items; all items with a $sort_real value can be evaluated at this point
			// same criteria used for section selection as in load_listing (classes_html.php)
			if($sort_real) {
				if(cut_item(title_url_format($sort_real),$document->section)) {
				    continue;
                }
			}
	
			// breaks into multiple values per item; only necessary for Author and Subject
			$sorts = explode($split, $sort_key);

			foreach($sorts as $value) {
				$s_value = $value; // unique value array is sorted by
				if($sortby == 'author' || $sortby == 'composer') {
					$value = preg_replace("'^.+>(.*?)<[\s\S]+$'","$1",$value);
					if(!strlen($value)) { continue; } // if no author name given, skip
					// rearranges last name first, but does not format for url
					$sort_real = author_url_format($value,false);
					$sort_match = str_replace('_','',title_url_format($sort_real));
					$s_value = $sort_match; // the actual unique key
					$s_author = $value;
					// sets the value for the title bar, using the "natural" name format
					if($document->section == title_url_format($sort_real)) {
						$document->section_real = $value;
					}
				} else {
					// if not sorting by author, use the first (i.e., major) author
					$s_author = preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$rItem['author']);
				}
				if($sortby=='scripture') {
					static $last_book;
					$s_scripture = trim($value); // the value displayed in the item listing
					preg_match("'^(?:(.*?) )?(\d+)(:(\d+))?'",$s_scripture,$sm); // matches for sorting by books of the Bible
					if($sm[1] == 'Psalm') { $sm[1] = 'Psalms'; }
					if(!$sm[1]) { $sm[1] = $last_book; $s_scripture = $sm[1].' '.$s_scripture; } // if no book is given, use the book given for the last reference
					$last_book = $sm[1];
					$sort_key = str_pad(get_bible_book($sm[1]),2,"0",STR_PAD_LEFT).str_pad($sm[2],3,"0",STR_PAD_LEFT).str_pad($sm[4],3,"0",STR_PAD_LEFT); // sorts by padded book/chapter/verse numbers
					$sort_match = $sm[1];
					$sort_real = $sort_match;
					$s_value = $sort_key; // the actual unique key
				} else {
					// if not sorting by scripture, no scripture is displayed
				}
				if($sortby=='subject') {
					$s_subject = $value; // the value displayed in the item listing
					$sort_real = $value;
					$sort_match = $sort_real;
				} else {
					// if not sorting by subject, use the first (i.e., major) subject
					$s_subject = preg_replace("'^([^\,]+),.+?$'","$1",$rItem['subject']);
				}
				if($sortby == 'year' || $sortby == 'composed') {
					$sort_key = $value; // sorts by year
					$sort_match = substr($sort_key,0,3); // matches by decade
					$sort_real = $sort_match.'0s';
				} else {
					// if not sorting by year, no year is displayed
				}
				// cuts out unnecessary items; all but 'author|composer', 'subject', 'scripture', and 'year|composed' have been evaluated at this point
				// same criteria used for section selection as in load_listing (classes_html.php)
				if(substr_count('author|composer|subject|scripture|year|composed',$sortby)) {
					if(cut_item(title_url_format($sort_real),$document->section)) {
					    continue;
                    }
				}
				if(substr_count('tune|composer|composed|meter|key',$sortby)) {
					$sj = 0;
					foreach($score as $object) {
						$sj++;
						if($sj == $si) {
							$skey_title = $object->title;
                            $skey_title2 = $rItem['m_title'];
						}
					}
				} else {
					$skey_title = $rItem['title'];
				}
				
				// only Title is known to be unique, so it's appended to prevent accidental write-overs and to sort by Title within each section
				$skey = strtolower( $s_value .'0_'. str_replace('_','',title_url_format($skey_title)) .'0_'. str_replace('_','',title_url_format($skey_title2)) );
				
				if($type == 'bible') {
					$items[$skey] = new BibleItem();
				} elseif($type == 'texts') {
					$items[$skey] = new TextsItem();
				} elseif($type == 'music') {
					if(!$items[$skey]) {
						$items[$skey] = new MusicItem();
					}
				}
			
				$items[$skey]->copyright = array('year'=>$cm[1],'cc'=>$cm[2],'owner'=>$cm[3]);
				$items[$skey]->title = $rItem['title'];
				$items[$skey]->url_title = title_url_format($rItem['title']);
				$items[$skey]->author = $s_author;
				$items[$skey]->subject = $s_subject;
				$items[$skey]->year = $s_year; // only present when sorting by year|composed
				if($type == 'bible') {
					$items[$skey]->number = $rItem['number'];
					$items[$skey]->collection = $rItem['collection'];
					$items[$skey]->blurb = $rItem['excerpt'].' &hellip;';
				} elseif($type == 'texts') {
					$items[$skey]->url_title = $rItem['url_title']; // overrides default
					$items[$skey]->collection = $rItem['collection'];
					$items[$skey]->blurb = $rItem['excerpt'];
					$items[$skey]->date = $rItem['date'];
				} elseif($type == 'music') {
					$items[$skey]->blurb = str_replace('&amp;','&',"{$vm[1]} / {$vm[2]}") .' &hellip;';
					if($s_scripture) { $items[$skey]->scripture = $s_scripture; }
					$sj = 0;
					foreach($score as $key => $object) {
						$sj++;
						if($sj == $si) {
							$items[$skey]->score[$key] = $object;
						}
					}
					// include collection music number
                    $items[$skey]->coll_id = $rItem['index'];
				}

				$items[$skey]->sort_match = strtolower($sort_match);
				$items[$skey]->sort_real  = $sort_real;
				// updates sections, creating new object if none
				if(!is_a($document->sections[$sort_match],"ListSection")) {
					$document->sections[$sort_match] = new ListSection($sort_real);
				}
			}
		} while($si < count($score));
	}
	// if the sorting arrays have been extracted from database, fill section values
	if($rSorts) {
		$arrS = explode('/',$rSorts[$document->sortby == 'key' ? 'keytone' : $document->sortby]);
		foreach($arrS as $section) {
			switch($document->sortby) {
				case 'title':
				case 'lyrics':
				case 'subject':
				case 'tune':
					$s_real  = str_replace('_','/',$section);
					$s_match = $s_real;
					$s_key   = $s_match;
					break;
				case 'author':
				case 'composer':
					$s_match = str_replace('_','',$section);
					$s_key   = $s_match;
					// reverse engineer corrections
					$s_real  = preg_replace(array("'Anonymous_Unknown'","'Baring_Gould'","'Barham_Gould'","'Sandell_Berg'","'Steadman_Allen'","'African_American'","'_'","'(?<=^|\W)(\w|Mrs)(?=\W|$)'","'^([\w\-]+[\.]?) '","' ([JS]r)$'","', (\d)'","'OKane'"),
										    array( 'Anonymous/Unknown',  'Baring-Gould',  'Barham-Gould',  'Sandell-Berg',  'Steadman-Allen',  'African-American',  " ",  "$1.",                        "$1, ",              ", $1.",      " $1",     "O'Kane"),$section);
					break;
				case 'year':
				case 'composed':
					$s_real  = $section;
					$s_match = substr($section,0,3);
					$s_key   = $s_match.'\d';
					break;
				case 'scripture':
					$s_match = str_replace('_',' ',$section);
					$s_real  = $s_match;
					$s_key   = str_pad(get_bible_book($s_match),2,'0',STR_PAD_LEFT).'\d';
					break;
				case 'number':
					$s_real  = '> '.$section;
					$s_match = substr($section,0,1);
					$s_key   = $s_match.'\d';
					break;
				case 'meter':
					preg_match("'^([3-9]|[1-2]\d)([3-9]|[1-2]\d)$'",$section,$sm);
					$s_real  = $sm[1].'.'.$sm[2];
					$s_match = str_pad($sm[1],2,'0',STR_PAD_LEFT).str_pad($sm[2],2,'0',STR_PAD_LEFT);
					$s_key   = $s_match.'\d';
					break;
				case 'key':
					$s_real  = preg_replace(array("'b'","'s'"),array("&#9837;","&#9839;"), $section);
					$s_match = str_pad($section,3,'0');
					$s_key   = $s_match;
					break;
                case 'published':
                    $s_real  = str_replace('_',' ',$section);
                    $s_match = date("Ym",strtotime('1 '.$s_real));
                    $s_key   = $s_match.'\d';
                    break;
			}
			$skeyS = strtolower($s_key);
			preg_match("'^".$skeyS."'",$skey,$sm);
			if($dbsection != '_' && !$sm) {
				$items[$skeyS]->sort_real  = $s_real;
				$items[$skeyS]->sort_match = $s_match;
			}
			// sets the value for the title bar; author is not changed later
			if($document->section == title_url_format($s_real) && !substr_count('author|composer',$sortby)) {
				$document->section_real = $s_real;
			}
		}
	}

	// sort the items and sections
	if($order == 'ascending') {
		ksort($items);
		ksort($document->sections);
	} else {
		krsort($items);
		krsort($document->sections);
	}

	// disconnect from database
	db_disconnect($db);
}

// cuts out unnecessary items; to speed up processing, only one item from each unique $match is kept
function cut_item($match,$section) {
	global $type;
	// if all sections are requested, don't cut any
	if($section == '_') { return false; }
	
	// array of matches
	static $arr_match;
	
	// if this match is not in the requested section, cut it; music uses it's own algorithm to generate options
	if($type == 'music' && $match != $section) {
		return true;
	// if there currently exists an item from this match, and this match is not in the requested section, cut it
	} elseif($arr_match[$match] && $match != $section) {
		return true;
	// otherwise add item to list of matches
	} else {
		$arr_match[$match] = true;
		return false;
	}
}
