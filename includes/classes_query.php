<?php
class Query {
	var $sections = array();
	var $spans = array();
	
	var $warning;
	var $output;
	
	var $prev_title;
	var $next_title;
	
	// transforms url argument into SQL LIKE-compatible string
	function q_strip($q) {
		$q = stripslashes(stripslashes($q));
		$q = preg_replace("'[;,.?!()*%&_<>=]?\s*$'",'',$q);
		
		if(substr_count($q,"\"")) {
			$q = preg_replace("' '","_%",$q);
			$q1 = substr($q,0,strpos($q,"\""));
			$q2 = preg_replace("'^.*?\"(.*?)\".*?$'","$1",$q);
			$q3 = substr($q,strrpos($q,"\"")+1);
			$q = $q1.preg_replace("'_%'"," ",$q2).$q3;
		} else {
			$q = preg_replace("' '","_%",$q);
		}
		$q = preg_replace("'(^|\W)(for|in|of|the|a)_%'","$1$2 ",$q);
		return $q;
	}
	
	// transforms $q string into an sorted array of strings ready to apply highlights to selected phrase
	function q_to_array($q) {
		$arr_q = @split('_%',$q);
		foreach($arr_q as $qw) {
			if(substr_count('/i/I/b/B/u/U/','/'.$qw.'/')) {
				$qw = $qw.'(?=\W)(?!>)';
			}
			if($qw) {
				$qw = addslashes($qw);
				// any case, not inside of a tag
				$arr_q_reg[str_pad(strlen($qw),2,'0',STR_PAD_LEFT).$qw] = '"((?i)'.$qw.')(?![^<]*>)"';
			}
		}
		krsort($arr_q_reg); // find longest first
		return $arr_q_reg;
	}
}

class PassageSpan {
	var $book;
	var $chapter;
	var $verse;
	
	var $next; // ref, item, span

	var $illegal;
	
	function PassageSpan($book, $chapter, $verse, $next) {
		$this->book = $book;
		$this->chapter = $chapter;
		$this->verse = $verse;
		$this->next = $next;
		$this->illegal = false; // illegal may be turned on when checking the database
	}
}

class PassageSection {
	var $request; // regularized request for display
	var $book;
	var $chapter;
	var $passage; // xml markup

	function PassageSection($request, $book, $chapter, $passage) {
		$this->request = $request;
		$this->book = $book;
		$this->chapter = $chapter;
		$this->passage = $passage;
	}
}

class BibleQuery extends Query {
	var $arrQBooks = array(
		'genesis' => 'Genesis', 'ge' => 'Genesis', 'gen' => 'Genesis',
		'ex' => 'Exodus', 'exodus' => 'Exodus',
		'lev' => 'Leviticus', 'leviticus' => 'Leviticus',
		'num' => 'Numbers', 'numbers' => 'Numbers',
		'dt' => 'Deuteronomy', 'deuteronomy' => 'Deuteronomy', 'deut' => 'Deuteronomy', 'deu' => 'Deuteronomy',
		'jos' => 'Joshua', 'joshua' => 'Joshua', 'josh' => 'Joshua',
		'jdg' => 'Judges', 'judges' => 'Judges', 'judg' => 'Judges',
		'ruth' => 'Ruth', 'ru' => 'Ruth',
		'1 sa' => '1 Samuel', '1 samuel' => '1 Samuel', '1 sam' => '1 Samuel', 'i samuel' => '1 Samuel',
		'2 sa' => '2 Samuel', '2 samuel' => '2 Samuel', '2 sam' => '2 Samuel', 'ii samuel' => '2 Samuel',
		'1 ki' => '1 Kings', '1 kings' => '1 Kings', 'i kings' => '1 Kings',
		'2 ki' => '2 Kings', '2 kings' => '2 Kings', 'ii kings' => '2 Kings',
		'1 chronicles' => '1 Chronicles', '1 chron' => '1 Chronicles', '1 chr' => '1 Chronicles', 'i chronicles' => '1 Chronicles',
		'2 chronicles' => '2 Chronicles', '2 chron' => '2 Chronicles', '2 chr' => '2 Chronicles', 'ii chronicles' => '2 Chronicles',
		'ezr' => 'Ezra', 'ezra' => 'Ezra',
		'ne' => 'Nehemiah', 'nehemiah' => 'Nehemiah', 'neh' => 'Nehemiah',
		'es' => 'Esther', 'esther' => 'Esther',
		'job' => 'Job',
		'psalm' => 'Psalms', 'ps' => 'Psalms', 'psalms' => 'Psalms', 'psa' => 'Psalms',
		'proverbs' => 'Proverbs', 'pr' => 'Proverbs', 'prov' => 'Proverbs', 'pro' => 'Proverbs',
		'ecclesiastes' => 'Ecclesiastes', 'ec' => 'Ecclesiastes', 'ecc' => 'Ecclesiastes', 'eccl' => 'Ecclesiastes',
		'song of solomon' => 'Song of Solomon', 'song' => 'Song of Solomon', 'song of songs' => 'Song of Solomon',
		'isaiah' => 'Isaiah', 'isa' => 'Isaiah', 'is' => 'Isaiah',
		'jeremiah' => 'Jeremiah', 'jer' => 'Jeremiah',
		'lamentations' => 'Lamentations', 'la' => 'Lamentations', 'lam' => 'Lamentations',
		'ezekiel' => 'Ezekiel', 'eze' => 'Ezekiel', 'ezek' => 'Ezekiel', 'ez' => 'Ezekiel',
		'daniel' => 'Daniel', 'dan' => 'Daniel', 'dn' => 'Daniel',
		'hosea' => 'Hosea', 'ho' => 'Hosea', 'hos' => 'Hosea',
		'joel' => 'Joel', 'joe' => 'Joel',
		'amos' => 'Amos', 'am' => 'Amos',
		'obadiah' => 'Obadiah', 'ob' => 'Obadiah', 'obad' => 'Obadiah',
		'jonah' => 'Jonah', 'jon' => 'Jonah',
		'micah' => 'Micah', 'mic' => 'Micah',
		'nahum' => 'Nahum', 'na' => 'Nahum', 'nah' => 'Nahum',
		'habakkuk' => 'Habakkuk', 'hab' => 'Habakkuk',
		'zephaniah' => 'Zephaniah', 'zep' => 'Zephaniah', 'zeph' => 'Zephaniah',
		'haggai' => 'Haggai', 'hag' => 'Haggai',
		'zechariah' => 'Zechariah', 'zec' => 'Zechariah', 'zech' => 'Zechariah',
		'malachi' => 'Malachi', 'mal' => 'Malachi',
		'matthew' => 'Matthew', 'matt' => 'Matthew', 'mt' => 'Matthew', 'mat' => 'Matthew',
		'mark' => 'Mark', 'mk' => 'Mark',
		'luke' => 'Luke', 'lk' => 'Luke',
		'john' => 'John', 'joh' => 'John', 'jn' => 'John',
		'acts' => 'Acts', 'ac' => 'Acts', 'act' => 'Acts',
		'romans' => 'Romans', 'rom' => 'Romans', 'ro' => 'Romans',
		'1 corinthians' => '1 Corinthians', '1 cor' => '1 Corinthians', 'i corinthians' => '1 Corinthians',
		'2 corinthians' => '2 Corinthians', '2 cor' => '2 Corinthians', 'ii corinthians' => '2 Corinthians',
		'galatians' => 'Galatians', 'gal' => 'Galatians', 'ga' => 'Galatians',
		'ephesians' => 'Ephesians', 'eph' => 'Ephesians',
		'philippians' => 'Philippians', 'phil' => 'Philippians', 'php' => 'Philippians',
		'colossians' => 'Colossians', 'col' => 'Colossians',
		'1 thessalonians' => '1 Thessalonians', '1 thess' => '1 Thessalonians', '1 th' => '1 Thessalonians', 'i thessalonians' => '1 Thessalonians',
		'2 thessalonians' => '2 Thessalonians', '2 thess' => '2 Thessalonians', '2 th' => '2 Thessalonians', 'ii thessalonians' => '2 Thessalonians',
		'1 timothy' => '1 Timothy', '1 tim' => '1 Timothy', '1 ti' => '1 Timothy', 'i timothy' => '1 Timothy',
		'2 timothy' => '2 Timothy', '2 tim' => '2 Timothy', '2 ti' => '2 Timothy', 'ii timothy' => '2 Timothy',
		'titus' => 'Titus', 'tit' => 'Titus',
		'philemon' => 'Philemon', 'phm' => 'Philemon', 'phile' => 'Philemon',
		'hebrews' => 'Hebrews', 'heb' => 'Hebrews',
		'james' => 'James', 'jas' => 'James', 'jam' => 'James',
		'1 peter' => '1 Peter', '1 pet' => '1 Peter', '1 pe' => '1 Peter', '1 pt' => '1 Peter', 'i peter' => '1 Peter',
		'2 peter' => '2 Peter', '2 pet' => '2 Peter', '2 pe' => '2 Peter', '2 pt' => '1 Peter', 'ii peter' => '2 Peter',
		'1 john' => '1 John', '1 jn' => '1 John', '1 jo' => '1 John', 'i john' => '1 John',
		'2 john' => '2 John', '2 jn' => '2 John', '2 jo' => '2 John', 'ii john' => '2 John',
		'3 john' => '3 John', '3 jn' => '3 John', '3 jo' => '3 John', 'iii john' => '3 John',
		'jude' => 'Jude', 'jud' => 'Jude',
		'revelation' => 'Revelation', 'rev' => 'Revelation', 're' => 'Revelation', 'revelations' => 'Revelation'
		);
		
	function get_passage($passage) {
		// removes unnecessary closing characters
		$passage = preg_replace("'[;,.?!]?\s*$'",'',$passage);
		
		// convert from "human" notation
		$passage = preg_replace(
			array("'(^| )(first|one)'","'(^| )(second|two)'","'(^| )(third|three)'","'(^| )(fourth|four)'","'(^| )(fifth|five)'","'(^| )(sixth|six)'","'(^| )(seventh|seven)'","'(^| )(eighth|eight)'","'(^| )(ninth|nine)'","'(^| )(tenth|ten)'"),
			array("${1}1","${1}2","${1}3","${1}4","${1}5","${1}6","${1}7","${1}8","${1}9","${1}10"),
			$passage);
		// convert from "human" notation
		$passage = preg_replace(
			array("'First'","'Second'","'Third'"),
			array("1","2","3"),
			$passage);
		$passage = preg_replace("'(^| )([12])(st|nd) '","$1$2 ",$passage);
		
		$passage = preg_replace("'[;,]? ?and'",",",$passage);
		$passage = preg_replace("' ?(through|to)'","-",$passage);
		if(substr_count($passage, ' chapter of ')) {
			$passage = preg_replace("'(?:[tT]he )?(\d+)\D* chapter of ([^,;-]+?)(,? verse(?:s)?.+)?([,;-]|$)'","$2 $1$3$4",$passage);
		}
		if(substr_count($passage, ' verse of ')) {
			$passage = preg_replace("'(?:[tT]he )?(\d+)\D* verse of ([^,;-]+?)([,;-]|$)'","$2$3:$1",$passage);
		}
		$passage = preg_replace("',? verse[s]?[ ]?'",":",$passage);
		$passage = preg_replace("',? chapter[s]?'"," ",$passage);
		if(substr_count($passage, ' verse ')) {
			$passage = preg_replace("'(?:[tT]he )?(\d+)\D+ verse (?:in|of) ([^,;-]+?)(?:,? chapter.+)?(\d+)([,;-]|$)'","$2 $3:$1",$passage);
		}
		$passage = preg_replace("'(\d)[a-z]([^a-z\d]|$)'","$1$2",$passage);

		// an array of requested references (a:b;c:d)
		$script['ref'] = substr_count($passage,';');
		$passages = preg_split( ($script['ref'] ? "';[\s]*'" : "'\|'") ,$passage);
		foreach($passages as $ref) {
			// an array of requested items (a:b,c:d)
			$script['item'] = substr_count($ref,',');
			$refs = preg_split( ($script['item'] ? "',[\s]*'" : "'\|'") ,$ref);
			foreach($refs as $item) {
				// an array of requested spans (a:b-c:d)
				$script['span'] = substr_count($item,'-');
				$items = preg_split( ($script['span'] ? "'-'" : "'\|'") ,$item);
				foreach($items as $span) {
					// matches books and passages
					preg_match("'^(.*?)\.?\s*(?:([1-9]\d*)[:.])?([1-9]\d*)?$'",trim($span),$pm);

					// if book has been given, try to find in book array
					if(strlen($pm[1])) {
						$book = $this->arrQBooks[strtolower($pm[1])];
						// if new book has been given, last span should be reference, as books cannot be spanned or itemized
//						if(count($this->spans)) { $this->spans[count($this->spans)-1]->next = 'ref'; }
					}
					
					// if request is a reference, and no book has previously been given, report error, and skip reference
					if(( $this->spans[count($this->spans)-1]->next == 'ref' || !count($this->spans) ) && !$book) {
						if(strlen($_GET['passage'])) {
//							header('Location: http://'. NORMALIZED_DOMAIN .'search/?query=bible&q='.urlencode(str_replace('+',' ',$_GET['passage'])));
//							exit;
							$this->warning .= "\n".'<h2><span class="red">Cannot find: '.htmlentities(stripslashes(stripslashes($span))).'</span></h2><h3>Expecting Bible reference or search string</h3>';
						} else {
							// if no passage is queried, and short instructions
							$this->warning .= "\n".'<h2><span class="red">Enter a scripture reference in the box above.</span></h2>';
						}
						continue 3;
					// if span does not match passage syntax, report error, and skip span
					} elseif(!$pm) {
						$this->warning .= "\n".'<h2><span class="red">Cannot find: '.$span.'</span></h2>';
						continue;
					} else {
						if($pm[2]) {
							$chapter = $pm[2];
						// otherwise, if no book is given, or previous book is the same as this book, use previous chapter
						} elseif(!$pm[1] || $this->spans[count($this->spans)-1]->book == $pm[1]) {
							$chapter = $chapter;
						// if no chapter, and if verse has not been given, include all the rest of the book
						} elseif($this->spans[count($this->spans)-1]->next == 'span' && !$pm[3] && !$verse) {
							$chapter = false;
						} else {
							$chapter = false;
						}
						// method for determining chapter or verse
						// ... Matt 5:5-6	-> 6 = verse
						// ... Matt 5:5,6	-> 6 = verse
						// ... Matt 5:8-6	-> 6 = chapter
						// ... Matt 5:8,6	-> 6 = chapter
						// ... Matt 5:5;6	-> 6 = chapter
						// ... Matt 5,6		-> 6 = chapter
						// ... Matt 5-6		-> 6 = chapter
						// if chapter has been specified according to unabiguous syntax
						if($pm[2] || ($verse && $this->spans[count($this->spans)-1]->next != 'ref' && $verse < $pm[3] && (!$pm[1] || $this->spans[count($this->spans)-1]->book == $pm[1]))) {
							$verse = $pm[3];
						} elseif(substr_count('|Obadiah|Philemon|2 John|3 John|Jude|','|'.$book.'|')) {
							// if no chapter was specified in above books, assume chapter = 1
							// except for Obadiah, Philemon, 2 John, 3 John, Jude
							$chapter = '1';
							$verse = $pm[3];
						} else {
							// if no chapter was specified according to syntax, assume verse-place (if present) is chapter
							$chapter = ($pm[3] ? $pm[3] : $chapter);
							$verse = '';
						}
						// finds what type (or "level") the next scripture request is
						if($script['span']) {
							$next = 'span';
						} elseif($script['item']) {
							$next = 'item';
						} elseif($script['ref']) {
							$next = 'ref';
						} else {
							$next = false;
						}
						// if new chapter is given, and is less than previous chapter of same book, last span should be reference
						if(count($this->spans) && $chapter && $chapter < $this->spans[count($this->spans)-1]->chapter) { $this->spans[count($this->spans)-1]->next = 'ref'; }
						// initialize
						$this->spans[] = new PassageSpan($book, $chapter, $verse, $next);
					}
					$script['span']--;
				}
			$script['item']--;
			}
		$script['ref']--;
		}
		// retrieve passage texts
		//global database variable array passed to database connection from included "f_dbase.php"
		global $db;
		db_connect($db);

		// shorter alias
		$spans = &$this->spans;
		$chapter = false; // clears chapter value;
//print_r($spans);
		for($i=0; $i < count($spans); $i++) {
			// only new references (refs) and items are processed individually; these are checked for spans, which are processed with them
			// next type == 'ref' OR 'item'
			if(	$spans[$i]->next != 'span') {
				$rBible = mysql_query("SELECT * FROM ". $db['tt3_kjv']
					." WHERE book = ".get_bible_book($spans[$i]->book)
					.($spans[$i]->chapter ? " AND chapter = ".$spans[$i]->chapter : '')
					// match specific verse if given
					.($spans[$i]->verse ? " AND verse = ".$spans[$i]->verse : '') );
				// if none found, return error
				if(!mysql_num_rows($rBible)) {
					$this->warning .= "\n".'<h2><span class="red">Cannot find: '.$spans[$i]->book.' '.$spans[$i]->chapter.($spans[$i]->verse ? ':'.$spans[$i]->verse : '').'</span></h2>'."\n".'</section>';
					// if verse was given, illegalize verse, otherwise blame chapter
					$spans[$i]->illegal = ($spans[$i]->verse ? $spans[$i]->verse : $spans[$i]->chapter);
//					continue;
				}
			// next type == 'span'
			} elseif($spans[$i]->next == 'span') {
				// gets current spans verse id
				$rBible = mysql_query("SELECT id FROM ". $db['tt3_kjv']
					." WHERE book = ".get_bible_book($spans[$i]->book)
					.($spans[$i]->chapter ? " AND chapter = ".$spans[$i]->chapter : '')
					//
					.($spans[$i]->verse ? " AND verse = ".$spans[$i]->verse : '') );
				$arrBible = mysql_fetch_assoc($rBible);
				$id_start = $arrBible['id'];
				// gets nexts spans verse id
				$rBible = mysql_query("SELECT id,chapter,verse FROM ". $db['tt3_kjv']
					." WHERE book = ". get_bible_book($spans[$i+1]->book)
					.($spans[$i+1]->chapter ? " AND chapter = ".$spans[$i+1]->chapter : '')
					.($spans[$i+1]->verse ? " AND verse = ".$spans[$i+1]->verse : '') );
				// if starting point is found, but not ending point, try to find a "natural" place to end
				if($id_start && !mysql_num_rows($rBible)) {
					if($spans[$i+1]->verse) {
						$spans[$i+1]->verse = '';
					} elseif($spans[$i+1]->chapter) {
						$spans[$i+1]->chapter = '';
					}
					$i--;
					$request = '';
					$xml = '';
					continue;
				}
				// make sure to use last id fetched
				while($arrBible = mysql_fetch_assoc($rBible)) {
					$id_end = $arrBible['id'];
					$chapter_end = $arrBible['chapter'];
					$verse_end = $arrBible['verse'];
				}
				// updates request with last verse if not specified
				if(!$spans[$i+1]->verse) {
					$request .= ($spans[$i+1]->chapter != $spans[$i]->chapter ?
						($spans[$i+1]->chapter ? ($spans[$i]->verse ? ':'.$verse_end : '') : $chapter_end)
						: '');
				}
				// gets whole span
				$rBible = mysql_query("SELECT * FROM ". $db['tt3_kjv']
					." WHERE id >= ". $id_start
					." AND id <= ".$id_end);
				if(mysql_errno()) {
					$this->warning .= "\n".'<h2><span class="red">Cannot find: '.$spans[$i+1]->book.' '.$spans[$i+1]->chapter.($spans[$i]->verse ? ':'.$spans[$i]->verse : '').'</span></h2>'."\n".'</section>';
					// since the rest of the loop will be skipped, increment to skip next span
					$i++;
					continue;
				}
			}
			// if this is the next verse in an itemized list, separate with minor break
			if($spans[$i-1]->next == 'item') {
				$xml .= "\n".'<break type="minor" />';
				$request .= ','
					// if not the same as the chapter before, add chapter notation
					.($spans[$i]->chapter != $spans[$i-1]->chapter ? $spans[$i]->chapter.($spans[$i]->verse ? ':' : '') : '')
					.($spans[$i]->verse ? $spans[$i]->verse : '');
				if($spans[$i]->next == 'span') {
					$request .= '-'
						// if next chapter not the same as this chapter, add chapter notation
						.($spans[$i+1]->chapter != $spans[$i]->chapter ? $spans[$i+1]->chapter.':' : '')
						.($spans[$i+1]->verse ? $spans[$i+1]->verse : '');
				}
			// otherwise major break and heading
			} else {
				$xml .= "\n\n".'<section>';
				// used for section link
				$request .= $spans[$i]->book.' '.$spans[$i]->chapter
					.($spans[$i]->verse ? ':'.$spans[$i]->verse : '')
					.($spans[$i]->next == 'span' ?
						'-'
						.($spans[$i+1]->chapter != $spans[$i]->chapter ?
							$spans[$i+1]->chapter
								.($spans[$i+1]->verse ? ':' : '')
							: '')
						.($spans[$i+1]->verse ? $spans[$i+1]->verse : ($spans[$i]->verse ? $verse_end : ''))
						: '');
			}
			
			// skip next span, as it is already fetched
			if($spans[$i]->next == 'span') { $i++; }
			
			while($arrBible = mysql_fetch_assoc($rBible)) {
				if($book != $arrBible['book']) {
					$xml .= "\n".'<title><link type="bible">'.get_bible_book($arrBible['book'],'name').'</link></title>';
					$book = $arrBible['book'];
					$chapter = false; // reset chapter
				}
				if($chapter != $arrBible['chapter']) {
					$xml .= "\r\n\r\n".'<heading type="minor"><link type="bible" link="'.str_replace(' ','_',$spans[$i]->book).' '.$arrBible['chapter'].'">'.($arrBible['book'] == 19 ? 'Psalm ' : 'Chapter ').$arrBible['chapter'].'</link></heading>';
					$chapter = $arrBible['chapter'];
				}
				// converts [ ] to special type
				$scripture = preg_replace(array("'\['","'\]'"),array('<span type="KJVi">','</span>'),$arrBible['scripture']);
				// matches { } psalm captions or epistle notices
				if(substr($scripture,0,1) == '$') {
					$scripture = substr($scripture, 1);
					$xml .= "\r\n".'<pm />';
				}
				preg_match("'^(.*?)(?: |)".preg_quote('{')."(.*?)".preg_quote('}')."(?: |)(.*?)$'",$scripture,$sm);
				if($sm[2]) {
					if($sm[1]) {
						$blurb_post = "\r\n".'<blurb>'.$sm[2].'</blurb>';
						$scripture = $sm[1];
					} elseif($sm[3]) {
						$blurb_pre = "\r\n".'<blurb>'.$sm[2].'</blurb>';
						$scripture = $sm[3];
					}
				} else {
					$blurb_pre = '';
					$blurb_post = '';
				}
				$xml .= $blurb_pre;
				$xml .= "\r\n".'<verse id="'.$arrBible['verse'].'">'.$scripture.'</verse>';
				$xml .= $blurb_post;
			}

			// if at end of section, initialize next section object, and reset markup collection
			if($spans[$i]->next != 'item') {
				$xml .= "\n".'</section>';
				$this->sections[] = new PassageSection($request,$spans[$i]->book,$spans[$i]->chapter,$xml);
				$request = '';
				$xml = '';
				$chapter = '';
			}
		}
		// encapsulate xml in <document> tags so single-section and multi-section have the same heirarchy for parsing
		$this->output .= "\n\n".'<document>';
		foreach($this->sections as $section) {
			$this->output .= $section->passage;
		}
		$this->output .= "\n\n</document>";
		
		// disconnect from database
		db_disconnect($db);
	}

	function get_q($q,$passage=false) {
		
		global $db;
		db_connect($db);

		if($passage) { $book = get_bible_book($passage); }

		$q = $this->q_strip($q);
		$arr_q_reg = $this->q_to_array($q);

		$_GET['start'] = ($_GET['start'] > 0 ? (int)$_GET['start'] : 0);
		$_GET['results'] = ($_GET['results'] ? (int)$_GET['results'] : 10);
		$this->next_title = 'Next '.$_GET['results'];
		
		$rBible = mysql_query("SELECT * FROM ". $db['tt3_kjv'] ." WHERE scripture LIKE '%".addslashes($q)."%'".($book ? " AND book = $book": '')." LIMIT ".$_GET['start'].",".$_GET['results']);
		
		if($_GET['start']) {
			$this->prev_title = 'Previous '.$_GET['results'];
		}
		if(mysql_num_rows($rBible) < $_GET['results']) {
			$this->next_title = '';
		}
		
		// disconnect from database
		db_disconnect($db);
		
		if(!@mysql_num_rows($rBible)) {
			// if nothing found in verses, forward to passage query
			header('Location: http://'. NORMALIZED_DOMAIN .'search/?query=bible&passage='.urlencode(str_replace('+',' ',$_GET['q'])));
			exit;
		}
		
		$xml .= "\n\n".'<section>';
		
		while($arrBible = mysql_fetch_assoc($rBible)) {
			if($book != $arrBible['book']) {
				// title should link to book in Bible department
				$xml .= "\n".'<title><link type="bible">'.get_bible_book($arrBible['book'],'name').'</link></title>';
				$book = $arrBible['book'];
				$chapter = false; // reset chapter
			}
			if($chapter != $arrBible['chapter']) {
				$xml .= "\r\n\r\n".'<heading type="minor"><link type="bible" link="'.str_replace(' ','_',get_bible_book($arrBible['book'],'name')).' '.$arrBible['chapter'].'">'.($arrBible['book'] == 19 ? 'Psalm ' : 'Chapter ').$arrBible['chapter'].'</link></heading>';
				$chapter = $arrBible['chapter'];
			}
			// converts [ ] to italics
			$scripture = preg_replace(array("'\['","'\]'"),array('<span type="KJVi">','</span>'),$arrBible['scripture']);
			// matches { } psalm captions or epistle notices
			if(substr($scripture,0,1) == '$') {
				$scripture = substr($scripture, 1);
				$xml .= "\r\n".'<pm />';
			}
			preg_match("'^(.*?)(?: |)".preg_quote('{')."(.*?)".preg_quote('}')."(?: |)(.*?)$'",$scripture,$sm);
			if($sm[2]) {
				if($sm[1]) {
					$blurb_post = "\r\n".'<blurb>'.$sm[2].'</blurb>';
					$scripture = $sm[1];
				} elseif($sm[3]) {
					$blurb_pre = "\r\n".'<blurb>'.$sm[2].'</blurb>';
					$scripture = $sm[3];
				}
			} else {
				$blurb_pre = '';
				$blurb_post = '';
			}
			$xml .= $blurb_pre;
			$q_scripture = preg_replace($arr_q_reg,"<span type='q'>$1</span>",$scripture);
			$xml .= "\r\n".'<verse id="'.$arrBible['verse'].'">'.$q_scripture.'</verse>';
			$xml .= $blurb_post;
		}
		$xml .= "\n".'</section>';
		
		// encapsulate xml in <document> tags so single-section and multi-section have the same heirarchy for parsing
		$this->output = "\n\n".'<document>'.$xml."\n\n</document>";
	}
}

class MusicQuery extends Query {
	function q_warning($search_q) {
		$this->warning .= "\n".'<h2><span class="red">Cannot find: '.htmlentities(stripslashes(stripslashes($search_q))).'</span></h2><h3>Expecting words from song title or lyrics</h3>';
//echo $this->warning;			
	}
	function get_q($q,$passage=false) {
	
		if(!$q) { return $this->q_warning($q); }

		$search_q = $q;
		
		global $db;
		db_connect($db);

		preg_match("'^title:[ ]?(.*)$'",$q,$qm);
		if($qm[0]) {
			$q_title = true;
			$q = $qm[1];
		}
		// if only one quoted word, don't search within words
		preg_match("'^\"([\w\']+)\"$'",stripslashes(stripslashes($q)),$qm);
		if($qm[0]) {
			$w_match = true;
		}
		// if slash at end, search only end of lines
		preg_match("'/$'",stripslashes(stripslashes($q)),$qm);
		if($qm[0]) {
			$l_match = true;
			$q = str_replace('/','',$q);
		}
		
		$q = $this->q_strip($q);
		if(!$q) { return $this->q_warning($q); }
		$arr_q_reg = $this->q_to_array($q);
		
		if($w_match) {
			$find = "title REGEXP '[[:space:]]".addslashes($q)."[[:space:]]'".(!$q_title ? " OR verses REGEXP '([[:space:]]|[[:punct:]])".addslashes($q)."([[:space:]]|[[:punct:]])'" : '');
		} else {
			$find = "REPLACE(title,'\'','') LIKE '%".addslashes($q)."%'".(!$q_title ? " OR verses LIKE '%".addslashes($q)."%'" : '');
		}
		if($l_match) {
			$find = "verses REGEXP '".addslashes($q)."([[:space:]]|[[:punct:]])*(</verse>|<br />)'";
		}

		$sql_query = "SELECT * FROM ". $db['tt3_music'] ." WHERE ".$find;
		$rMusic = mysql_query($sql_query);

		$_GET['start'] = ($_GET['start'] > 0 ? (int)$_GET['start'] : 0);
		$_GET['results'] = ($_GET['results'] ? (int)$_GET['results'] : 10);
		$this->next_title = 'Next '.$_GET['results'];
		
		if($_GET['start']) {
			$this->prev_title = 'Previous '.$_GET['results'];
		}
		if(mysql_num_rows($rMusic) < $_GET['results']) {
			$this->next_title = '';
		}
		
		// disconnect from database
		db_disconnect($db);
		
		if(!@mysql_num_rows($rMusic)) {
//echo $sql_query;		    
			return $this->q_warning($search_q);
		}
		
		$xml .= "\n\n".'<section>';
		while($arrMusic = mysql_fetch_assoc($rMusic)) {

			$q_preg = preg_replace("'_%'",".+",$q);

			if($w_match) {
				$q_find = "\W".addslashes($q_preg)."\W.*";
			} else {
				$q_find = addslashes($q_preg).".*";
			}
			if($l_match) {
				$q_find = addslashes($q_preg)."\W*(<br />.*)?";
			}
			preg_match_all("'<verse[^>]*?>.*".$q_find."</verse>'i",$arrMusic['verses'],$mm);
			if(!$mm[0]) {
				preg_match("'".addslashes($q_find)."'i",$arrMusic['title'],$mm);
                preg_match("'".addslashes($q_find)."'i",str_replace("'",'',$arrMusic['title']),$mm2);
				if(!$mm[0] && !$mm2[0]) {
					continue;
				}
			}

			$i++; // results counter
			if($i <= ($_GET['start'])) { continue; }
			if($i > ($_GET['start'] + $_GET['results'])) { break; }
				
			// gets copyright year, license, and owner, if any
			preg_match("'<copyright(?: year=\"(.*?)\")?(?: cc=\"(.*?)\")?(?: tt=\"(.*?)\")?>(.*?)</copyright>'", $arrMusic['copyright'], $cm);
			// only includes copyrighted items if sorting by number
			if($cm[1] >= 1923
			 && !$cm[2]
             && !$cm[3]) {
				$copyright = '<span type="red"><b>&amp;copy;</b></span> ';
			} else {
				$copyright = '';
			}

			$title = preg_replace($arr_q_reg,"<span type='q'>$1</span>",$arrMusic['title']);
			// remove underlines within underlines
//			$title = preg_replace('/(<u>[^<]*)<u>([^<]*)<\/u>([^<]*<\/u>)/',"$1$2$3",$title);
			$xml .= "\r\n\r\n" . '<heading type="minor">'.$copyright.'<link type="music" link="'.title_url_format($arrMusic['title']).'">'.$title.'</link></heading>';
			if(!$q_title) {			
				if(is_array($mm[0])) {
					foreach($mm[0] as $verse) {
						$verse = preg_replace($arr_q_reg,"<span type='q'>$1</span>",$verse);
						// remove bold tags within xml entities: &...;
						$verse = preg_replace('/(\&[^;]*)<b>([^<]*)<\/b>([^;]*;)/',"$1$2$3",$verse);
						$xml .= "\r\n".'<p>'.$verse.'</p>';
					}
				}
			}
		}
		// if at end of results, cancel link to next set
		if($i < ($_GET['start'] + $_GET['results']) || mysql_num_rows($rMusic) <= ($_GET['start'] + $_GET['results']) ) {
			$this->next_title = '';			
		}
		$xml .= "\n".'</section>';
		
		// encapsulate xml in <document> tags so single-section and multi-section have the same heirarchy for parsing
		$this->output = "\n\n".'<document>'.$xml."\n\n</document>";
	}
}
?>