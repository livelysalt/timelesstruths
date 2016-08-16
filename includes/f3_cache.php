<?php
$nocache = false; // this value should be set to TRUE if page should NOT be cached, such as streaming music for IE 5.5

function manage_cache($action) {
	if (file_exists('NOCACHE.txt')) {
		return false; // <- UNCOMMENT to bypass
	}
	global $nocache, $uri, $url_bits, $uacrawler, $html;
	// if page is from a text-in-process, don't cache
	if($url_bits[0] == 'texts' && strlen($url_bits[1]) > 1 && substr($url_bits[1],0,1) == '_') { $nocache = true; }
	// IE/Opera use <bgsound>, others use <embed> for midi
	if(substr($url_bits[2],0,4) == 'midi') { $nocache = true; }
	// having problems with about, search and help index pagez being written over
	if($url_bits[0] && substr_count('about,search,help',$url_bits[0]) && !$url_bits[1]) { $nocache = true; }
	// if user agent is a crawler, don't cache
	
	if($nocache) { return false; }
	// if other url args than list pages (only pages with 'sortby' args) or 'updatecache' (but not musicplayer) arg, DO NOT CACHE
	if(!$_SERVER['QUERY_STRING'] || $_SERVER['QUERY_STRING'] == 'updatecache' || $_SERVER['QUERY_STRING'] == 'subdomain=library') {
		// if at the home page ($url_bits is empty), create index.html version for faster loading
		$cache_file = 'index.html';
		$cache_path = ($url_bits ? 'cache/' : '').$uri.$cache_file;
	} elseif($_GET['sortby'] || (isset($_GET['musicplayer']) && !$url_bits[1]) || isset($_GET['updatecache'])) {
		foreach($_GET as $arg => $value) {
			if($arg == 'updatecache') { break; }
			$cache_file .= '_'.$arg.'-'.$value;
		}
		$cache_file .= '.html';
		$cache_path = 'cache/'.$uri.$cache_file;
	} else {
		// if not suitable for caching, fail
		return false;
	}

	// directory not needed for home page
	if(substr_count($cache_path,'/')) {
		$cache_dir = substr($cache_path,0,strrpos($cache_path,'/')+1);
	}

	// file input/output
	if($action == 'extract') {
        if (NORMALIZED_LOCALHOST) return false;
		// extract if cached file exists AND is deemed good to extract (i.e., there is no new content)
		if(file_exists($cache_path) && good_cache($cache_path,filemtime($cache_path))) {
			/*$fp = fopen($cache_path, 'r');
			$html = fread($fp, filesize($cache_path));
			fclose($fp);*/
            $html = file_get_contents($cache_path);
			return true;
		} else {
			return false;
		}
	} elseif($action == 'insert') {
// POSSIBLE WHITE SPACE STRIPPER
/*		$html = preg_replace("'(<[^/>]+?>)\s+(<[^/>]+?>)'","$1$2",$html);
		$html = preg_replace("'(</[^>]+?>)\s+(</[^>]+?>)'","$1$2",$html);
		$html = preg_replace("'\s+'"," ",$html);
		$html = preg_replace("'((?:table|tr|td|option)>) (<(?:table|tr|td|option))'","$1$2",$html);*/
		mkdir_path($cache_dir);
		// if content is eligible, create .htm file in "cache/" folder
		/*$cache_fp = @fopen($cache_path,'w');
		@fwrite($cache_fp, $html);
		@fclose($cache_fp);*/
        @file_put_contents($cache_path, $html);
        do {
            @chmod($cache_path, 0777);
            $cache_path = preg_replace("'/[^/]+$'",'',$cache_path);
            $i++;
        } while(substr_count($cache_path, 'cache') && $i <= 5);
	}
}

function good_cache($path,$time) {
// $path = path to check for __update.txt files,
// $time = file modified time to compare with __update.txt
	// home page cache always 'good'; if it needs replacing, it should be deleted
	if($path == 'index.html') return true;
    
    // [2015-02-23] triggered by obsolete /pdf/ pages
    if ($_COOKIE['updatecache']) {
        delete_path($path);
        setcookie("updatecache",'',0,"/");
        return false;
    }
	
	// if 'updatecache' arg is given (used as a manual update), cache is considered 'bad'
	if (isset($_GET['updatecache'])) {
	    if (isset($_GET['purge'])) {
	        delete_path(preg_replace("'/[^/]+$'", '', $path));
	    } else {
            delete_path($path);
		}
		return false;
	}
	
	// if update instructions exist at the current path level, and they are more recent than the cached file, or cache is older than two weeks, cached file is considered 'bad'
	if((file_exists($path.'__update.txt') && filemtime($path.'__update.txt') > $time) || strtotime("-2 weeks") > $time) {
		return false;
	}
    
    // [2016-07-15] mp3 audio update cannot use previusly-cached files
    if (substr_count($path, '/music/') && $time < strtotime('2016-07-16') && substr_count(file_get_contents($path), '.mp3')) {
        return false;
    }
	
	// works recursively down to first cache dir (i.e. 'cache/')
	if(strlen($path) > strlen('cache/')) {
		// where path = '/path/', chops off 'path/'; where path = '/path', chops off 'path'
		return good_cache( preg_replace("'[^/]*/?$'",'',$path) ,$time);
	}
	
	// if no more recent update instructions found, cache is good
	return true;
}
