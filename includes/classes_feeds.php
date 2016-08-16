<?php
// classes_feeds.php
// included from _urlhandler.php

//variables available from url: /$type/$title/$section/
require_once "includes/f3_common.php";
require_once "includes/f_dbase.php";

require_once "includes/f3_cache.php";
// if successful, populates $html with cached file
if(!manage_cache("extract")) {
	// collect html
	output_feed($title,$section);
	// cache page
	manage_cache("insert");
}
// outputs rss 2.0 feed
@header ("Content-type: text/xml");
echo $html;

// log visit
//$table = "stats_tt3"; @include "_stats/write_stat.php";


function output_feed($type,$section) {
	if($type == 'rss2') {
		//global database variable array passed to database connection from included "f_dbase.php"
		global $db, $html;
	
		db_connect($db);
	
		$items = array();
		if($section != 'music') {
			$description = 'texts ';
			$rTexts = mysql_query("SELECT date,collection,title,url_title,subject,excerpt FROM ". $db['tt3_texts'] ." ORDER BY date DESC");
			// grab latest text
			for($i=0; $i < 3; $i++) {
				$arrTexts=mysql_fetch_assoc($rTexts);
				$url_title = $arrTexts['url_title'];
				
				// key is date.'b' : texts get priority ('b' is higher than 'a' in reverse chronological) over music on the same day
				$items[$arrTexts['date'].'b'] = new RSSItem(
					($section != 'texts' ? 'Texts: ' : '').$arrTexts['title'], // title
					'http://library.timelesstruths.org/texts/'.$url_title.'/', // link
					// if multiple subjects are listed, only use first
					substr_count($arrTexts['subject'],',') ? substr($arrTexts['subject'],0,strpos($arrTexts['subject'],',')) : $arrTexts['subject'], // category
					$arrTexts['date'], // pubDate
					strip_tags($arrTexts['excerpt']) // description
					);
			}
		}
		if($section == '') {
			$description .= 'and ';
		}
		if($section != 'texts') {
			$description .= 'sheet music ';
			$r = mysql_query("SELECT collection,title,sib FROM ". $db['tt3_scores'] ." ORDER BY sib DESC");
			// grab 7 latest scores
			for($i=0; $i < 7; $i++) {
				if($arr=mysql_fetch_assoc($r)) {
					// matches the latest collection given
					preg_match("'<([^<:]*?):([^:/]*?)/([^/:]*?):([^/>]*?)>$'",$arr['collection'],$cm);
		
					$arrM = mysql_fetch_assoc(mysql_query("SELECT title, subject, verses FROM ". $db['tt3_music'] ." WHERE collection LIKE '%".addslashes($cm[0])."%'"));
					$title_url = title_url_format(title_unbracket($arrM['title']));
					// extracts first two lines from first verse
					preg_match("'^<verse(?:[^>]*)>(.*?)(?:<br />(.*?))?<(?:/verse|br)'",$arrM['verses'],$vm);
					// key is date.'a' (and title, to distinguish from any other on that date) as it should follow the Recent Texts when sorted in reverse;
					$items[$arr['sib'].'a'.$title_url] = new RSSItem(
						($section != 'music' ? 'Music: ' : '').$arrM['title'], // title
						'http://library.timelesstruths.org/music/'.$title_url.'/score'.($cm[4] ? '_'.$cm[4] : '').'/', // link
						// if multiple subjects are listed, only use first
						substr_count($arrM['subject'],',') ? substr($arrM['subject'],0,strpos($arrM['subject'],',')) : $arrM['subject'], // category
						$arr['sib'], // pubDate
						$vm[1].' / '.$vm[2].' &#8230;' // description
						);
				}
			}
		}
		// disconnect from database
		db_disconnect($db);
		
		// sort by reverse chronological
		krsort($items);
		// grabs the latest item's date
		foreach($items as $item) {
			$pubDate = $item->pubDate;
			break;
		}
		$html .= '<?xml version="1.0"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
<title>Timeless Truths - Recent '.($section == '' ? 'Additions' : ucfirst($section)).'</title>
<link>http://library.timelesstruths.org/</link>
<atom:link href="http://library.timelesstruths.org/feeds/rss2/" rel="self" type="application/rss+xml" />
<description>'.ucfirst($description).'recently added to the Timeless Truths Free Online Library</description>
<language>en-us</language>
<webMaster>webmaster@timelesstruths.org (Timeless Truths Webmaster)</webMaster>
<pubDate>'.format_date($pubDate).'</pubDate>
<lastBuildDate>'.format_date($pubDate).'</lastBuildDate>
<ttl>1440</ttl>';
	
		foreach($items as $item) {
			$html .= "\n\n".
'<item>
<title>'.apply_formatting($item->title).'</title>
<link>'.$item->link.'</link>
<guid>'.$item->link.'</guid>
<description>'.$item->description.'</description>
<category>'.$item->category.'</category>
<pubDate>'.format_date($item->pubDate).'</pubDate>
</item>';
		}
	
		$html .= "\n".
'</channel>
</rss>';
	}
}

function format_date($date_mysql) {
	// matches digit sets
	preg_match("'(\d{4})-?(\d{2})-?(\d{2})'",$date_mysql,$date);
	// formats date from sets
	return @date("r",mktime(0,0,0,$date[2],$date[3],$date[1]));
}

class RSSItem {
	var $title;
	var $link;
	var $category;
	var $pubDate;
	var $description;
	
	function RSSItem ($title, $link, $category, $pubDate, $description) {
		$this->title = $title;
		$this->link = $link;
		$this->category = $category;
		$this->pubDate = $pubDate;
		$this->description = $description;
	}
}
?>