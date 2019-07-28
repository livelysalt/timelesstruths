<?php // app/models/Bible.php

class Bible extends Model {

    public  $defaults     = array(
        'start'      => 1,
        'show'       => 25, // number of results shown 
        'page_range' => 5   // number of page links shown before/after current page results
    );
    
    private $passages     = array(); // array of passage objects {book:id,chapter:id,verse:id,next:type}
    
    private $abbrBooks    = array(); // array of book abbreviations 

    private $passageSpans = array(); // array of passage spans (verse IDs ranges)
    private $passageIds   = array(); // array of verse IDs for passage query
    private $strongs      = array(); // array of Strong's objects
    
    private $sqlV = "SELECT v.id,b.name AS book,v.book_id,v.chapter,v.verse,c.verses AS verses_in_chapter,v.xml 
            FROM bible_verses AS v 
            LEFT JOIN bible_books AS b ON (b.id = v.book_id)
            LEFT JOIN bible_chapters AS c ON (c.book_id = v.book_id AND c.chapter = v.chapter)";
    
    public  $errors       = array(); // array of error messages
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Creates an array of book abbreviations
     */
    public function loadAbbrBooks() {

        $res = $this->db->query("SELECT * FROM bible_books WHERE 1");
        while ($r = $res->fetch_object()) {
            
            $abbrs = explode('|',$r->lookup);
            foreach($abbrs as $abbr) {
                if ($abbr) { $this->abbrBooks[ strtolower($abbr) ] = $r->id; }
            }
            
        }
        
        ksort($this->abbrBooks);
        
    } // loadAbbrBooks()
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    public function getBookList() {

        if (($list = $this->get('bookList'))) {
            return $list;
        }
    
        $list = array();
        
        $res = $this->db->query("SELECT * FROM bible_books WHERE 1");
        while ($r = $res->fetch_object()) {
            
            $abbrs = explode('|',$r->lookup);
            
            $list[] = (object)array(
                'id'        => $r->id,
                'fullname'  => $r->name,
                'auralname' => str_replace(array('1 ','2 ','3 '), array('First ','Second ','Third '), $r->name),
                'abbrname'  => $r->toc,
                'abbrhtml'  => $this->makeAbbrHtml($r->name, $r->toc),
                'chapters'  => $r->chapters
            );
            
        }
        
        return $this->set('bookList', $list);
        
    } // getBookList()

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    private function makeAbbrHtml($fullname,$abbrname) {
        
        $len     = strlen($fullname);
        $html    = '';
        $is_same = true;
        
        for($iF = 0, $iA = 0; $iF < $len; $iF++) {
            //echo '<br />$i:'. $fullname[$iF] . '|'. $abbrname[$iA];
            if ($fullname[$iF] == $abbrname[$iA]) {
                if (!$is_same) {
                    $html .= '</span>';
                    $is_same = true;
                }
                $html .= $fullname[$iF];
                $iA++;
            } else {
                if ($is_same) {
                    $html .= '<span>';
                    $is_same = false;
                }
                $html .= $fullname[$iF];
            }
        }
        if (!$is_same) {
            $html .= '</span>';
        }
        
        return $html;
        
    } // makeBookAbbr()
     
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    public function doBook($s = array(/*params*/)) {
        // loads book abbreviations from database to array
        if (!count($this->abbrBooks)) {
            $this->loadAbbrBooks();
        }
        
        $bookName = strtolower($s['book']);
        
        if (!isset($this->abbrBooks[$bookName])) {
            $this->errors[] = "Cannot find book: {$s['book']}";
            $this->set('errors', $this->errors);
            return false;
        }
        $bookId = $this->abbrBooks[$bookName];
        
        $list = $this->getBookList();
        
        $book = $list[$bookId - 1];
        
        $this->set('book',   $book);

        return $book;
        
    } // doBook()
     
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Performs a keyword and/or passage search
     */
    public function doQuery($s = array(/*params*/)) {

        $lookupType = preg_match("'[0-9]'", $s['query']) ? 'passage' : 'word';

        $resCount = 0;
        
        if ($s['passage']) {
            $this->parsePassages($s['passage']);
        }
        
        $query = $this->cleanLookup($s['query']);

        if ($query && $lookupType == 'word') {

            $sqlQuery = $this->db->escape($query);

            if (count($this->passageIds)) {
                $sqlPassage = '|'. implode('|',$this->passageIds) .'|';
            }

            // if passage(s) are specified as well as query, limit query to passages
            $sql = "SELECT id 
                    FROM bible_verses 
                    WHERE lookup LIKE '%$sqlQuery%'" . 
                    (isset($sqlPassage) ? " AND '$sqlPassage' LIKE CONCAT('%|',id,'|%')" : '') .
                    ";";

            $res = $this->db->query($sql);

            $resCount = $res->num_rows;
        }

        // if no verses were found in a word search, try passage search
        if (!$resCount && !$s['passage'] && $s['query']) {
            
            $s['passage'] = $s['query'];
            $this->parsePassages($s['passage']);
            
            $lookupType = 'passage';
        }

        // will contain an array of verses
        $verses = array();

        if ($lookupType == 'word') {
            for($i = $s['start'] - 1; $i < $s['start'] - 1 + $s['show']; $i++) {
                if ($i >= $resCount) { break; }
    
                $res->data_seek($i);
                $v = $res->fetch_object();
                $r = $this->db->queryFirst($this->sqlV . " WHERE v.id = $v->id;");
                $r->html = $this->xmlToHtml($r, $query);
                $verses[] = $r;
                
            }
        
        } elseif ($resCount = count($this->passageIds)) {
            
            foreach ($this->passageIds as $id) {

                $r = $this->db->queryFirst($this->sqlV . " WHERE v.id = $id;");
                $r->html = $this->xmlToHtml($r, $query, array('type'=>'passage'));
                $verses[] = $r;
            
            }

            if (count($this->passageSpans) == 1 && $this->passageSpans[0]->chapter) {
                $sql = "SELECT chapters, name AS book FROM bible_books WHERE id = ". $this->passageSpans[0]->book_id;
                $r = $this->db->queryFirst($sql);
                $this->set('totalChapters', $r->chapters );
                $s['currentChapter'] = $this->passageSpans[0]->chapter;
                $s['book'] = $r->book;
            }
        
        } else {
        
            $s['passage'] = '';
            $lookupType = 'word';
            
        }
        
        $this->set('errors', $this->errors);
        
        $this->processVerses($verses); // verses passed by reference

        $this->set('lookupType',    $lookupType);
        $this->set('verses',        $verses);
        $this->set('totalResults',  $resCount);
        $this->set('errors',        $this->errors);
        $this->set('s',             $s);
        
    } // doQuery()

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Performs a Strong's # search
     */
    public function doStrongs($id, $s = array(/*params*/)) {


        $this->processStrongs($s['query']);

        $lookupType = 'strongs';

        // limit to passages selected
        if ($s['passage']) {
            $this->parsePassages($s['passage']);
            $sqlPassage = '|'. implode('|',$this->passageIds) .'|';
        }

        $sqlMatch = '';
        
        for($si = 0; $si < count($this->strongs); $si++) {
            
            $ss = $this->strongs[$si];

            $sql = "SELECT kjv_word,refs FROM bible_strongs_references WHERE strongs_id = '$ss->id';";
//echo $sql;
            $res = $this->db->query($sql);
            while ($x = $res->fetch_object()) {
                $refs = explode('|',$x->refs);
                $occurs = count($refs) - 2;
                $ss->occurs += $occurs;
                
                $ss->words[] = (object)array(
                    'word'   => $x->kjv_word,
                    'occurs' => $occurs
                );
            }
    
            if ($ss->kjv_word) {
                $sqlMatch .= ($sqlMatch ? " AND" : '') . " strongs_lookup REGEXP ' $ss->lookup_id [^\]]+ ". addslashes($ss->kjv_word) ." '";
            } else {
                $sqlMatch .= ($sqlMatch ? " AND" : '') . " strongs_lookup LIKE '% $ss->lookup_id %'";
            }
            
        } // end loop through multiple numbers

        $sql = "SELECT id 
                FROM bible_verses 
                WHERE $sqlMatch" . 
                (isset($sqlPassage) ? " AND '$sqlPassage' LIKE CONCAT('%|',id,'|%')" : '') .
                ";";
//echo $sql;
        $res = $this->db->query($sql);            

        $resCount = $res->num_rows;

        $verses = array(); // will contain an array of verses
        for($i = $s['start'] - 1; $i < $s['start'] - 1 + $s['show']; $i++) {
            if ($i >= $resCount) { break; }

            $res->data_seek($i);
            $v = $res->fetch_object();
//print_r($v);            
            $r = $this->db->queryFirst($this->sqlV . " WHERE v.id = $v->id;");
            $r->html = $this->xmlToHtml($r, '', array('type'=>'strongs'));
            $verses[] = $r;
            
        }

        $this->set('strongs',       $this->strongs);
        
        $this->processVerses($verses); // verses passed by reference
        
        $this->set('lookupType',    $lookupType);
        $this->set('totalResults',  $resCount);
        $this->set('verses',        $verses);
        $this->set('errors',        $this->errors);
        $this->set('s',             $s);

    } // doStrongs()
        
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Cleans up the query to match against 'lookup' db text
     * this function should follow the same procedures used to prepare the 'lookup' db text
     */
    private function cleanLookup($q) {

        // remove extra whitespace
        $q = preg_replace("/\s+/"," ",$q); 
        // encode "quote" as <quote> for exact match
        $q = preg_replace("/(?:&quot;|\")(.+?)(?:&quot;|\")/","<$1>",$q);
        // remove unmatched punctuation 
        $q = preg_replace("/&.+?;/","",$q);
        // encode spaces for proximity match
        $q = preg_replace("/\s/","#",$q);
        // remove all other non-word and non-apostrophe characters
        $q = preg_replace("/(?![#<>])[^\w\']/","",$q);
        // transform encoded space to SQL pattern 
        $q = str_replace("#","_%",$q);
        // transform spaces inside quotes to exact match
        $q = preg_replace("/_%(?=[^<]*>)/"," ",$q);
        // transform encoded quotes to spaces (i.e., word boundaries)
        $q = str_replace(array('<','>'),' ',$q);

        return $q;
        
    } // cleanLookup()
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Changes xml formatting to standard HTML
     */
    public function xmlToHtml(&$r, $q, $args=array()) {
        if (!isset($args['type'])) { $args['type'] = 'word'; }

        $xml = $r->xml;

        $xml = preg_replace("/\xE2\x80\x93/","-",$xml); // special hyphen
        $xml = preg_replace("/\xC3\xA6/","&aelig;",$xml); // ae ligature
        // verses
        $xml = preg_replace("/<verse(.+?)<\/verse>/","<span class='verse-text'$1</span>",$xml);
        // strongs
        $xml = preg_replace("/<w([^<]+?)\/>/","<span class='w'$1></span>",$xml);
        $xml = preg_replace("/<w(.+?)<\/w>/","<span class='w'$1</span>",$xml);
        $xml = preg_replace("/(strong:H)0(\d+)/","$1$2",$xml);
        $xml = preg_replace("/(<span class='w'[^>]*lemma=\"(strong:[^\"]+ ?)+\")/","$1 title='$2'",$xml);
        // Psalm 119 subtitles with Hebrew letters
        $xml = preg_replace("/<foreign n=\"(.+?)\">(.+?)\.<\/foreign>/","$2 <span class='foreign'>($1)</span>. ",$xml);
        // titles of psalms
        $xml = preg_replace("/<title(.+?)<\/title>/","<span class='psalm-title'$1</span>",$xml);
        // colophon of epistles
        $xml = preg_replace("/<div type=\"colophon\"(.+?)<\/div>/","<span class='epistle-colophon'$1</span>",$xml);
        // inscriptions
        $xml = preg_replace("/<inscription(.+?)<\/inscription>/","<span class='inscription'$1</span>",$xml);
        // quotes of Jesus
        $xml = preg_replace("/<q(.+?)<\/q>/","<span class='quote'$1</span>",$xml);
        // words added in translation
        $xml = preg_replace("/<transChange type=\"added\">(.+?)<\/transChange>/","<span class='transChange added'>$1</span>",$xml);
        // divine Name
        $xml = preg_replace("/<seg><divineName>(.+?)<\/divineName><\/seg>/","<span class='divineName'>$1</span>",$xml);
        // paragraph marks
        preg_match("/^(.*?)<milestone type=\"(.+?)\"(?: subType=\"(x-added)\")?[^>]+>(.*?)$/",$xml,$m);
        if ($m) {
            $r->is_new_p = "{$m[2]} {$m[3]}";
            $xml = $m[1] . "<span class='p-mark $r->para'>&para;&nbsp;</span>" . $m[4];
        }
        // convert html entities to utf characters to avoid highlight matching problems
        $xml = html_entity_decode($xml,ENT_COMPAT,'UTF-8');
        // study notes
        $xml = preg_replace("/<note type=\"study\">(.+?)<\/note>/e","'<span class=\'study\' title=\''.htmlspecialchars('$1', ENT_QUOTES).'\'></span>'",$xml);

        switch ($args['type']) {
        case 'word':
            // highlights query in word lookup
            $xml = preg_replace($this->qToArray($q),"<mark>$1</mark>",$xml);
            break;
        case 'strongs':
            // highlights word(s) from strongs lemma
            foreach($this->strongs as $ss) {
                if ($ss->kjv_word) {
                    $xml = preg_replace("/(strong:{$ss->id}\D.*?\W)($ss->kjv_word)(\W)/i","$1<mark>$2</mark>$3",$xml);
                } else {
                    $xml = preg_replace("/(<span class='w'[^>]+strong:{$ss->id}[\D][^>]+>)("
                                       ."(?:[^<]*?)"
                                       ."(?:<span(?:.*?)<\/span>)?" // bridges possible nested <span>
                                       ."(?:[^<]*?)"
                                       .")(<\/span>)/i","$1<mark>$2</mark>$3",$xml);
                }
            }
            break;
        }
        
        return $xml;
        
    } // xmlToHtml()

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Transforms $q string into an sorted array of strings ready to apply highlights to selected phrase
     */
    private function qToArray($q) {
        if (!$q) { return array(); }
        // change quoted sections to match exact word
        $q = preg_replace("/ (.+) /","(?<=\W)$1(?=\W)",$q);
        $q = str_replace(' ','_%',$q); // this compromizes exact match highlighting by matching partials within verse as well        
        $arr_q = explode('_%',$q);
        foreach($arr_q as $qw) {
            if(substr_count('/i/I/b/B/u/U/','/'.$qw.'/')) {
                $qw = $qw.'(?=\W)(?!>)';
            }
            if($qw) {
                $qw = addslashes($qw);
                $qw = str_replace('\\\\W','\W',$qw);
                $key = str_pad(strlen($qw),2,'0',STR_PAD_LEFT).$qw; // indexed by length
                // any case, not inside of a tag
                $arr_q_reg[$key] = '"((?i)'.$qw.')(?![^<]*>)"';
            }
        }
        krsort($arr_q_reg); // find longest first
        return $arr_q_reg;
    } // qToArray()
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Creates a set of passage objects from "human" notation
     */
    public function parsePassages($p) {

        // loads book abbreviations from database to array
        if (!count($this->abbrBooks)) {
            $this->loadAbbrBooks();
        }

        // removes unnecessary closing characters
        $p = preg_replace("'[;,.?!]?\s*$'",'',$p);
        
        // convert from "human" notation
        $p = preg_replace(
            array("'(^| )(first|one)'","'(^| )(second|two)'","'(^| )(third|three)'","'(^| )(fourth|four)'","'(^| )(fifth|five)'","'(^| )(sixth|six)'","'(^| )(seventh|seven)'","'(^| )(eighth|eight)'","'(^| )(ninth|nine)'","'(^| )(tenth|ten)'"),
            array("\${1}1","\${1}2","\${1}3","\${1}4","\${1}5","\${1}6","\${1}7","\${1}8","\${1}9","\${1}10"),
            $p);
        // convert from "human" notation
        $p = preg_replace(
            array("'First'","'Second'","'Third'"),
            array("1","2","3"),
            $p);
        $p = preg_replace("'(^| )([12])(st|nd) '","$1$2 ",$p);
        // New Testament shortcut
        $p = preg_replace("'(^|(?<=\W))nt'i","Matthew-Revelation",$p);
        
        $p = preg_replace("'[;,]? ?and'",",",$p);
        $p = preg_replace("' ?(through|to)'","-",$p);
        if(substr_count($p, ' chapter of ')) {
            $p = preg_replace("'(?:[tT]he )?(\d+)\D* chapter of ([^,;-]+?)(,? verse(?:s)?.+)?([,;-]|$)'","$2 $1$3$4",$p);
        }
        if(substr_count($p, ' verse of ')) {
            $p = preg_replace("'(?:[tT]he )?(\d+)\D* verse of ([^,;-]+?)([,;-]|$)'","$2$3:$1",$p);
        }
        $p = preg_replace("',? verse[s]?[ ]?'",":",$p);
        $p = preg_replace("',? chapter[s]?'"," ",$p);
        if(substr_count($p, ' verse ')) {
            $p = preg_replace("'(?:[tT]he )?(\d+)\D+ verse (?:in|of) ([^,;-]+?)(?:,? chapter.+)?(\d+)([,;-]|$)'","$2 $3:$1",$p);
        }
        $p = preg_replace("'(\d)[a-z]([^a-z\d]|$)'","$1$2",$p);

        $p = preg_replace("'(?<=[:\-]) (?=\d)'","",$p);

        // an array of requested references (a:b;c:d)
        $scripture = array();
        $scripture['ref'] = substr_count($p,';');
        $pp = preg_split( ($scripture['ref'] ? "';[\s]*'" : "'\|'") ,$p);
        
        foreach($pp as $ref) {
            // an array of requested items (a:b,c:d)
            $scripture['item'] = substr_count($ref,',');
            $refs = preg_split( ($scripture['item'] ? "',[\s]*'" : "'\|'") ,$ref);
            foreach($refs as $item) {
                // an array of requested spans (a:b-c:d)
                $scripture['span'] = substr_count($item,'-');
                $items = preg_split( ($scripture['span'] ? "'-'" : "'\|'") ,$item);
                $book = $verse = false;
                foreach($items as $span) {
                    if (!$span) continue;
                    
                    // total passage spans
                    $nPP = count($this->passages);
                    // matches books and passages
                    preg_match("'^(.*?)\.?\s*(?:([1-9]\d*)[:.])?([1-9]\d*)?$'",trim($span),$pm);
                    // if book has been given, try to find in book array
                    $mBook = $pm[1];
                    if(strlen($mBook)) {
                        if (!isset($this->abbrBooks[ strtolower($mBook) ])) {
                            $this->errors[] = "Cannot find: {$mBook}";
                            continue; // skip
                        }
                        $book = $this->abbrBooks[ strtolower($mBook) ];
                    } else {
                        $book = $nPP ? $this->passages[$nPP-1]->book : false;                      
                    }
                    
                    // if request is a reference, and no book has previously been given, report error, and skip reference
                    if((!$nPP || $this->passages[$nPP-1]->next == 'ref') && !$book) {
                        if(strlen($s['passage'])) {
                            $this->errors[] = 'Cannot find: '.htmlentities(stripslashes(stripslashes($span))).'. Expecting Bible reference or search string.';
                        } else {
                            // if no passage is queried, and short instructions
                            $this->errors[] = 'Enter a scripture reference in the search box above.';
                        }
                        continue 3;
                    // if span does not match passage syntax, report error, and skip span
                    } elseif(!$pm) {
                        $this->errors[] = "Cannot find: $span";
                        continue;
                    } else {
                        if(isset($pm[2]) && $pm[2]) {
                            $chapter = $pm[2];
                        // otherwise, if no book is given, or previous book is the same as this book, use previous chapter
                        } elseif(!$pm[1] || ($nPP && $this->passages[$nPP-1]->book == $pm[1])) {
                            $chapter = $chapter;
                        // if no chapter, and if verse has not been given, include all the rest of the book
                        } elseif($nPP && $this->passages[$nPP-1]->next == 'span' && (!isset($pm[3]) || !$pm[3]) && !$verse) {
                            $chapter = false;
                        } else {
                            $chapter = false;
                        }
                        // method for determining chapter or verse
                        // in ambiguous cases this algorithm will make fuzzy decisions rather than return error
                        // ... Matt 5:5-6   -> 6 = verse
                        // ... Matt 5:5,6   -> 6 = verse
                        // ... Matt 5:8-6   -> 6 = chapter
                        // ... Matt 5:8,6   -> 6 = chapter
                        // ... Matt 5:5;6   -> 6 = chapter
                        // ... Matt 5,6     -> 6 = chapter
                        // ... Matt 5-6     -> 6 = chapter
                        // if chapter has been specified according to unabiguous syntax
                        if( (isset($pm[2]) && $pm[2]) 
                            || ($nPP 
                                && $this->passages[$nPP-1]->verse && $this->passages[$nPP-1]->next != 'ref' && $this->passages[$nPP-1]->verse < $pm[3] 
                                && (!$pm[1] || $this->passages[$nPP-1]->book == $pm[1])
                            )
                        ) {
                            $verse = (isset($pm[3]) ? $pm[3] : '');
                        } elseif(substr_count('|31|57|63|64|65|','|'.$book.'|')) {
                            // if no chapter was specified in Obadiah, Philemon, 2 John, 3 John, Jude, assume chapter = 1
                            // if 1 was specified, assume entire chapter
                            $chapter = '1';
                            $verse = $pm[3] == 1 ? '' : $pm[3];
                        } else {
                            // if no chapter was specified according to syntax, assume verse-place (if present) is chapter
                            $chapter = (isset($pm[3]) && $pm[3] ? $pm[3] : $chapter);
                            $verse = '';
                        }
                        // if verse immediately follows previous, it is a span
                        if($nPP && $book == $this->passages[$nPP-1]->book && $chapter == $this->passages[$nPP-1]->chapter && $verse == $this->passages[$nPP-1]->verse + 1) {
                            $this->passages[$nPP-1]->next = 'span';
                        }                        
                        // finds what type (or "level") the next scripture request is
                        if($scripture['span']) {
                            $next = 'span';
                        } elseif($scripture['item']) {
                            $next = 'item';
                        } elseif($scripture['ref']) {
                            $next = 'ref';
                        } else {
                            $next = false;
                        }
                        // if new chapter is given, and is less than previous chapter of same book, last span should be reference
                        if($nPP && $chapter && $chapter < $this->passages[$nPP-1]->chapter && $book <= $this->passages[$nPP-1]->book) {
                            $this->passages[$nPP-1]->next = 'ref'; 
                        }
                        // initialize
                        $this->passages[] = (object)array(
                            'book'      => $book,
                            'chapter'   => $chapter,
                            'verse'     => $verse,
                            'next'      => $next
                        );
                    }
                    $scripture['span']--;
                }
                $scripture['item']--;
            }
            $scripture['ref']--;
        }

        $this->makeSpans();

    } // parsePassages()
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Gets an array of verse IDs from parsed passage spans
     */
    private function makeSpans() {

        for ($i = 0; $i < count($this->passages); $i++) {
            
            $span = $this->processPassage($i);
            if ($span) { $this->passageSpans[] = $span; }
            
        }

        foreach ($this->passageSpans as $span) {
            if (isset($span->min)) {
                for ($id = $span->min; $id <= $span->max; $id++) {
                    $this->passageIds[] = $id;
                }
            }
        }        

    } // makeSpans()
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Takes the index of a passage and returns a span object
     */
    private function processPassage(&$i, $min = null, $type = '') {

        // selects the passage object requested
        $p = $this->passages[$i];

        // if at the beggining of span range, finds the minimum verse ID or returns empty object if invalid
        if (!$min) {
            $sql = "SELECT id 
                    FROM bible_verses 
                    WHERE book_id = $p->book 
                      AND chapter = ". ($p->chapter ? $p->chapter : 1) ." 
                      AND (verse = ". ($p->verse ? $p->verse : "0 OR verse = 1") .") 
                    LIMIT 1;";
            $r = $this->db->queryFirst($sql);
            if (!$r) {
                $sql = "SELECT name FROM bible_books WHERE id = $p->book;";
                $book = $this->db->queryFirst($sql);
                $this->errors[] = "Cannot find: $book->name $p->chapter:$p->verse";
                return;
            }
            $min = $r->id;
            if (!$min) {
                return null;    
            }
        }
        
        // if at the end of the span range, or end of passages, finds maximum valid verse ID
        if ($p->next != 'span' || $i == (count($this->passages) - 1) ) {

            $sql = "SELECT v.id,b.chapters 
                    FROM bible_verses AS v 
                    LEFT JOIN bible_books AS b ON (b.id = v.book_id) 
                    WHERE v.book_id = $p->book 
                      AND (v.chapter > 0 AND v.chapter <= ". ($p->chapter ? $p->chapter : 999) .") 
                      AND (v.verse > 0 AND v.verse <= ". ($p->verse ? $p->verse : 999) .") 
                    ORDER BY v.id DESC LIMIT 1;";
            $r = $this->db->queryFirst($sql);
            // if the last complete chapter in the book, fetch colophon if any
            if (!$p->verse && $p->chapter == $r->chapters) {
                $sql = "SELECT id FROM bible_verses WHERE book_id = $p->book ORDER BY id DESC LIMIT 1;";
                $r = $this->db->queryFirst($sql);
            }
            $max = $r->id;            
            
        } else {
            
            return $this->processPassage(++$i, $min, 'span');
            
        }

        // returns a complete span
        return (object) array(
            'book_id' => $p->book,
            'chapter' => (!$p->verse && $type != 'span' ? $p->chapter : 0),
            'min' => $min,
            'max' => $max
        );
        
    } // processPassage()

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * parses Strong's notation
     */
    private function processStrongs($q) {
        
        // this regex is nearly identical to what is used to identify a strongs search in BibleController.php
        preg_match_all("/(?:strong[s]?:)?([Hh]|[Gg])0?(\d+)(?: \"([^\"]+?)\")?/", $q, $m);
        
        for($i = 0; $i < count($m[0]); $i++) {

            $id        = strtoupper( ($m[1][$i] . $m[2][$i]) );
            $lookup_id = preg_replace("/[Hh]([1-9])/","H0"."$1",$id);
            $kjv_word  = $m[3][$i];

            // lookup strongs number for definition
            $sql = "SELECT * FROM bible_strongs_definitions WHERE id = '$id';";
            $res = $this->db->query($sql);
        
            // if number is not found, show error message
            if (! ($r = $res->fetch_object()) ) {
                $this->errors[] = "Cannot find Strongs number: $id";
                continue;
            }

            $this->strongs[] = (object)array(
                'id'              => $id,
                'lookup_id'       => $lookup_id,
                'kjv_word'        => $kjv_word,
                'title'           => $r->title,
                'transliteration' => $r->transliteration,
                'pronunciation'   => $r->pronunciation,
                'description'     => $r->description,
                'words'           => array(), // will contain array of words used in the KJV
                'occurs'          => 0 // number of occurances
            );
        }
        
    } // processStrongs()
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    private function processVerses(&$verses=array()) {
        
        for ($i = 0; $i < count($verses); $i++) {
            $verse = $verses[$i];
            $prev  = ($i > 0 ? $verses[$i-1] : null);
            $next  = ($i < count($verses) - 1 ? $verses[$i+1] : null);

            // OPENING...
            // if a different book or chapter than previous, indicate first in chapter
            if ($verse->book != $prev->book || ($verse->chapter != $prev->chapter && $verse->chapter != 0)) {
                if (!$prev) {
                    $verse->is_first_result = true;
                }
                $verse->is_first_in_chapter = true;
            }
            // needs to account for Psalm titles which use verse number 0
            if ($verse->chapter && $verse->verse <= 1 && $verse->is_first_in_chapter) {
                $verse->is_start_of_chapter = true;
                // checks to see if the rest of the chapter is selected
                $verse_fwd = $verses[$i + ($verse->verses_in_chapter - $verse->verse)];
                if ($verse_fwd->book_id == $verse->book_id && $verse_fwd->chapter == $verse->chapter) {
                    $verse->is_complete_chapter = true;
                }
            }
            // checks to see if this chapter is the sequel to the previous chapter of results
            if ($verse->is_first_in_chapter && $prev->book == $verse->book && $prev->chapter == $verse->chapter - 1) {
                $verse->is_sequel_chapter = true;
                if ($prev->id == $verse->id - 1) {
                    $verse->is_sequel_chapter_verse = true;
                }
            }
            // checks to see if this chapter is the prequel to the next chapter of results
            if ($verse->is_first_in_chapter) {
                for ($i2 = $i; $i2 < count($verses); $i2++) {
                    if ($verses[$i2]->chapter != $verse->chapter) {
                        if ($verses[$i2]->book == $verse->book && $verses[$i2]->chapter == $verse->chapter + 1) {
                            $verse->is_prequel_chapter = true;
                            if ($verses[$i2]->id == $verses[$i2 - 1]->id + 1) {
                                $verse->is_prequel_chapter_verse = true;
                            }
                        }
                        break;
                    }
                }
            }
            // marks initial paragraphs of Psalm titles and first verses
            if ($verse->verse <= 1 && !$verse->is_new_p) {
                $verse->is_new_p = 'x-first-p';
            }
            // marks closing colophon of epistles
            if ($verse->chapter == 0 && $verse->verse == 0) {
                $verse->is_colophon = true;
            }
            // if not at the start of chapter, and previous verse doesn't preceed, there is a gap
            if ( !$verse->is_start_of_chapter && ($verse->verse != $prev->verse + 1 || $verse->is_first_in_chapter) ) {
                if (!$verse->is_colophon || ($verse->is_colophon && $prev->verse != $prev->verses_in_chapter)) {
                    $verse->is_after_gap = true;
                    if (!$verse->is_new_p) { $verse->is_new_p = 'x-gap-p'; }
                    if ($verse->is_colophon) {
                        $verse->verses_in_chapter = $prev->verses_in_chapter;
                    }
                }
            }
            // verse at beginning of current paragraph
            $verseP = ($verse->is_new_p ? $verse : $verseP);

            // CLOSING...
            // announces closing colophon in epistles
            if ($verse->book == $next->book && $next->chapter == 0 && $next->verse == 0 && $verse->verse == $verse->verses_in_chapter) {
                $verse->is_before_colophon = true;
            }

            // if a different book or chapter than previous, indicate last in chapter
            if ($verse->book != $next->book || $verse->chapter != $next->chapter) {
                if (!$verse->is_before_colophon) {
                    $verse->is_last_in_chapter = true;
                }
            }
            if ($verse->verse == $verse->verses_in_chapter || $verse->is_colophon) {
                if (!$verse->is_before_colophon) {
                    $verse->is_end_of_chapter = true;
                    $verseP->is_end_p_of_chapter = true;
                }
            }
            
            // if not at the end of chapter, and next verse doesn't follow, there is a gap
            if ( !$verse->is_end_of_chapter && ($next->verse != $verse->verse + 1 || $verse->is_last_in_chapter) ) {
                if (!$verse->is_before_colophon) {
                    $verse->is_before_gap = true;
                }
            }
            
        } // end for loop
        
    } // processVerses()

} // Bible{}
