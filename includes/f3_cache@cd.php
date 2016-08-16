<?php
$nocache = false; // this value should be set to TRUE if page should not be cached, such as streaming music for IE 5.5
// @CD flag for automatic page generation
$cd_ffw = true;

function manage_cache($action) {
//return false; // <- UNCOMMENT to bypass
	global $nocache, $uri, $url_bits, $uacrawler, $html;
	// if page is from a text-in-process, don't cache
	if($url_bits[0] == 'texts' && strlen($url_bits[1]) > 1 && substr($url_bits[1],0,1) == '_') { $nocache = true; }
	/* @CD skip
	// IE/Opera use <bgsound>, others use <embed> for midi
	if(substr($url_bits[2],0,4) == 'midi') { $nocache = true; }
	// having problems with about, search and help index pages being written over
	if($url_bits[0] && substr_count('about,search,help',$url_bits[0]) && !$url_bits[1]) { $nocache = true; }
	*/
	// if user agent is a crawler, don't cache
	
	if($nocache) { return false; }
// @CD sorting and Music Player not available on CD; sort should save to index.html
	// if other url args than list pages (only pages with 'sortby' args) or 'updatecache' (but not musicplayer) arg, DO NOT CACHE
//	if(!$_SERVER['QUERY_STRING'] || (isset($_GET['updatecache']) && !isset($_GET['musicplayer'])) ) {
		// if at the home page ($url_bits is empty), create index.html version for faster loading
		$sortby = 'title';
		if($_GET['sortby']) {
			$sortby = $_GET['sortby'];
		} elseif($url_bits[0] == 'bible') {
			$sortby = 'collection';
		} elseif(substr_count('/Foundation_Truth/Treasures_of_the_Kingdom/Dear_Princess/','/'.$url_bits[1].'/')) {
			$sortby = 'issue';
		}
		$cache_file = (is_collection($url_bits[1]) ? $sortby.'_index.html' : 'index.html');
		$cache_path = ($url_bits ? '@cd/' : '').$uri.$cache_file;
/*	} elseif($_GET['sortby'] || (isset($_GET['musicplayer']) && !$url_bits[1])) {
		foreach($_GET as $arg => $value) {
			$cache_file .= '_'.$arg.'-'.$value;
		}
		$cache_file .= '.html';
		$cache_path = '@cd/'.$uri.$cache_file;
	} else {
		// if not suitable for caching, fail
		return false;
	}*/

	// directory not needed for home page
	if(substr_count($cache_path,'/')) {
		$cache_dir = substr($cache_path,0,strrpos($cache_path,'/')+1);
	} else {
		$cache_path = '@cd/'.$cache_file;
	}

	// file input/output
	if($action == 'extract') {
		// extract if cached file exists AND is deemed good to extract (i.e., there is no new content)
		if(file_exists($cache_path) && good_cache($cache_path,filemtime($cache_path))) {
			$fp = fopen($cache_path, 'r');
			$html = fread($fp, filesize($cache_path));
			fclose($fp);
			return true;
		} else {
			return false;
		}
	} elseif($action == 'insert') {
		$html_cd = $html;
// @CD modify the page before caching
		// changes contact links to online only
		$html_cd = preg_replace("'(class=\"[^\"]*)(\" href=\"(?!http))([^\"]*)(/contact/|/search/)([^\"]*\")'","$1 www$2http://library.timelesstruths.org$4$5",$html_cd);
		$html_cd = preg_replace("'(href=\"(?!http))([^\"]*)(/contact/)([^\"]*\")'","class=\"www\" $1http://library.timelesstruths.org$3$4",$html_cd);
		// Bible search online only
		$html_cd = preg_replace("'(<form id=\"lib_query.*)(action=\")[^\"]*(\"[\s\S]*</form>)'","$1$2http://library.timelesstruths.org/search/$3<p style=\"text-align: center;\">Search Online</p>",$html_cd);
		// Music Player not included
		$html_cd = preg_replace("'<div class=\"notes\"([\s\S]*?)<a href=\"../music/\?musicplayer\"([\s\S]*?)</div>'","",$html_cd);
		// adds specific file names to offline pages
		$html_cd = preg_replace("'(href=\"(?!http)[^\"]*)((?<=/)\")'","$1index.html$2",$html_cd);
		// changes the media url routing
		$html_cd = preg_replace("'([\"\']../../../)([^\"]*)(/[^/]*\.)(sib|mid)([\"\'])'","$1_$4$3$4$5",$html_cd);
		$html_cd = preg_replace("'(..%2F..%2F..%2F)([^\"]*)(%2F[^%]*\.)(mp3)(\")'","$1_$4$3$4$5",$html_cd);
		// changes the icon url routing at the TOC level
		$html_cd = preg_replace("'(<p><img src=\")../../(?:library/texts/|www/help/)[^/]*/([^\.]*.jpg)'","$1$2",$html_cd);
		// changes the icon url routing at the listing level and welcome page
		$html_cd = preg_replace("'(<a[^<>]*><img src=\"(?:../){0,2})library/(texts/[^/]*/[^\.]*.jpg)'","$1$2",$html_cd);
		// changes the image url routing at the section level
		$html_cd = preg_replace("'(src=\"../../)../(?:library/texts/|www/help/)([^/]*/[^/]*.(?:jpg|gif)\")'","$1$2",$html_cd);
		// changes the pdf linking at the section level
		$html_cd = preg_replace("'(href=\"../)../../library/texts/[^/]*/([^/]*.pdf\")'","$1$2",$html_cd);
		// reword pdf linking
		$html_cd = preg_replace("'Download PDF'","View PDF",$html_cd);
		// removes search engine links
		$html_cd = preg_replace("'<div class=\"header hidden\">[\s\S]*?</div>'","",$html_cd);
		// changes jump menu options to actual pages
		$connector = (is_collection($url_bits[1]) ? '_' : '/');		
		$html_cd = preg_replace("'(<option value=\")([^/]*?)(\")'","$1$2".$connector."index.html$3",$html_cd);
		$html_cd = preg_replace("'(<option value=\")(music/[^/]*/)(\")'","$1../../$2index.html$3",$html_cd);	
		$html_cd = preg_replace("'(<option value=\")/'","$1",$html_cd);
		if($url_bits[2]) {
			$html_cd = preg_replace("'(<option value=\")'","$1../",$html_cd);
			// the non-cached version needs this, too
			$html = preg_replace("'(<option value=\")'","$1../",$html);
		}
		// fixes problem with jump menu page links
		$html_cd = preg_replace("'(<option value=\"../)([^/]*?)(_index.html)'","$1_/$2$3",$html_cd);
		// remove js forwarding from pages
		$html_cd = preg_replace("' onload=\"settime\(\);\"'","",$html_cd);
		$html_cd = preg_replace("'function nextPage[\s\S]*?(?=</script>)'","",$html_cd);
		if( substr_count($url_bits[2],'score') ) {
			// if this is a score, remove the ability to change notes
			$html_cd = preg_replace("'&nbsp;<span class=\"form_item\">Notes:(.*?)Apply\" />'","",$html_cd);
			// modify the menu values
			$html_cd = preg_replace("'(<option value=\").*?(\d+).*?(\")'","$1$2$3",$html_cd);
		}
		
		// modify Bible listing links
		$html_cd = preg_replace("'(bible/(?:_|Old_Testament|New_Testament)/)(\")$'","$1_/$2",$html_cd);
		
		$array_s = array("'bible/_/_/index.html'","'bible/Old_Testament/_/index.html'","'bible/New_Testament/_/index.html'",
						"'texts/_/_/index.html'","'texts/Foundation_Truth/_/index.html'","'texts/Treasures_of_the_Kingdom/_/index.html'","'texts/Dear_Princess/_/index.html'","'texts/Books/_/index.html'","'texts/Articles/_/index.html'",
						"'music/_/_/index.html'","'music/Select_Hymns/_/index.html'","'music/Evening_Light_Songs/_/index.html'","'music/Echoes_from_Heaven_Hymnal/_/index.html'","'music/The_Blue_Book/_/index.html'","'music/Sing_unto_the_Lord/_/index.html'");
		$array_s2 = array("'bible/_/index.html'","'bible/Old_Testament/index.html'","'bible/New_Testament/index.html'",
						"'texts/_/index.html'","'texts/Foundation_Truth/index.html'","'texts/Treasures_of_the_Kingdom/index.html'","'texts/Dear_Princess/index.html'","'texts/Books/index.html'","'texts/Articles/index.html'",
						"'music/_/index.html'","'music/Select_Hymns/index.html'","'music/Evening_Light_Songs/index.html'","'music/Echoes_from_Heaven_Hymnal/index.html'","'music/The_Blue_Book/index.html'","'music/Sing_unto_the_Lord/index.html'");
		$array_r = array('bible/_/_/collection_index.html','bible/Old_Testament/_/collection_index.html','bible/New_Testament/_/collection_index.html',
						'texts/_/_/title_index.html','texts/Foundation_Truth/_/issue_index.html','texts/Treasures_of_the_Kingdom/_/issue_index.html','texts/Dear_Princess/_/issue_index.html','texts/Books/_/title_index.html','texts/Articles/_/title_index.html',
						'music/_/_/title_index.html','music/Select_Hymns/_/title_index.html','music/Evening_Light_Songs/_/title_index.html','music/Echoes_from_Heaven_Hymnal/_/title_index.html','music/The_Blue_Book/_/title_index.html','music/Sing_unto_the_Lord/_/title_index.html');
		$html_cd = preg_replace($array_s,$array_r,$html_cd);
		$html_cd = preg_replace($array_s2,$array_r,$html_cd);
// END @CD modifications
		mkdir_path($cache_dir);
		// if content is eligible, create .html file in "@cd/" folder
		$cache_fp = @fopen($cache_path,'w');
		@fwrite($cache_fp, $html_cd);
		@fclose($cache_fp);
		@chmod($cache_path, 0777);
	}
}

function good_cache($path,$time) {
	// in generating @cd editions, not necessary to extract from cache
	return false;
	
// $path = path to check for __update.txt files,
// $time = file modified time to compare with __update.txt

	// home page cache always 'good'; if it needs replacing, it should be deleted
	if($path == 'index.html') { return true; }
	
	// if 'updatecache' arg is given (used as a manual update), cache is considered 'bad'
	if(isset($_GET['updatecache'])) { return false; }
	
	// if update instructions exist at the current path level, and they are more recent than the cached file, cached file is considered 'bad'
	if(file_exists($path.'__update.txt') && filemtime($path.'__update.txt') > $time) {
		return false;
	}
	
	// works recursively down to first cache dir (i.e. '@cd/')
	if(strlen($path) > strlen('@cd/')) {
		// where path = '/path/', chops off 'path/'; where path = '/path', chops off 'path'
		return good_cache( preg_replace("'[^/]*/?$'",'',$path) ,$time);
	}
	
	// if no more recent update instructions found, cache is good
	return true;
}
?>