<?php
// basic class includes variable for storing markup
class HTML {
	var $html = '';
	// other variables may be added by extending classes
}

class Page extends HTML {
	var $document;
	var $parsed; // used by parser_xml_texts
	
	var $h_meta;
	var $h_body;
	var $h_globalnav;
	var $h_subnav;
	var $h_header;
	var $h_navbar;
	var $h_audio;
	var $h_content;
	var $h_sidetable;
	var $h_score;
	var $h_source;
	var $h_footer;

	
	function Page($type, $title, $section) {
		if($type == 'music' && $title) {
			if($title && !is_collection($title)) {
				$this->document = new MusicDoc($title,$section);
			} elseif($title) {
				$this->document = new MusicListing($title,$section);
			}
		} elseif(($type == 'texts' || $type == 'help') && $title) {
			if($title && !is_collection($title)) {
				$this->document = new TextDoc($type,$title,$section);
			} elseif($title) {
				$this->document = new TextsListing($title,$section);
			}
		} elseif(($type == 'bible') && $title) {
			if($title && !is_collection($title)) {
				$this->document = new BibleDoc($type,$title,$section);
			} elseif($title) {
				$this->document = new BibleListing($title,$section);
			}
		} elseif($_GET['query'] == 'bible') {
			$this->document = new BibleQueryList();
		} elseif($_GET['query'] == 'music') {
			$this->document = new MusicQueryList();
		} elseif(!$title && !$_GET['query']) {
			$this->document = new SiteSplit($type);
		}

		$this->h_meta = new hMeta($type,$section,$this->document);
		$this->h_body = new hBody($type);
		$this->h_globalnav = new hGlobalNav($type);
		$this->h_subnav = new hSubNav($type,$this->document->collection);
		if(get_parent_class($this->document) && substr_count( 'document|listing|querylist', strtolower(get_parent_class($this->document)) )) {
			// header and navigation bar only required in certain situations
			$this->h_header = new hHeader($type,$section,$this->document);
			$this->h_navbar = new hNavBar($type,$section,$this->document);
		}
		// audio controls may be present in audio-capable pages
		if( is_audio(get_parent_class($this->document)) ) {
			$this->h_audio = new hAudio($type,$title,$section);
		}
		
		$this->h_content = new hContent($type,$title,$section,$this->document);
		
		// side table only required for site pages
		if	( strtolower(get_class($this->document)) == 'sitesplit'
			||	( strtolower(get_parent_class($this->document)) == 'document'
				// music document pages, except for score
				&&	(	($type=='music' && !is_score() )
					// document TOCs
					||	(substr_count('/bible/texts/help/','/'.$type.'/') && !$section)
					)
				)
			// collection indices
			||	( strtolower(get_parent_class($this->document)) == 'listing'
				&&	is_collection($title)
				&&	!$section
				)
			) {
			$this->h_sidetable = new hSideTable($type,$section,$this->document);
		}
		if( ($type=='music' && strtolower(get_parent_class($this->document))=='document' && is_score() ) ) {
			// score only required in specific situations
			$this->h_score = new hScore($title,$section,$this->document);
		}
		if($type=='music' && !is_score() && strtolower(get_parent_class($this->document))=='document') {
			// source only required in music documents
			$this->h_source = new hSource($this->document->source);
		}
		if(strtolower(get_parent_class($this->document)) != 'document' && strtolower(get_parent_class($this->document)) != 'listing') {
			// footer only required when navbar is absent
			if(strtolower(get_parent_class($this->document)) != 'querylist') {
				$this->h_footer = new hFooter($type,$title);
			}
		}
	}
	
	function output($method="return") {
		$this->stitch();
		if($method=="return") {
			return $this->html;
		}
	}
	function stitch() {
		$this->html .= $this->h_meta->html;
		$this->html .= $this->h_meta->parse($this->document);
		$this->html .= $this->h_body->pre;
		$this->html .= $this->h_globalnav->html;
		$this->html .= $this->h_subnav->html;
		$this->html .= $this->h_header->html;
		$this->html .= $this->h_navbar->pre;
		$this->html .= $this->h_audio->html;
		$this->html .= $this->h_content->pre;
		$this->html .= $this->h_sidetable->pre;
		$this->html .= $this->h_sidetable->html;
		$this->html .= $this->h_sidetable->post;
		$this->html .= $this->h_content->html;
		
		$this->html .= $this->h_content->parse($this->document);
		$this->html .= $this->h_sidetable->final;
		$this->html .= $this->h_content->post;
		$this->html .= $this->h_score->html;
		$this->html .= $this->h_source->html;
		$this->html .= $this->h_navbar->post;
		$this->html .= $this->h_footer->html;
		$this->html .= $this->h_body->post;
	}
}

class hMeta extends HTML {
	function hMeta($type,$section,&$document,$meta="A free online library of books, music, magazines, and more.") {
		//if the current score has an associated author name, add to author line (identical code for page TITLE and document info)
		$score_author = false;
		if(is_score() || substr($section,0,4)=='midi') {
			$score_id = (substr($section,6) ? substr($section,6) : '0'); // '0' is the default score with no extension, and is only needed for sorting purposes if there are more than one score to a music title
			if($document->score[$score_id]->author) {
				$score_author = preg_replace("'^.*?>(.*?)</author>[\s\S]*$'","$1",$document->score[$score_id]->author); // matches the first author given
				$meta = $score_author;
				$score_author = ' / '.$score_author;
			}			
		} elseif($type == 'music' && $section == '') {
			$meta = preg_replace("'^.*?>(.*?)</author>[\s\S]*$'","$1",$document->author); // matches the first author given
		}
		
		$medialine = false;
		$s_media = ($section ? substr($section,0,4) : 'none');
		if($type=='music' && /*$document->collection &&*/ !is_collection($document->title)) {
			$arrDescrip = array(
				'scor' => 'Score Sheet Music',
				'pdf'  => 'PDF Sheet Music',
				'pdf_' => 'PDF Sheet Music',
				'midi' => 'MIDI',
				'hifi' => 'High Quality MP3',
				'lofi' => 'Dial-Up Quality MP3',
				'lowm' => 'Dial-Up Quality WMA',
				'none' => 'Lyrics'
			);
			if(!array_key_exists($s_media,$arrDescrip)) {
				header('Location: http://'. NORMALIZED_DOMAIN .'music/'.title_url_format($document->title).'/');
				exit;
			}
			$document->description = $arrDescrip[$s_media];
			$medialine = ' &gt; ' . $document->description;
			$meta = $document->description .': '. $meta;
		}
		if(!$document->author && !$title) { $document->author = 'Timeless Truths Free Online Library'; }
		if( is_collection($document->title) ) {
			if(!$document->section_real) { $document->section_real = 'All'; }
			$titleline = (!$document->section ? $document->title.' | ' : $document->section_real.' | '.$document->title).' sorted by '.$document->sortby;
		} elseif($type == 'music') {
			$titleline = $document->title.$medialine.' | '.preg_replace("'^.*?>(.*?)</author>[\s\S]*$'","$1",$document->author).$score_author;
		} else if(substr_count('bible|texts|help',$type)) {
			$section_line = ' &gt; '.($type == 'bible'
				? (($bible_id = $document->parts[$document->this_pNum]->sections[$document->this_sNum]->id) && $document->title == 'Psalms' ? 'Psalm ' : 'Chapter ').$bible_id
				: strip_tags($document->parts[$document->this_pNum]->sections[$document->this_sNum]->title) );
			// don't try to add section name to title bar if displaying Whole Document or no section name is available
			$titleline = $document->title.($document->this_pNum === false || !isset($document->this_pNum)? '' : $section_line).' | '.preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$document->author);
		} elseif($_GET['query']) {
			$titleline = $document->title;
		} else {
			$titleline = ($document->title == 'Welcome' ? '' : $document->title.' | ').'Timeless Truths Free Online Library';
		}
		$this->html = '
		<!DOCTYPE html>
		<html>
		
		<head>
            <meta charset="utf-8">
		    <meta http-equiv="X-UA-Compatible" content="IE=edge">
		    <meta name="viewport" content="width=device-width, initial-scale=1">
		    <!-- Latest compiled and minified CSS -->
			<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
			<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    		<!-- WARNING: Respond.js doesnt work if you view the page via file:// -->
    		<!--[if lt IE 9]>
      		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    		<![endif]-->

			<title>'.apply_formatting($titleline).'</title>
			<meta name="description" content="'.$meta.'" />
			<meta name="author" content="">
			<link rel="shortcut icon" href="'.level_url_webfront().'timeless.ico" />
            <link rel="stylesheet" href="'.level_url_webfront().'tt3.'. (DEV ? 'dev' : FILENAME_JS_CSS_DATE) .'.css" media="all" />
            <style>
            /*! Temp style for testing
			 * Timeless Truths 3 Column Bootstrap template - by thezoomla.com(http://www.thezoomla.com)
			 * Code licensed under the Apache License v2.0.
			 * For details, see http://www.apache.org/licenses/LICENSE-2.0.
			 */
			.book-section,.search-a-to-z p:hover,.sidebar-item{box-shadow:0 1px 3px 0 rgba(0,0,0,.2)}body{padding-top:55px}body,button,input,select,textarea{color:#444;font:16px "Open Sans",serif;line-height:1.6;word-wrap:break-word;font-weight:400}.portfolio-item{margin-bottom:25px}.book-section,.sidebar-item{padding:10px;margin-bottom:25px}a{color:#000}a:hover{color:#289DCC}.navbar{margin-bottom:0;height:55px}.navbar-nav{margin:5px -15px 7.5px}.navbar-inverse{background-color:#fff;border-color:#289DCC;border-bottom:5px solid #289DCC;color:#232323;font-weight:500}.navbar-inverse .navbar-brand,.navbar-inverse .navbar-brand:focus,.navbar-inverse .navbar-brand:hover{color:#289DCC;text-transform:uppercase;font-weight:600}.navbar-inverse .navbar-nav>li>a{color:#289DCC;font-size:14px;text-transform:uppercase;background-color:#fff;margin-top:-5px}.navbar-default .navbar-toggle .icon-bar,.navbar-inverse .navbar-nav>li>a:hover,.navbar-inverse .navbar-toggle .icon-bar{background-color:#289DCC}.navbar-inverse .navbar-toggle{color:#289DCC;border-color:#fff}.mumbo-jumbo,.welcome-message{color:#fff;background-color:#289dcc;background-image:url(../images/checked-background.png);padding:100px 0;margin-bottom:50px}.mumbo-jumbo{background-color:#289dcc;background-image:url(../images/square-background.png)}.mumbo-jumbo .page-header,.welcome-message .page-header{font-size:82px;font-weight:700;color:#fff;margin-top:0}.btn-outline{padding:10px 15px;border:1px solid #fff;background-color:transparent;color:#fff;font-weight:400;font-size:16px}.btn-outline:hover{background-color:#fff;color:#289DCC}.btn-orange{background-color:#e05e48;border:none}.btn-lightblue{background-color:#264967;border:none}.email-subscribe{color:#fff;background-color:#289dcc;background-image:url(../images/checked-background.png);padding:50px 0;margin-top:50px}.email-subscribe .input-group{max-width:500px;margin:30px auto 0}footer{background-color:#303440;color:#fff;padding:50px 0}footer ul li{line-height:1.5;padding:5px 0;border-bottom:1px solid #444}footer ul li a{color:#fff}.book-section .post-title-blue,footer .post-title-blue,section .post-title-blue{border-bottom:2px solid #289dcc;font-size:18px;margin-bottom:15px;padding-bottom:0}.book-section .post-title-blue span,footer .post-title-blue span,section .post-title-blue span{background-color:#289dcc;color:#fff;padding:6px 12px;display:inline-block}footer .copyright{padding:50px 0 15px;color:#b1b6b6;font-size:14px}@media (max-width:767px){.welcome-message .page-header{font-size:52px}}@media (min-width:768px) and (max-width:991px){.welcome-message .page-header{font-size:62px}}@media (min-width:992px) and (max-width:1199px){.welcome-message .page-header{font-size:82px}}.edit-option{position:relative;display:none}@media (min-width:768px){.edit-option{position:relative;display:block}}.btn-edit{position:absolute;top:0;right:0;z-index:10;display:block;padding:5px 8px;font-size:12px;color:#767676;cursor:pointer;background-color:#fff;border:1px solid #e1e1e8;border-radius:0 4px}.faq-section{background:url(default-bg-bbc.gif) 50% 0 no-repeat}.faq-content{font-family:Helvetica,Arial,sans-serif;padding:25px 50px 50px;font-weight:400;background:url(default-bg-bbc.gif) no-repeat}.faq-content h1,.faq-content h2,.faq-content strong{text-transform:uppercase;vertical-align:baseline;color:#289dcc;font-weight:600}.faq-content .panel-group .panel-heading{padding-top:20px}.faq-content a{color:#289dcc}.faq-content .panel-primary strong{color:#fff}.faq-content .panel-group .panel{border-radius:0}.margin-top-40{margin-top:40px}.search-a-to-z h2{color:#fff;background-color:#4B4644;padding:10px 5px;line-height:50px;font-size:16px}.search-a-to-z p{padding:10px 5px;color:#4B4644;background-color:silver}.atoz{padding-left:10px;padding-right:10px}.text-menu,.text-menu a{background-color:#081F2C;color:#fff;line-height:40px;font-size:16px;padding:10px 5px;text-transform:uppercase}.text-menu a:hover{text-decoration:underline}#form-messages *,#form-messages :after,#form-messages :before{-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box}#page-wrapper{width:640px;background:#FFF;padding:1em;margin:1em auto;border-top:5px solid #69c773;box-shadow:0 2px 10px rgba(0,0,0,.8)}#ajax-contact{padding:0 10px}#form-messages h1{margin-top:0}            
            </style>
            ';
		global $url_bits;
		if(substr_count('//texts/music/','/'.$url_bits[0].'/') && !$url_bits[1]) {
			$this->html .= '
			<link rel="alternate" title="Timeless Truths - Recent '.($url_bits[0] == '' ? 'Additions' : ucfirst($url_bits[0])).'" href="'.level_url().'feeds/rss2/'.($url_bits[0] != '' ? $url_bits[0].'/' : '').'" type="application/rss+xml" />';
		}
		// asynchronous Google Analytics tracking
        $this->html .= "
            <script>(function(h){h.className=h.className.replace('no-js','js". (DEV ? ' dev' : '') ."')})(document.documentElement)</script>
            <script>(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;if(document.location.hostname!='localhost')m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
            ga('create','UA-1872604-1','timelesstruths.org');ga('send','pageview');</script>
            ";
	}
	
	function parse(&$document) {
		$html = ($document->type != 'welcome' ? '
<link rel="start" title="Library Welcome Page" href="'.level_url().'" />' : '' ).'
<link rel="help" title="Help" href="'.level_url().'help/" />
<link rel="search" title="Search" href="'.level_url().'search/" />'.(substr_count("textlist|musiclist|textdoc",strtolower(get_class($document))) ? '
<link '.(strtolower(get_class($document)) == 'textdoc' ? 'rel="contents first" title="Table of Contents"' : 'rel="index first" title="Index"').' href="?section=" />' : '').($document->prev_link ? '
<link rel="prev" title="Previous Page: '.strip_tags($document->prev_title).'" href="'.$document->prev_link.'" />' : '').($document->next_link ? '
<link rel="next" title="Next Page: '.strip_tags($document->next_title).'" href="'.$document->next_link.'" />' : '').'
';
		global $url_bits;
		// base functions loaded first to make sure accessed functions are present
		//removed the script files and added them before the end of body tag so that
		//after the page loads the script files will load

		$html .= "\r\n"
		.'</head>';

		return $html;
	}
}

class hBody extends HTML {
	var $pre;
	var $post;
	function hBody($type) {
		if(substr_count("bible|texts|music|welcome",$type)) {
			$color = "green";
		} else {
			$color = "blue";
		}
		global $url_bits;
		$this->pre = "\r\n"
		.'<body data-level="'.level_url().'" data-relhost="http://'. NORMALIZED_LOCALHOST .'">';

		$this->post = "\r\n"
		.'
		<!-- jQuery required for Bootstraps -->
    	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    	<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
        <script src="'.level_url_webfront().'tt3.'. (DEV ? 'dev' : FILENAME_JS_CSS_DATE) .'.js"></script>		
		</body>'
		."\r\n".'</html>';
	}
}

class hGlobalNav extends HTML {
	function hGlobalNav($selected=false) {
		$options = '&nbsp;<script>document.write("<button id=\'btn_translate\'>Translate</button>");</script>';
		$google_translate = '<span id="translate"><span id="google_translate"></span></span><script>TT.Utility.initTranslate();</script>';

		$this->html = '<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
        <div class="container">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="http://library.timelesstruths.org" accesskey="1">Timeless Truths</a></a>
            </div>
            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="//'.'bible.timelesstruths.org/">Bible</a></li>
					<li><a href="'.level_url().'texts/">Texts</a></li>
					<li><a href="'.level_url().'music/">Music</a></li>
					<li><a href="'.level_url().'about/">About Us</a></li>
					<li><a href="'.level_url().'help/">Help</a></li>
					<li>'.$options.$google_translate.'</li>
					<li>
                    <a href="'.level_url().'search/"><i class="glyphicon glyphicon-search"></i><span class="hidden-lg hidden-md hidden-sm">Search Timeless Truths</span></a>
                	</li></ul>
            </div>
            <!-- /.navbar-collapse -->
        </div>
        <!-- /.container -->
    </nav>';
	}
}

// creates the site subnavigation bar with a list of the current ancestor category and siblings, if applicable
class hSubNav extends HTML {
	function hSubNav($type,$music_coll) {
		$this->html = "\r\n\t".'<nav class="text-menu">
        	<div class="container">
            	<div class="text-center">';
		if($type == "bible") {
		    //[2012-10-12] removed
			//$this->html .= $this->list_colls($type,array("Old Testament","New Testament"),$music_coll);
		} elseif($type == "texts") {
			$this->html .= $this->list_colls($type,array("Foundation Truth","Treasures of the Kingdom","Dear Princess","Books","Articles"),$music_coll);
		} else if($type == "music") {
			$this->html .= $this->list_colls($type,array("Select Hymns","Evening Light Songs","Echoes from Heaven Hymnal","The Blue Book","Sing unto the Lord"),$music_coll);
		} else if($type == "help") {
			$this->html .= $this->list_colls($type,array("Help"),$music_coll);
		} else {
			$this->html .= "";//date('F j, Y'); // timezone set to Pacific
		}
		$this->html .= "\r\n\t".'</div>
						</div>
    				</nav>';
        
        if ($type == 'bible') {
            $this->html .= "\r\n"
                .'<div class="alert alert-warning alert-dismissible" role="alert">
  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                    .'<p><strong>We have a new site for online Bible study, featuring Strong\'s definitions, in addition to search and audio.</strong></p>'
                    .'<p style="line-height:5em; font-size:20px;"><a href="//'. NORMALIZED_LOCALHOST .'bible.timelesstruths.org/">bible.timelesstruths.org</a></p>'
                .'</div>';
        }
	}
	
	// takes a list of category siblings and highlights the current one
	function list_colls($type,$array,$music_coll) {
		if($array[0] == 'Help') { // at present Help is too small to have a full 3-level heirarchy, so the listing level is ommited
			$html = "\r\n"
				.'<a class="current" href="'.level_url().'help/">Help</a>';
		} else {
			$music_coll = str_replace('_',' ',$music_coll);
			$bible_x = ($type == 'bible' ? '_/' : ''); // Bible collections currently use shorcut to full indices
			if($type != 'help') {
				$query = add_query();
				$html = "\r\n\t\t\t"
					.'<a'.($music_coll == ' ' ? ' class="current"' : '')
					.' href="'.level_url().$type.'/_/'.$bible_x.$query.'">All</a>';
			}
			foreach($array as $collection) {
				$query = add_query();
				preg_match("'$collection\:([^:/]*?)/'",$music_coll,$cm);
				$coll_display = preg_replace("'(Echoes from Heaven) Hymnal'","$1",$collection);
				$html .= "\r\n"
					.'| <a'.(substr_count($music_coll,$collection) ? ' class="current"' : '')
					.' href="'.level_url().$type.'/'.title_url_format($collection).'/'.$bible_x
					.$query.'"'
					.($cm[1] ? ' title="#'.$cm[1].'"' : '')
					.'>'.$coll_display.'</a>';
			}
		}
		return $html;
	}
}

class hFooter extends HTML {
	function hFooter($type,$title) {
		$this->html = '<footer>            
			<div class="container">
        		<div class="row">
            		<div class="copyright">
            			<p class="text-center">
        					<!--Site by LivelySalt. --><a href="'.level_url().'help/Management_and_Policies/Copyrights/">Copyright</a> &copy; 2002-2016 Timeless Truths Publications. <span id="footer-hostedby">Hosted by <a href="http://ibiblio.org">ibiblio</a>.</span>
        				</p>
        			</div>
        		</div>
    		</div> <!-- End of Footer Container-->
		</footer>
		';
	}
}

class hHeader extends HTML {
	function hHeader($type,$section,&$document) {
		if(strtolower(get_parent_class($document))=='document') {
			//lists only primary author
			$author = preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$document->author);
			//if the current score has an associated author name, add to author line (identical code for page TITLE and document info)
			if(substr_count('/scor/pdf/pdf_/midi/hifi/lofi/lowm/','/'.substr($section,0,4).'/')) {
				$score_id = (substr_count($section,'_') ? preg_replace("'^.+_'",'',$section) : '0'); // '0' is the default score with no extension, and is only needed for sorting purposes if there are more than one score to a music title
				if($document->score[$score_id]->author) {
					$score_author = ' / '.preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$document->score[$score_id]->author);
				}
			}
			// if not at score already, gets score data, if any, for links
			if($type=='music' && !is_score() ) {
				foreach($document->score as $s_id => $score) {
					if($score->sib) {
						// create tooltip-compatible 'title|primary-author' string
						$tooltip = ($score->title ? $score->title.' | ' : '').preg_replace("'^.*?>(.*?)</author>[\s\S]+$'","$1",$score->author);
						$score_links .= "\r\n\t\t".'<span class="score_link"><a href="'.level_url().'music/'
						// append score id, if any, to link
						.title_url_format($document->title).'/score'.($score->id ? '_'.$score->id : '')
						.'/" title="'.title_tooltip_format($tooltip).'">'
						.'<img src="'.level_url_webfront().'link_score.gif" alt="'.title_tooltip_format($tooltip).'" /></a></span>';
					}
				}
			}
			// if not at music > lyrics, or text > table of contents, provide link
			if($section != '') {
				$title_link =
					'<a href="'.level_url().$type.'/'.($document->url_title ? $document->url_title : title_url_format($document->title)).'/'
					.'" title="'.($type=='music' ? 'Show Lyrics' : 'Table of Contents').'">';
			}
			$this->html = 
			"\r\n\t".'<div class="container"><div class="row"><h1 class="header">'
				.($type=='music' ? $score_links : '')
				."\r\n\t\t".'<span class="title">'
					.($title_link ? $title_link : '<small class="current">')
					.apply_formatting($document->title)
					.($title_link ? '</a> ' : '</small> ')
					.'| <small>'.$author.$score_author.'</small>
				</span>
				<span class="pull-right">
					<small class="subject">'.$document->subject.'</small>
				</span>'
			."\r\n\t".'</h1></div></div>';
		} elseif(strtolower(get_parent_class($document)) == 'listing') {
			$sortby = $document->sortby;
			$order = $document->order;

			if(substr_count('|Foundation Truth|Treasures of the Kingdom|Dear Princess|','|'.$document->title.'|')) {
				$default = "\r\n\t".'<p><a href="./?sortby=issue&sortlast='.$document->sortby.'">'.$document->title.' sorted by issue</a></p>';
			} else {
				$default = "\r\n\t".'<p><a href="./?sortby=title&sortlast='.$document->sortby.'">'.$document->title.' sorted by title</a></p>';
			}
			$this->html = 
			"\r\n\t".'<div class="container"><div class="header hidden" style="display:hidden;"><!--SEARCH ENGINE FRIENDLY LINKS-->'
			.($type == 'bible' ? "\r\n\t".'<p><a href="./&sortlast='.$document->sortby.'">Bible sorted by collection</a></p>' : '')
			.$default
			.($type != 'bible' ? "\r\n\t".'<p><a href="./?sortby=author&sortlast='.$document->sortby.'">'.$document->title.' sorted by author</a></p>' : '')
			.($type != 'bible' ? "\r\n\t".'<p><a href="./?sortby=year&sortlast='.$document->sortby.'">'.$document->title.' sorted by year</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=lyrics&sortlast='.$document->sortby.'">'.$document->title.' sorted by lyrics</a></p>' : '')
			."\r\n\t".'<p><a href="./?sortby=subject&sortlast='.$document->sortby.'">'.$document->title.' sorted by subject</a></p>'
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=scripture&sortlast='.$document->sortby.'">'.$document->title.' sorted by scripture</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=scores&sortlast='.$document->sortby.'">'.$document->title.' sorted by scores</a></p>' : '')
			.($type == 'bible' ? "\r\n\t".'<p><a href="./?sortby=verse&sortlast='.$document->sortby.'">'.$document->title.' sorted by verse</a></p>' : '')
			.($type == 'texts' ? "\r\n\t".'<p><a href="./?sortby=excerpt&sortlast='.$document->sortby.'">'.$document->title.' sorted by excerpt</a></p>' : '')
			.($type == 'texts' ? "\r\n\t".'<p><a href="./?sortby=published&sortlast='.$document->sortby.'">'.$document->title.' sorted by publishing date</a></p>' : '')
			.($type == 'music' && $document->collection != '_' ? "\r\n\t".'<p><a href="./?sortby=number&sortlast='.$document->sortby.'">'.$document->title.' sorted by number</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=tune&sortlast='.$document->sortby.'">'.$document->title.' sorted by tune</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=composer&sortlast='.$document->sortby.'">'.$document->title.' sorted by composer</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=composed&sortlast='.$document->sortby.'">'.$document->title.' sorted by composed</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=meter&sortlast='.$document->sortby.'">'.$document->title.' sorted by meter</a></p>' : '')
			.($type == 'music' ? "\r\n\t".'<p><a href="./?sortby=key&sortlast='.$document->sortby.'">'.$document->title.' sorted by key</a></p>' : '')
			."\r\n\t".'</div></div>';

			if(substr_count('|Foundation Truth|Treasures of the Kingdom|Dear Princess|','|'.$document->title.'|')) {
				$default = "\r\n\t".'<option value="issue"'.($sortby=='' ? ' selected="selected"' : '').'>issue</option>';
			} else {
				$default = "\r\n\t".'<option value="title"'.(($sortby=='' && $type != 'bible') || $sortby == 'title' ? ' selected="selected"' : '').'>title</option>';
			}
			$this->html .= 
			"\r\n\t".'<div class="container"><div class="header"><!--HUMAN FRIENDLY FORM OPTION LIST-->
				<form class="form-inline" id="flist" name="flist" action="./" method="get">
					<div class="form-group">
					<label for="sortby" class="form_item">Sort by:</label>
					<select name="sortby" id="sortby" onchange="document.flist.submit();">'
			.($type == 'music' ? "\r\n\t".'<optgroup label="Lyrics">' : '')
			.($type == 'bible' ? "\r\n\t".'<option value="collection"'.($sortby=='' ? ' selected="selected"' : '').'>collection</option>' : '')
			.$default
			.($type != 'bible' ? "\r\n\t".'<option value="author"'.($sortby=='author' ? ' selected="selected"' : '').'>author</option>' : '')
			.($type != 'bible' ? "\r\n\t".'<option value="year"'.($sortby=='year' ? ' selected="selected"' : '').'>year</option>' : '')
			.($type == 'music' ? "\r\n\t".'<option value="lyrics"'.($sortby=='lyrics' ? ' selected="selected"' : '').'>lyrics</option>' : '')
			."\r\n\t".'<option value="subject"'.($sortby=='subject' ? ' selected="selected"' : '').'>subject</option>'
			.($type == 'music' ? "\r\n\t".'<option value="scripture"'.($sortby=='scripture' ? ' selected="selected"' : '').'>scripture</option>' : '')
			.($type == 'bible' ? "\r\n\t".'<option value="verse"'.($sortby=='verse' ? ' selected="selected"' : '').'>verse</option>' : '')
			.($type == 'texts' ? "\r\n\t".'<option value="excerpt"'.($sortby=='excerpt' ? ' selected="selected"' : '').'>excerpt</option>' : '')
			.($type == 'texts' ? "\r\n\t".'<option value="published"'.($sortby=='published' ? ' selected="selected"' : '').'>published</option>' : '')
			.($type == 'music' && $document->collection != '_' ? "\r\n\t".'<option value="number"'.($sortby=='number' ? ' selected="selected"' : '').'>number</option>' : '')
			.($type == 'music' ? "\r\n\t".'</optgroup>'
				."\r\n\t".'<optgroup label="Scores">'
				."\r\n\t".'<option value="tune"'.($sortby=='tune' ? ' selected="selected"' : '').'>tune</option>'
				."\r\n\t".'<option value="composer"'.($sortby=='composer' ? ' selected="selected"' : '').'>composer</option>'
				."\r\n\t".'<option value="composed"'.($sortby=='composed' ? ' selected="selected"' : '').'>year</option>'
				."\r\n\t".'<option value="meter"'.($sortby=='meter' ? ' selected="selected"' : '').'>meter</option>'
				."\r\n\t".'<option value="key"'.($sortby=='key' ? ' selected="selected"' : '').'>key</option>'
                ."\r\n\t".'<option value="published"'.($sortby=='published' ? ' selected="selected"' : '').'>published</option>'
				: '')
			.'				
					</select></div>'
					.'<div class="form-group"><input type="hidden" name="sortlast" id="sortlast" value="'.$document->sortby.'" />
					&nbsp;<span class="form_item">Order:</span>'
					.'<input type="radio" name="order" id="ascending" value="ascending"'.($order=='ascending' ? ' checked="checked"' : '').' />'
					.'<label for="ascending">ascending</label>'
					.'&nbsp;<input type="radio" name="order" id="descending" value="descending"'.($order=='descending' ? ' checked="checked"' : '').' />'
					.'<label for="descending">descending</label></div>'
					.'&nbsp;&nbsp;<input type="submit" class="btn btn-success" value="Apply" />
				</form>'
			."\r\n\t".'</div></div>';
		} elseif(strtolower(get_parent_class($document)) == 'querylist') {
			$this->html = 
			"\r\n\t".'<div class="container"><div class="header">'
			."\r\n".'<form class="form-inline" name="lib_query" action="./" method="get"><div class="form-group"><input type="hidden" name="query" id="query" value="'.$_GET['query'].'" /><label for="'.$input.'" class="form_item">'.ucfirst($_GET['query']).' query:</label>'
			.'<input type="text" style="width:35%;" name="q" id="q" accesskey="L" title="Alt+L" value="'.htmlentities(stripslashes(stripslashes( ($_GET['q'] ? $_GET['q'] : $_GET['passage']) ))).'" onfocus="this.select();" />&nbsp;&nbsp;<input type="submit" value="Look It Up" /></div></form>'
			."\r\n\t".'</div></div>'."\r\n";
		}
	}
}

class hNavBar extends HTML {
	function hNavBar($type,$section,&$document) {
		// adds document title to url if not a music document
		$url_title = ($type != 'music' || strtolower(get_parent_class($document)) == 'listing' ? title_url_format($document->title) : '');
		if($document->url_title) { $url_title = $document->url_title; }
		if(strtolower(get_parent_class($document)) == 'document') {
			// get code for dropdown jump menu, and if section is Table of Contents (but not music), modifies $document->xml
			$menu = doc_outline($document, $type, ($section == '' && $type != 'music'));
			// inserts section title "level" if not Table of Contents; and not music
			$prev_title_url = ($type != 'music' ? $url_title : '') . ($document->prev_title != 'Table of Contents' ? ($type != 'music' ? '/' : '') . title_url_format($document->prev_title) : '');
			$next_title_url = ($type != 'music' ? $url_title : '') . ($document->next_title != 'Table of Contents' ? ($type != 'music' ? '/' : '') . title_url_format($document->next_title) : '');
		} elseif(strtolower(get_parent_class($document)) == 'listing') {
			// get code for dropdown jump menu
			$menu = load_listing($type,$document);
			// converts title to url representation
			$url_title = ($url_title == 'Whole_Bible' || $url_title == 'All_Music' || $url_title == 'All_Texts' ? '_' : $url_title);
			$prev_title_url = $url_title . (	!substr_count($document->prev_title,' Index')
												? '/'.($document->sortby == 'number' ? preg_replace("'^\D+(\d+)$'","$1",$document->prev_title) : title_url_format($document->prev_title))
												: '');
			$next_title_url = $url_title . (	$section != '_'
												? '/'.($document->sortby == 'number' ? preg_replace("'^\D+(\d+)$'","$1",$document->next_title) : title_url_format($document->next_title))
												: ($type == 'bible' ? '/'.title_url_format($document->next_title) : '') );
		} elseif(strtolower(get_parent_class($document)) == 'querylist') {
			$url_title = '?query='.$_GET['query'].'&q='.urlencode(stripslashes(stripslashes($_GET['q'])));
			$prev_title_url = $url_title.'&start='.($_GET['start']-$_GET['results']).'&results='.$_GET['results'];
			$next_title_url = $url_title.'&start='.($_GET['start']+$_GET['results']).'&results='.$_GET['results'];
		}
		// skip the jump menu if this is a music document
		if(($type != 'music' || strtolower(get_parent_class($document)) != 'document') && strtolower(get_parent_class($document)) != 'querylist') {
			// each form needs a different id, so the top jump menu is jump1 and the bottom jump menu is jump2
			$this->pre = "\r\n\t\t"
				.'<table summary=""><tr><td><form id="fjump1" name="fjump1" action="./" method="get">'
				."\r\n\t\t\t".'<select id="jumpto1" name="section" onchange="document.fjump1.submit();">';
			$this->post = "\r\n\t\t"
				.'<table summary=""><tr><td><form id="fjump2" name="fjump2" action="./" method="get">'
				."\r\n\t\t\t".'<select id="jumpto2" name="section" onchange="document.fjump2.submit();">';
			$this->html =
					$menu
					."\r\n\t\t\t".'</select>';
			// if this jump menu is from listing, and sorting variables are not the defaults, send values with it
			if(strtolower(get_parent_class($document)) == 'listing') {
				$this->html .= "\r\n\t\t\t"
					.($document->sortby != 'title' ? '<input type="hidden" name="sortby" value="'.$document->sortby.'" />' : '')
					.($document->order != 'ascending' ? '<input type="hidden" name="order" value="'.$document->order.'" />' : '');
			}
			$this->html .= '<input type="submit" value="Go" />'
				."\r\n\t\t".'</form></td></tr></table>';
		}
		if(is_collection($document->title)) {
			$queryline = add_query();
		}
		// the following links used in the Meta section
		$document->prev_link = ($document->prev_title ? level_url().$type.'/'.$prev_title_url.(strtolower(get_parent_class($document)) == 'querylist' ? '' : '/'.$queryline) : '');
		$document->next_link = ($document->next_title ? level_url().$type.'/'.$next_title_url.(strtolower(get_parent_class($document)) == 'querylist' ? '' : '/'.$queryline) : '');
		$prevline = '<span'.($document->prev_title ? '><a href="'.$document->prev_link.'" title="Previous Page | '.title_tooltip_format($document->prev_title).'">&lt;&lt;</a>' : ' class="disabled">&lt;&lt;');
		$nextline = '<span'.($document->next_title ? '><a href="'.$document->next_link.'" title="Next Page | '.title_tooltip_format($document->next_title).'">&gt;&gt;</a>' : ' class="disabled">&gt;&gt;');

        $url = urlencode(str_replace('?updatecache','',"http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"));
		$reportline = '<a href="'.level_url().'contact/?subject='.urlencode('Error on'.($_GET['q'] ? ' query' : '').': '.$document->title.'/'.str_replace('_',' ',$section)).'&amp;placeholder='.urlencode('[Please tell us about the problem]').'&amp;url='.$url.'" accesskey="9">Report Error</a>';
		$this->html .= '
			<div>'
				.$reportline.'
				| &nbsp;&nbsp;'
				.$prevline
				.'</span>&nbsp;&nbsp;
				| &nbsp;&nbsp;'
				.$nextline
				.'</span>
			</div>';
		$this->pre = "\r\n\t". '<nav aria-label="Page navigation"><ul class="pagination"><li><a href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span>' . $this->pre . $this->html . "\r\n\t".'</li></ul></nav>';
		$this->post = "\r\n\t". '<nav aria-label="Page navigation"><ul class="pagination"><li><a href="#" aria-label="Previous"><span aria-hidden="true">&raquo;</span>' . $this->post . $this->html . "\r\n\t".'</li></ul></nav>';
	}
}

class hAudio extends HTML {
	function hAudio($type,$title,$section) {
		$format = 'mp3';
		
		if($type == 'bible') {
/*			global $db;
			db_connect($db);
			$sql_replace = "REPLACE(title,' ','_')";
			$rBible = mysql_fetch_assoc(mysql_query("SELECT number FROM ". $db['tt3_bible'] ." WHERE '$title' = $sql_replace"));
			$title = str_pad($rBible['number'],2,'0',STR_PAD_LEFT).'_'.$title;
			// closes database connection
			db_disconnect($db); //*/
		} elseif($type == 'music') {
			if( substr_count('/hifi/lofi/',substr($section,0,4)) ) {
				$section = $title.'_'.substr($section,0,2);
				$title = substr($title,0,1).'/'.$title;
			}
		} elseif($type == 'texts' || $type == 'help') {
			$title = substr($title,0,1).'/'.$title;
			foreach (glob(level_library() . 'library/'.$type.'/'.$title.'/*'.$section.'*.'.$format) as $audio_file) {
			}
			// makes sure audio file matches section title with an optional prefix of ##_ for sequencing and optional postfix of @## for bitrate
			if (!preg_match("'/(\d+_)?".$section."(@\d+)?\.'",$audio_file)) return;
		}
		
		$audio_file = ($audio_file ? $audio_file : 'library/'.$type.'/'.$title.'/'.$section.'.'.$format);
        if ($type == 'bible') {
            $audio_file = '//'. NORMALIZED_LOCALHOST . "bible.timelesstruths.org/{$title}.{$section}.mp3";
        }

    $audio_file = str_replace(level_library(), "", $audio_file);

    if (!stream_resolve_include_path($audio_file)) return;

		$audiopath = level_url_webfront().'f_audio.swf?mp3='.urlencode(level_url().$audio_file);
		// gets around Flash bug in certain IE 5.5 versions
		if(substr_count($_SERVER['HTTP_USER_AGENT'],'MSIE 5.5')) {
			$iebug = ' classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"';
		}
		$audiocode = '
			<object id="audio_obj" name="audio_obj" type="application/x-shockwave-flash"'/*.$iebug*/.'
			  data="'.$audiopath.'">
			<param name="movie" value="'.$audiopath.'" />
			<param name="scale" value="exactfit" />
			<p class="warning">You need to upgrade your browser to be able to play this music in the background. Streaming music requires the free Flash plugin (version 6 or greater).</p>
			</object>					
			';		

		$this->html = '<div id="audio" class="panel">
		<p class="first last">'. $audiocode .'</p>
<script>
function doAudio( el ) {
	var a = document.getElementById("audio_obj");
	
	switch( el.value ) {
		case "Play":
			a.TGotoLabel("/", "a_play");
			el.value = "Pause";
			el.blur();
			var el_s = document.getElementById("a_stop");
			el_s.disabled = false;
			break;
		case "Pause":
			a.TGotoLabel("/", "a_pause" );
			el.value = "Play";
			el.blur();
			var el_s = document.getElementById("a_stop");
			el_s.disabled = false;
			break;
		case "Stop":
			a.Rewind();
			el.disabled = true;
			el.blur();
			var el_s = document.getElementById("a_play");
			el_s.value = "Play";
			el_s.disabled = false;
			break;
	}
}
</script>
		<input id="a_play" type="button" value="Play" onclick="doAudio(this);" /> <input id="a_stop" type="button" value="Stop" onclick="doAudio(this);" disabled />
		</div>';
	}
}

class hContent extends HTML {
	var $pre;
	var $post;
	function hContent($type,$title,$section,&$document) {
		if(!is_score()) {
			if($type=='music' && !is_collection($title)) {
				$class_side = ' sidebar';
			}
			$this->pre = "\r\n"
			.'<section><div class="container'.$class_side.'" id="content">
			';
			}	
		if($type=='music' && strtolower(get_parent_class($document)) == 'document') {
			if($score_type = is_score()) {
				// score format, zoom, and note type always set to default for caching
				// THESE VALUES MUST BE EXAMINED FOR CUSTOMIZING AFTER EXTRACTING FROM CACHE (see: /_page.php)
				$format = DEFAULT_M_SCORE_FORMAT;
                $zoom   = DEFAULT_M_SCORE_ZOOM;
                $notes  = DEFAULT_M_SCORE_NOTES;

			  $url_stub = 'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.substr($section,5); // [score]_2 - includes variation suffix, if any
        $document->has_pdf_standard = stream_resolve_include_path($url_stub.'.pdf');
        $document->has_pdf_shaped   = stream_resolve_include_path($url_stub.'+.pdf');
        $document->has_pdf          = ($document->has_pdf_standard || $document->has_pdf_shaped);

        $document->has_sib_standard = stream_resolve_include_path($url_stub.'.sib');
        $document->has_sib_shaped   = stream_resolve_include_path($url_stub.'+.sib');
        $document->has_sib          = ($document->has_sib_standard || $document->has_sib_shaped);

				$this->html .= "\r\n"
				.'<div class="panel score">
					'.($score_type == 'score' ? '' : '<p class="first">To view the music score below, you will need to <a class="red" href="http://www.adobe.com/reader/">get the free Adobe plugin</a>.<!-- See <a class="blue" href="'.level_url().'help/More_Help/Sheet_Music_Scorch_Plugin/">Help &gt; Sheet Music</a>.--></p>').'
					<form id="fscore" name="fscore" action="./" method="get">
						<p class="'.($score_type == 'score' ? 'first' : '').'"><label for="score-format" class="form_item">Format:</label>
                        <select id="score-format" name="format" data-default="'. DEFAULT_M_SCORE_FORMAT .'">
                            <option value="pdf"'. ($format=='pdf' ? ' selected' : '').'>PDF</option>
                            <option value="sib"'. ($format=='sib' ? ' selected' : '').'>Scorch</option>
                        </select>
                        &nbsp;<label for="score-notes" class="form_item">Notes:</label>
                        <select id="score-notes" name="notes" data-default="'. DEFAULT_M_SCORE_NOTES .'">
                            <option value="standard"'. ($notes=='standard' ? ' selected' : '').'>standard</option>
                            <option value="shaped"'.   ($notes=='shaped'   ? ' selected' : '').'>shaped</option>
                        </select>
						&nbsp;<label for="score-zoom" class="form_item">Zoom:</label>																
						<select id="score-zoom" name="zoom" data-default="'. DEFAULT_M_SCORE_ZOOM .'">
							'.($zoom=='custom' ? '<option value="'.(int)($_GET['zoom']).'" selected="selected">custom</option>' : '').'
							<option value="580"'. ($zoom== 580 ? ' selected' : '').'>smaller</option>
							<option value="760"'. ($zoom== 760 ? ' selected' : '').'>standard</option>
							<option value="900"'. ($zoom== 900 ? ' selected' : '').'>larger</option>
							<option value="1200"'.($zoom==1200 ? ' selected' : '').'>largest</option>
						</select>
                        &nbsp;&nbsp;<input id="apply" type="submit" value="Apply" />
					</form>
				</div>
				<div class="panel-toggle help help-score hide">
				    <aside class="toggle">
                        <p class="help-link blue first last"><a class="show" href="'.level_url().'help/More_Help/Sheet_Music/">Get help...</a><a class="hide" title="hide help panel" href="#"></a></p>
                    </aside>
                    <section class="panel">
                        <p class="first last">Sheet music is available in two formats, Scorch and PDF, and you can select your preference above. The Scorch format is interactive, enabling you to transpose and play the music, but to use it you will need to <a class="red" href="http://www.sibelius.com/cgi-bin/download/get.pl?com=sh&amp;prod=scorch">install&nbsp;the&nbsp;Scorch&nbsp;plugin</a>. The PDF format only allows you to view and print the music, but many computers already have a PDF reader in their browser. If not, you can <a class="red" href="http://www.adobe.com/reader/">download&nbsp;Adobe&nbsp;Reader</a>. <a class="blue" href="'.level_url().'help/More_Help/Sheet_Music/">More info...</a></p>
                    </section>
				</div>
				';
			}
		} else {
			if(strtolower(get_parent_class($document)) == 'listing' && ($document->section || $type == 'bible') ) {
				$this->html = '
				<div class="col-xs-12 col-sm-7 col-md-8 col-lg-8 pull-right">
					<div class="sidebar-item">
						<div>
							<p class="pull-right">
								<a href="#">
								<span class="text-info"><i class="glyphicon glyphicon-backward"></i>Prev
								</span>
								</a>
								|
								<a href="#">
									<span class="text-info">Next <i class="glyphicon glyphicon-forward"></i>
									</span>
								</a>
							</p>	
						</div>	
						<div class="list"><div class="list_table">'."\r\n\r\n"
						.$document->xml				
						."\r\n\r\n".'
						</div></div>
				</div></div>
				';
			} elseif( (strtolower(get_parent_class($document)) == 'document' || strtolower(get_parent_class($document)) == 'listing') && $section == '') {
				// if this is Table of Contents, $document->xml has been already set by function called from hNavBar
				$this->html = '
				<div class="col-xs-12 col-sm-7 col-md-8 col-lg-8 pull-right">
					<div class="sidebar-item">
						<div>
							<p class="pull-right">
								<a href="#">
								<span class="text-info"><i class="glyphicon glyphicon-backward"></i>Prev
								</span>
								</a>
								|
								<a href="#">
									<span class="text-info">Next <i class="glyphicon glyphicon-forward"></i>
									</span>
								</a>
							</p>	
						</div>'."\r\n\r\n"
						.$document->xml
						."\r\n\r\n".'
						</div>
					</div>	
				</div>
				';
			} elseif(strtolower(get_class($document)) == 'sitesplit') {
				$this->html = '
				<div class="col-xs-12 col-sm-7 col-md-8 col-lg-8 pull-right">
					<div class="sidebar-item">
						<div>
							<p class="pull-right">
								<a href="#">
								<span class="text-info"><i class="glyphicon glyphicon-backward"></i>Prev
								</span>
								</a>
								|
								<a href="#">
									<span class="text-info">Next <i class="glyphicon glyphicon-forward"></i>
									</span>
								</a>
							</p>	
						</div>
						<div class="site '.$type.'">'."\r\n\r\n"
						.$document->xml
						."\r\n\r\n".'
						</div>
					</div>
				</div>		
				';
                //[2012-10-12]
                if ($type == 'bible' && !$title) {
                    $this->html = '';
                }
			}
		}
		if(!is_score()) {
			$this->post = "\r\n".'</div></section>';
		}
	}
	
	// parsing to come after object initialization to take advantage of the objects variables
	function parse(&$document) {
		// run lyrics parsing only if it's a music page with parsable lyrics
		if(strtolower(get_class($document)) == 'musicdoc' && !is_score()) {
			$section = $document->section;
			global $parsed;
			parse_xml_lyrics($document->xml);
		
			// adds music playing in background
			$mediacode = '';
			$url_title = title_url_format($document->title);
			$id        = (substr($section,5) ? '_'.substr($section,5) : '');
            $path_base = "library/music/{$url_title[0]}/$url_title/$url_title{$id}";
            $url_base  = level_url() . $path_base;
			$m_type    = $section ? substr($section,0,4) : ' ';
			if( substr_count('/midi/lowm/',$m_type) ) {
				$suffix = ($m_type == 'midi' ? '.mid' : '_'.substr($section,0,2).'.wma');
                $url  = $url_base.$suffix;
				if(substr_count($_SERVER['HTTP_USER_AGENT'],'MSIE') || substr_count($_SERVER['HTTP_USER_AGENT'],'Opera')) {
					$mediacode = "\r\n".'<bgsound src="'.$url.'" loop="infinite" />';
				} else {
					$mediacode = "\r\n".'<embed style="height:0;" src="'.$url.'" hidden="true" autostart="true" loop="true" />';
				}
				// because the html is tailored to the browser, the page can't be cached
				global $nocache;
				$nocache = true;
/*CHANGE TO NEW AUDIO PLAYER:*/				
			} elseif( substr_count('/hifi/lofi/',$m_type) ) {
				$quality = '_'.substr($section,0,2);
                if ($quality == '_hi' && stream_resolve_include_path($path_base.'.mp3')) $quality = '';
				// gets the right mp3 to pass to Flash
				$mediapath = level_url_webfront().'f_stream.swf?mp3='.urlencode($url_base.$quality.'.mp3');
				// gets around Flash bug in certain IE 5.5 versions
				if(substr_count($_SERVER['HTTP_USER_AGENT'],'MSIE 5.5')) {
					$iebug = "\r\n\t".'classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"';
					global $nocache;
					$nocache = true;
				}
				$mediacode = '
				<object type="application/x-shockwave-flash"'
				  .$iebug.'
				  data="'.$mediapath.'">
				<param name="movie" value="'.$mediapath.'" />
				<p class="warning">You need to upgrade your browser to be able to play this music in the background. Streaming music requires the free Flash plugin (version 6 or greater).</p>
				</object>					
				';
			}
			if(strlen($section)) {
				$mediaplay = "\r\n\t\t\t\t".'<div class="notes first"><p class="notice first">Now playing '.$document->description.'.</p>'.'<p>It may take a few seconds to begin.</p>'
				.($m_type == 'lowm' ? '<p><b><span class="red">Attention: this Windows Media Audio will usually only play with Internet Explorer on Windows.</span></b></p>' : '')
				.($m_type == 'lowm' ? '' : '<p>Open in <a href="?musicplayer&stop" target="wMusicPlayer" onclick="wMusicPlayer = window.open(this.href, \'wMusicPlayer\',\'location=1,status=1,menubar=0,scrollbar=0,width=300,height=289,resizable=1\'); wMusicPlayer.focus(); return false;" title="opens in new browser window"><span class="sym_music">&#x266b; </span>Music Player</a>.</p>')
				.'</div>'."\r\n\r\n";
			}

			// replaces notes tags with html
            $editable_notes = "data-editable=\"tt3_music|url=$url_title|notes\"";
            // wraps with class
            $notes = "\t\t\t<div class=\"notes\" $editable_notes>$document->notes</div>";
			// adds css markup to first notes paragraph
			$notes = preg_replace("'^([\s\S]*?)<p>'","$1<p class=\"first\">",$notes);
			// adds copyright notice if necessary
			$copynotice = '';
			if($document->copyright['owner'] != '?'
			 && $document->copyright['year'] >= 1923
			 && !$document->copyright['cc']
             && !$document->copyright['tt']) {
				$copynotice = '
				<div class="notes">
					<p class="first">We do not have permission to publish the lyrics:</p>
					<p>Copyright '.$document->copyright['year'].' '.$document->copyright['owner'].'</p>
				</div>
				';
			} elseif($document->copyright['owner'] == '?' || $document->copyright['tt'] == 'uncertain') {
				$copynotice = '
				<div class="notes">
					<p class="first">The copyright status of this song is uncertain. If you have more information, please <a href="'.level_url().'contact/?subject='.urlencode($document->title).'">contact us</a>.</p>
				</div>
				';
			}
            $editable_verses = "data-editable='tt3_music|url=$url_title|verses'";
			$html = "\r\n\t\t\t"
			.'<div class="document lyrics">'
			.$mediaplay
			."\r\n\t\t\t\t"."<div class='verses' $editable_verses>"
			."\r\n\t"
			.$parsed
			.'
			</div>'
			.($document->notes ? "\r\n\t".apply_formatting($notes) : '')
			.$copynotice
			.$mediacode
			."\r\n"
			."\t\t\t".'</div>
			';
			
			// clear $parsed
			$parsed = '';
			return $html;
		}
		// only run parsing if it's a parsable page, i.e., a non-TOC TextDoc or BibleDoc, or query
		if((strtolower(get_parent_class($document)) == 'document' && $document->section != '') || strtolower(get_parent_class($document)) == 'querylist') {
			if(strtolower(get_class($document)) != 'musicdoc' ) {
				// if not displaying the whole document, check to see if this section is an image, which will have no page margins
				if($document->this_pNum !== false && $document->parts[$document->this_pNum]->sections[$document->this_sNum]->type == 'image') {
					$document->xml = preg_replace("'(<image(?:[^>]*)>)[\s\S]*?(</image>)'","$1$2",$document->xml);
					$classline = 'image';
				} else {
					$classline = 'document page';
				}
				if($document->type == 'texts' || $document->type == 'help') {
					$this->encode_xhtml($document->xml);
				}
				global $parsed;
				parse_xml_texts($document->xml);
//				$parsed = $document->xml; // <--ONLY USED FOR DEBUGGING
				if($document->type == 'texts' || $document->type == 'help') {
					apply_firsts($parsed);
				}
				$html = '
				<div class="'.$classline.'">'."\r\n\r\n"
				.$document->warning
				.$parsed
				."\r\n\r\n".'
				<div class="clearer"></div>
				</div>
				';
				// clear $parsed
				$parsed = '';
				return $html;
			}
		}
	}
	
	function encode_xhtml(&$xml) { //encodes xhtml, so it won't be parsed
		$xml = preg_replace("'(<xhtml[^>]*?>)([\s\S]+?)(?=</xhtml>)'e","stripslashes('$1').rawurlencode('$2')",$xml);
	}
}

class hSideTable extends HTML {
	var $pre;
	var $post;
	var $final;
	function hSideTable($type,$section,&$document) {
		$this->pre = '
		<!-- Before it was the table begining, now changed to div-->
		';
		if(strtolower(get_parent_class($document)) == 'document' || (strtolower(get_parent_class($document)) == 'listing' && !$document->section) ) {
			$copyline = format_copyright($document->author,$document->copyright,$document->title,'Text');
			if(strtolower(get_parent_class($document)) == 'listing') {
				$copyline   = format_copyright($document->publisher,$document->copyright,$document->title,'Text');
				$titleline  = format_orphan(apply_formatting($document->pub_title));
				$authoryear = format_author($document->publisher);
				
				//global database variable array passed to database connection from included "f_dbase.php"
				global $db;
				db_connect($db);
				if($type == 'texts') {
					$r = mysql_query("SELECT date,collection,title,url_title FROM ". $db['tt3_texts'] ." WHERE collection LIKE '".$document->collection."%' ORDER BY date DESC");
					$rTexts=mysql_fetch_assoc($r);
					$title_url = $rTexts['url_title'];
					$html = "\r\n"
					."\r\n".'<div class="recent"><table summary="" class="first"><tr><td class="top"><div class="date"><p>'.convert_date($rTexts['date']).'</p></div></td></tr>'
					."\r\n".'<tr><td class="icon"><a href="'.level_url().'texts/'.$title_url.'/" title="'.title_tooltip_format($rTexts['title']).'"><img src="'.level_url().'texts/'.$title_url.'.jpg" alt="'.title_tooltip_format($rTexts['title']).'" width="120" height="120" /></a></td></tr>'
					."\r\n".'<tr><td class="bottom"><div class="title"><p><a href="'.level_url().'texts/'.$title_url.'/">'.$rTexts['title'].'</a></p></div></td></tr></table></div>';
				} elseif($type == 'music') {
                    $sql = "SELECT *
                    FROM tt3_scores s
                    LEFT JOIN tt3_music_collection_indices ci ON (ci.s_title = s.title)
                    LEFT JOIN tt3_music_collections c ON (c.code = ci.c_code)
                    WHERE ". ($document->collection == '_' ? '1' : "c.title_url = '$document->collection'") ."
                    GROUP BY s.pub_id
                    ORDER BY s.pub_date DESC
                    LIMIT 1";
                    $r = mysql_query($sql);
					$arrKeysigs = array('C'=>'C','Am'=>'C','G'=>'G','Em'=>'G','D'=>'D','Bm'=>'D','A'=>'A','Fsm'=>'A',
						'E'=>'E','Csm'=>'E','B'=>'B','Gsm'=>'B','Fs'=>'Fs','Dsm'=>'Fs','Cs'=>'Cs','Asm'=>'Cs',
						'F'=>'F','Dm'=>'F','Bb'=>'Bb','Gm'=>'Bb','Eb'=>'Eb','Cm'=>'Eb',
						'Ab'=>'Ab','Fm'=>'Ab','Db'=>'Db','Bbm'=>'Db','Gb'=>'Gb','Ebm'=>'Gb','Cb'=>'Cb','Abm'=>'Cb');

					$rM=mysql_fetch_assoc($r);

                    $sql = "SELECT m.title FROM tt3_scores s LEFT JOIN tt3_music m ON (m.url = s.m_url) WHERE s.pub_id = {$rM['pub_id']}";
					$arrM = mysql_fetch_assoc(mysql_query($sql));
					$title_url = title_url_format($arrM['title']);
                    $arrM['title'] = apply_formatting($arrM['title']);
					
					$score_icon = $arrKeysigs[str_replace('#','s',preg_replace("',.+'",'',$rM['keytone']))];
					
					$html = "\r\n"
					."\r\n".'<p>'.convert_date($rM['sib']).'</p>'
					."\r\n".'<p><a href="'.level_url().'music/'.$title_url.'/score'.($rM['s_id'] ? '_'.$rM['s_id'] : '').'/"><img src="'.level_url_webfront().'score_icon/keysig_'.$score_icon.'.gif" alt="'.title_tooltip_format($arrM['title']).'" width="120" height="60" /></a></p>'
					."\r\n".'<p><a href="'.level_url().'music/'.$title_url.'/">'.$arrM['title'].'</a></p>';
				}
				// disconnect from database
				db_disconnect($db);
	
				$this->html = '
				<div class="col-xs-12 col-sm-5 col-md-4 col-lg-4">
					<div class="row sidebar-item">
						<h2 class="post-title-blue">
							<span>$titleline</span>
						</h2>
						<!--h1 class="first"></h1-->'

						.($authoryear ? "
						<p class='author'>$authoryear</p>
						<p class='pubstatus last'>$copyline</p><hr>"
						: '')
						.($document->summary ? '
						<div class="well">
							<p>'.apply_formatting($document->summary).'</p>
						</div>'
						: '<hr />').'
					</div>
					<div class="row sidebar-item">
						<h2 class="post-title-blue">
							<span>Most Recent:</span>
						</h2>
							'.$html.'
					</div>
				</div>	
				';
			} elseif($type=='music') {
				// assigns subject margins depending on whether scripture reference will follow
				$subject_class = ($document->scripture ? 'first' : 'first last');
				$titleline  = format_orphan(apply_formatting($document->title));
				$authoryear = format_author($document->author,'author');
                $url_title  = title_url_format($document->title);

                $url_path   = level_url()."music/$url_title";
                $file_path  = 'library/music/'.$url_title[0].'/'.$url_title.'/'.$url_title;
                // provide link to other languages PDF
                $langs = array(
                    'de' => 'Deutsch (German)',
                    'ru' => 'Pусский (Russian)',
                    'es' => 'Español (Spanish)'
                );
                $html_langs = '';
                foreach ($langs as $lang => $language) {
                    $file_pdf = "$file_path@$lang.pdf";
                    if (stream_resolve_include_path($file_pdf)) {
                        $html_langs .= "
                            <p><a class='source' href='$url_path@$lang.pdf' title='Download PDF sheet music (". format_filesize($file_pdf).")'><img src='". level_url_webfront() ."icon_pdf.gif' alt='Show PDF' />.pdf</a> $language</p>";
                    }
                }
                
                $editable_author    = "data-editable='tt3_music|url=$url_title|author'";
                $editable_subject   = "data-editable='tt3_music|url=$url_title|subject'";
                $editable_scripture = "data-editable='tt3_music|url=$url_title|scripture'";

				$this->html = "
				<div class='col-xs-12 col-sm-5 col-md-4 col-lg-4'>
					<div class='row sidebar-item'>
						<h2 class='post-title-blue'>
							<span>$titleline</span>
						</h2>
					<p class='author' $editable_author>$authoryear</p>
					<p class='pubstatus last'>$copyline</p>
					<hr />
                    <p class='$subject_class' $editable_subject>Subject". (count($document->subjects) > 1 ? 's' : '') .":";
                foreach ($document->subjects as $i => $subject) {
                    $this->html .= ($i > 0 ? ',' : '') ."
                        <a href='". level_url() ."$type/_/". title_url_format($subject) ."/?sortby=subject'>". str_replace(' ','&nbsp',$subject) ."</a>";
                }
                $this->html .= "</p>"
                    .($document->scripture ? "\r\n\t\t\t\t\t"."<p class='green last' $editable_scripture>Scripture: <a href='http://". NORMALIZED_LOCALHOST ."bible.timelesstruths.org/$document->scripture'>$document->scripture</a></p></div>" : '</div>')
                    .$this->list_media($section,$document)
                    .($html_langs ? '
                        <div class="row sidebar-item">
						<h2 class="post-title-blue">
							<span>Other Languages</span>
						</h2>
                        $html_langs
                        </div>'
                        : '') .'
                    </div>
				';
			} else {
				if($type=='help') {
					$root = 'www';
				} else {
					$root = 'library';
				}
				$subject_class = ($type=='help' || $type=='texts' ? 'first' : 'first last');
				$titleline = format_orphan(apply_formatting($document->title));
				$url_title = $document->url_title;
				
                $pubstatus = '';
                $authoryear = format_author($document->author,'');
                if($document->type == 'texts') {
                    $pub_date   = date("j F Y",strtotime($document->date));
                    $pubstatus .= "this edition published $pub_date";
                    //$publine    = '<p>Published: '.date("Y",strtotime($document->date)).'</p>';
                    $authoryear = format_author($document->author,'author');
                }
				if($document->type != 'bible') {
				    $file = stream_resolve_include_path($root.'/'.$type.'/'.$url_title[0].'/'.$url_title.'/'.$url_title.'.xml');
				    $edit_date = date("j F Y",filemtime($file));
				    $pubstatus .= ($edit_date != $pub_date ? ($pubstatus ? '<br />' : '') ."last edited on $edit_date" : '');
					$imageline = '<p><img src="'.level_url().$type.'/'.$url_title.'.jpg" alt="'.$document->title.'" width="120" height="120" /></p>';
					//$editline = '<p class="last">Last edited: '.date("F j, Y",filemtime($root.'/'.$type.'/'.$url_title[0].'/'.$url_title.'/'.$url_title.'.xml')).'</p>';
				}
				$subjectline = ($type != 'help' ? '<a href="'.level_url().$type.'/_/'.title_url_format($document->subject).'/?sortby=subject">'.$document->subject.'</a>' : $document->subject);
				$anchorline = ''; // [2013-12-01 ignore] '?anchor='.$document->excerpt_anchor.'#'.str_replace("%27","'",rawurlencode($document->excerpt_anchor));
				$excerptline = '<a href="'.level_url().$type.'/'.$url_title.'/'.title_url_format($type == 'bible' ? preg_replace("/\D/",'',$document->excerpted) : $document->excerpted).'/'.$anchorline.'">'.apply_formatting($document->excerpted).'</a>';

				// provide link to other formats
				$file_path   = 'library/texts/'.$url_title[0].'/'.$url_title.'/'.$url_title;
                $file_epub   = $file_path.'.epub';
				$file_kindle = $file_path.'.mobi';
                $file_pdf    = $file_path.'.pdf';
                $file_m3u    = $file_path.'.m3u';
				$url_path    = level_url()."texts/$url_title";
				$has_other   = stream_resolve_include_path($file_epub) || stream_resolve_include_path($file_pdf) || stream_resolve_include_path($file_kindle) || stream_resolve_include_path($file_m3u);
				
				$onclick = 'onclick="return trackLink(this, \'Downloads\', this.href.replace(/^.+\//,\'\'));"';

				$this->html = "
				<div class='col-xs-12 col-sm-5 col-md-4 col-lg-4'>
					<div class='row sidebar-item'>
						<h2 class='post-title-blue'>
							<span>$document->title</span>
						</h2>
					$imageline
					<p class='author'>$authoryear</p>
					<p class='pubstatus last'>$pubstatus<br />$copyline</p>
					<hr />
                    <p class='$subject_class'>Subject". (count($document->subjects) > 1 ? 's' : '') .":";
                foreach ($document->subjects as $i => $subject) {
                    $this->html .= ($i > 0 ? ',' : '') ."
                        <a href='". level_url() ."$type/_/". title_url_format($subject) ."/?sortby=subject'>". str_replace(' ','&nbsp',$subject) ."</a>";
                }
                $this->html .= "</p></div>\r\n"
					//.$publine
					//.$editline
					.(strlen($document->excerpt)
						? "\r\n"
						  .'<div class="row sidebar-item">
						  		<div class="well">
						  			<p>'.apply_formatting($document->excerpt).'</p>'
						  			.($document->excerpted ? "\r\n".'<p class="excerpted"><i>from</i> '.$excerptline.'</p>' : '')
						  	.'</div></div>'
						: '')
					.($has_other
						? "\r\n"
						  .'<fieldset>
						  <p class="help"><i>This document in other formats:</i></p>'
                          .(stream_resolve_include_path($file_epub)   ? '<p><a href="'.$url_path.'.epub" '.$onclick.' title="Download EPUB file ('.format_filesize($file_epub).')"><img src="'.level_url_webfront().'icon_epub.png" alt="Open EPUB" /></a> EPUB [<a class="source" href="'.$url_path.'.epub" '.$onclick.' title="Download EPUB file ('.format_filesize($file_epub).')">.epub</a>]</p>' : '')
						              .(stream_resolve_include_path($file_kindle) ? '<p><a href="'.$url_path.'.mobi" '.$onclick.' title="Download Mobipocket file ('.format_filesize($file_kindle).')"><img src="'.level_url_webfront().'icon_mobi.gif" alt="Open Mobipocket" /></a> Kindle [<a class="source" href="'.$url_path.'.mobi" '.$onclick.' title="Download Mobipocket file ('.format_filesize($file_kindle).')">.mobi</a>]</p>' : '')
                          .(stream_resolve_include_path($file_pdf)    ? '<p><a href="'.$url_path.'.pdf"  '.$onclick.' title="Download PDF file ('.format_filesize($file_pdf).')"><img src="'.level_url_webfront().'icon_pdf.gif" alt="Open PDF" /></a> Adobe PDF [<a class="source" href="'.$url_path.'.pdf" '.$onclick.' title="Download PDF file ('.format_filesize($file_pdf).')">.pdf</a>]</p>' : '')
                          .(stream_resolve_include_path($file_m3u)    ? '<p><a href="'.$url_path.'.m3u"  '.$onclick.' title="Download M3U file"><img src="'.level_url_webfront().'icon_m3u.gif" alt="Open M3U" /></a> MP3 Playlist [<a class="source" href="'.$url_path.'.m3u" '.$onclick.' title="Download M3U file">.m3u</a>]</p>' : '')
						  .'</fieldset>'
						: '')
					."\r\n".'</div>
				';
			}
		} else { // class is SiteSplit
			if($type == 'welcome') { $type = ''; } // DEBUGGING ONLY
			if($type == 'texts' || $type == '') { // get latest texts
				//global database variable array passed to database connection from included "f_dbase.php"
				global $db;//, $rewrite_title_url;

				db_connect($db);
				$results = mysql_query("SELECT date,collection,title,url_title FROM ". $db['tt3_texts'] ." ORDER BY date DESC");
				// disconnect from database
				db_disconnect($db);
				// grab 3 latest texts
				for($i=0; $i < ($type == '' ? 1 : 3); $i++) {
					$rTexts=mysql_fetch_assoc($results);
					$url_title = $rTexts['url_title'];

					// key has 'z' prefix to precede music in reverse order
					$html['z'.$rTexts['date'].strtolower(str_replace('_','',$url_title))] = "\r\n"
					."\r\n".'<div class="well"><p>'.convert_date($rTexts['date']).'</p>'
					."\r\n".'<p><a href="'.level_url().'texts/'.$url_title.'/" title="'.title_tooltip_format($rTexts['title']).'"><img src="'.level_url().'texts/'.$url_title.'.jpg" alt="'.title_tooltip_format($rTexts['title']).'" width="120" height="120" /></a><p>'
					."\r\n".'<p><a href="'.level_url().'texts/'.$url_title.'/">'.$rTexts['title'].'</a></p></div>';
				}
                $html['x'] = "\r\n"
                    ."\r\n"."<p><br /><a href='".level_url()."texts/_/?sortby=published'>More...</a></p>";

				$titleline = 'Recent ';
			}
			if($type == 'music' || $type == '') { // get latest scores
				//global database variable array passed to database connection from included "f_dbase.php"
				global $db;
				db_connect($db);
                $r = mysql_query("SELECT * FROM tt3_scores ORDER BY pub_date DESC LIMIT 3");
				$arrKeysigs = array('C'=>'C','Am'=>'C','G'=>'G','Em'=>'G','D'=>'D','Bm'=>'D','A'=>'A','Fsm'=>'A',
					'E'=>'E','Csm'=>'E','B'=>'B','Gsm'=>'B','Fs'=>'Fs','Dsm'=>'Fs','Cs'=>'Cs','Asm'=>'Cs',
					'F'=>'F','Dm'=>'F','Bb'=>'Bb','Gm'=>'Bb','Eb'=>'Eb','Cm'=>'Eb',
					'Ab'=>'Ab','Fm'=>'Ab','Db'=>'Db','Bbm'=>'Db','Gb'=>'Gb','Ebm'=>'Gb','Cb'=>'Cb','Abm'=>'Cb');
				// grab 3 latest scores
				for($i=0; $i < ($type == '' ? 1 : 3); $i++) {
					$rM=mysql_fetch_assoc($r);
                    $sql = "SELECT m.title FROM tt3_scores s LEFT JOIN tt3_music m ON (m.url = s.m_url) WHERE s.pub_id = {$rM['pub_id']}";
                    $arrM = mysql_fetch_assoc(mysql_query($sql));
					$title_url = title_url_format($arrM['title']);
                    $arrM['title'] = apply_formatting($arrM['title']);
					
					$score_icon = $arrKeysigs[str_replace('#','s',preg_replace("',.+'",'',$rM['keytone']))];
					
					// key has 'y' prefix to follow texts in reverse order
					$html['y'.$rM['sib'].strtolower(str_replace('_','',$title_url))] = "\r\n"
					."\r\n".'<div class="well"><p>'.convert_date($rM['sib']).'</p>'
					."\r\n".'<p><a href="'.level_url().'music/'.$title_url.'/score'.($rM['s_id'] ? '_'.$rM['s_id'] : '').'/" title="'.title_tooltip_format($rM['title']).'"><img src="'.level_url_webfront().'score_icon/keysig_'.$score_icon.'.gif" alt="'.title_tooltip_format($rM['title']).'" width="120" height="60" /></a></p>'
					."\r\n".'<p><a href="'.level_url().'music/'.$title_url.'/">'.$arrM['title'].'</a></p></div>';
				}
                $html['x'] = "\r\n"
                    ."\r\n"."<p><br /><a href='".level_url()."music/_/?sortby=published'>More...</a></p>";

				$titleline = 'Recent ';
				// disconnect from database
				db_disconnect($db);
			}
			if($type == '') {
			    $html['x'] = '';
			    $titleline = 'Recent Additions';
            }
			// sets the content of the side panel
			
			$this->html = "\r\n"
			.'<div class="col-xs-12 col-sm-5 col-md-4 col-lg-4">'.($type == '' || $type == 'texts' || $type == 'music' ? '<div class="row sidebar-item">' : ' <div class="row sidebar-item">')
			."\r\n\t".'<h2 class="post-title-blue"><span>'.$titleline.ucfirst($type).'</span></h2>';
			if(is_array($html)) {
				krsort($html);
				foreach($html as $content) {
					$this->html .= $content;
				}
				// only Music uses this currently
				$this->html .= $document->sidebar;
			} else {
				// $document->sidebar set by the files called from classes_site.php
				$this->html .= $document->sidebar;
			}
			$this->html .= "\r\n"
			.($type == '' || $type == 'texts' || $type == 'music' ? '</div></div><!--Musics sidebar -->' : '').'</div></div><!--Musics sidebar -->';
		}
		$this->post = '
		<!-- Coments to check if post is working -->
		';
		$this->final = '
		<!-- End of sidebar div before it was the table end, now replaced with div-->
		';
        //[2012-10-12] hidden
        if ($type == 'bible' && get_class($document) == 'SiteSplit') {
            $this->pre = 
            $this->html =
            $this->post =
            $this->final = '';
        }
	}
	
	function list_media($section,&$document) {
		$html = '';
		
		// if no score is available, skip score data
		if(!$document->score[0]->sib) {
			if($document->copyright['owner'] != '?'
			 && $document->copyright['year'] >= 1923
			 && !$document->copyright['cc']) {
			 	return; // cannot provide sheet music for copyrighted songs
			 } else {
				global $uacrawler, $nocache;
				if($uacrawler) { // CRAWLERS DON'T NEED TO REQUEST SHEET MUSIC
					$nocache = true;
				} else {
					$subjectline = urlencode('Request: '.$document->title);
					$commentsline = urlencode('I would like to have the sheet music for this song added to your priority list. Thank you.');
					$html .= "\r\n\t\t\t\t".'<fieldset>'
					.'<p><a class="blue" href="'.level_url().'contact/?subject='.$subjectline.'&amp;comments='.$commentsline.'">Request sheet music</a>'
					."\r\n\t\t\t\t".'</fieldset>
					';
				}
			}
			return $html;
		}

		foreach($document->score as $s_id => $score) {
			$title = title_url_format($document->title);
			$id = $score->id;
			$url = level_url().'music/'.$title.'/';
			$file = 'library/music/'.$title[0].'/'.$title.'/'.$title.($id ? '_'.$id : '');
			foreach(array('score'=>'.sib','pdf'=>'.pdf','midi'=>'.mid','mp3'=>'.mp3','hifi_old'=>'_hi.mp3','lofi'=>'_lo.mp3','lowm'=>'_lo.wma','xml'=>'.xml') as $media => $media_file) {
				${'url_' .$media} = $url .($media == 'hifi_old' ? 'hifi' : $media).($id ? '_'.$id : '').'/';
				${'file_'.$media} = $file.$media_file;
			}
			
            // provide link to score
            $score_line = '<p><a href="'.$url_score.'" title="show score"><img src="'.level_url_webfront().'link_score.gif" alt="show score" /> view</a>';
            $source_sib = level_url().$file_score;
            $score_line .= ' sheet music';
            // provide link to PDF score
            if(stream_resolve_include_path($file_pdf)) {
                $source_pdf = level_url().$file_pdf;
                $score_line .= ' [<a class="source" href="'.$source_pdf.'" title="download PDF source file">.pdf</a>]';
            }
            $score_line .= ' [<a class="source" href="'.$source_sib.'" title="download Sibelius source file">.sib</a>]</p>';
			// provide link to midi-in-background if not playing, or link to plain lyrics if playing
			$midi_line = '';
			if(stream_resolve_include_path($file_midi)) {
				if($section=='midi'.($id ? '_'.$id : '')) {
					$midi_line = '<p><img src="'.level_url_webfront().'linked_midi.gif" alt="playing midi" /> <a href="'.$url.'">stop</a>';
				} else {
					$midi_line = '<p><a href="'.$url_midi.'" title="play midi"><img src="'.level_url_webfront().'link_midi.gif" alt="play midi" /> play</a>';
				}
				$source_midi = level_url().$file_midi;
				$midi_line .= ' midi [<a class="source" href="'.$source_midi.'" title="download midi source file">.mid</a>]</p>';
			}
			// provide link to mp3
			$mp3_line = '';
			if(stream_resolve_include_path($file_mp3)) { // hifi is a decimal time value, so '0.0' must be converted to (int)
                // filesize comparisons relative from calling file, which is _urlhandler, already "leveled", at root of site
                $source_mp3 = $file_mp3;
                $mp3_line = '<p><a class="use-player" href="'.level_url().$file_mp3.'" title="play audio"><img src="'.level_url_webfront().'link_hifi.gif" alt="play audio" /></a>'
				          . ' audio recording [<a class="source" href="'.level_url().$source_mp3.'" title="download mp3 file ('.format_filesize($source_mp3).')">.mp3</a>]</p>';
			}
            // provide link to hifi-in-background if not playing, or link to plain lyrics if playing
            $hifi_old_line = '';
            if(stream_resolve_include_path($file_hifi_old)) { // hifi is a decimal time value, so '0.0' must be converted to (int)
                if($section=='hifi'.($id ? '_'.$id : '')) {
                    $hifi_old_line = '<p><img src="'.level_url_webfront().'linked_hifi.gif" alt="Playing HiFi MP3" /> <a href="'.$url.'">stop</a>';
                } else {
                    $hifi_old_line = '<p><a href="'.$url_hifi_old.'" title="Play HiFi MP3"><img src="'.level_url_webfront().'link_hifi.gif" alt="Play HiFi MP3" /> play</a>';
                }
                // filesize comparisons relative from calling file, which is _urlhandler, already "leveled", at root of site
                $source_hifi_old = $file_hifi_old;
                $hifi_old_line .= ' high quality mp3 [<a class="source" href="'.level_url().$source_hifi_old.'" title="Download HiFi mp3 source file ('.format_filesize($source_hifi_old).')">.mp3</a>]</p>';
            }
			// provide link to lofi-in-background if not playing, or link to plain lyrics if playing
			$lofi_line = '';
			if(stream_resolve_include_path($file_lofi)) {
				if($section=='lofi'.($id ? '_'.$id : '')) {
					$lofi_line = '<p><img src="'.level_url_webfront().'linked_lofi.gif" alt="Playing LoFi MP3" /> <a href="'.$url.'">stop</a>';
				} else {
                    $lofi_line = '<p><a href="'.$url_lofi.'" title="Play LoFi MP3"><img src="'.level_url_webfront().'link_lofi.gif" alt="Play LoFi MP3" /> play</a>';
				}
				$source_lofi = $file_lofi;
				$lofi_line .= ' dial-up quality mp3 [<a class="source" href="'.level_url().$source_lofi.'" title="Download LoFi mp3 source file ('.format_filesize($source_lofi).')">.mp3</a>]</p>';
			}
			// provide link to lofi-WMA-in-background if not playing, or link to plain lyrics if playing
			$lowm_line = '';
			if(stream_resolve_include_path($file_lowm)) {
				if($section=='lowm'.($id ? '_'.$id : '')) {
					$lowm_line = '<p><img src="'.level_url_webfront().'linked_lowm.gif" alt="Playing LoFi Windows Media Audio" /> <a href="'.$url.'">stop</a>';
				} else {
                    $lowm_line = '<p><a href="'.$url_lowm.'" title="Play LoFi Windows Media Audio"><img src="'.level_url_webfront().'link_lowm.gif" alt="Play LoFi Windows Media Audio" /> play</a>';
				}
				$source_lowm = $file_lowm;
				$lowm_line .= ' dial-up quality <span title="Windows Media Audio">WMA</span> [<a class="source" href="'.level_url().$source_lowm.'" title="Download LoFi WMA source file ('.format_filesize($source_lowm).')">.wma</a>]</p>';
			}
			// provide link to MusicXML
			$xml_line = '';
			if(stream_resolve_include_path($file_xml)) {
				$source_xml = $file_xml;
				$xml_line .= ' <span style="font-weight:normal;">[<a class="source" href="'.level_url().$source_xml.'" title="Download MusicXML file">.xml</a>]</span>';
			}
			
			// if title is long, and the last word is short, "attach" it to the next one so it won't hang by itself
			$titleline = preg_replace("'^(.{25,}?)(?: (\S{1,4}))$'","$1&nbsp;$2",$score->title);
			preg_match("'^(\d*)(\.)(\d*)(.*)$'",$score->meter,$mm);
			$keyline = preg_replace(array("'b'","'s'"),array("&#9837;","&#9839;"), $score->key); // converts flats (b) and sharps (s) notation to HTML characters
			if(substr_count($keyline,'&#')) {
				$keyline = '<span title="'.preg_replace(array("'&#9837;'","'&#9839;'"),array(" flat"," sharp"),$keyline).'">'.$keyline.'</span>'; // for browsers that don't render flats and sharps notation, spell out
			}

            $editable_author = "data-editable='tt3_scores|title=". urlencode($score->title) ."|author'";
			
			$html .= "\r\n\t\t\t\t"
			.'<fieldset class="tuneinfo">'
			.($score->title ? "\r\n\t\t\t\t".'<p class="scoretitle">'.$titleline.$xml_line.'</p>' : '')
			."\r\n\t\t\t\t"."<p $editable_author>". format_author($score->author,'composer') .'</p>'
			."\r\n\t\t\t\t".'<p class="pubstatus'.($score->key ? ' last' : '').'">'.format_copyright($score->author,$score->copyright,$score->title,'Sound').'</p>'
			.($score->key   ? "\r\n\t\t\t\t<hr />\r\n\t\t\t\t".'<p class="first">Key: <a href="'.level_url().'music/_/'.$score->key.'/?sortby=key">'.$keyline.'</a></p>' : '')
			.($score->meter ? "\r\n\t\t\t\t".'<p>Meter: <a href="'.level_url().'music/_/'.$mm[1].$mm[3].'/?sortby=meter">'.$mm[1].$mm[2].$mm[3].'</a>'.$mm[4].'</p>' : '')
			.($score->sib   ?
				"\r\n\t\t\t\t<hr />\r\n\t\t\t\t"
				.'<p class="help"><a class="blue" href="'.level_url().'help/More_Help/Music_Formats/">Learn about music formats...</a></p>'
                ."\r\n\t\t\t\t".$score_line
				."\r\n\t\t\t\t".$midi_line
				."\r\n\t\t\t\t".$mp3_line
//              ."\r\n\t\t\t\t".$hifi_old_line
//				."\r\n\t\t\t\t".$lofi_line
//				."\r\n\t\t\t\t".$lowm_line
				: '')
			."\r\n\t\t\t\t".'</fieldset>
			';
		}
		return $html;
	}
}

class hScore extends HTML {
	function hScore($title,$section,&$document) {
		$width = DEFAULT_M_SCORE_ZOOM;
		$height = $width * 1.3 + 30; // letter size ratio -- offset tall enough to eliminate scrollbars on Chrome
		if (is_score() == 'score') {
			// file note type and zoom width always set to default for caching
			// THESE VALUES MUST BE EXAMINED FOR CUSTOMIZING AFTER EXTRACTING FROM CACHE (in _page.php)
			$url_stub = level_url().'library/music/'.substr($title,0,1).'/'.$title.'/'.$title.substr($section,5); // [score]_2 - includes variation, if any
            $src = $url_stub.'.'.DEFAULT_M_SCORE_FORMAT;
            // custom object code for valid XHTML
            $this->html = '<!--#score:start-->
            <!--#score [data-src="'.$src.'"]-->
            <iframe id="s_pdf" frameborder="0" src="'.$src.'">
                <div class="error-message">
                <p>Sorry, your browser does not support iframes, which are used to display the PDF score.</p>
                </div>
            </iframe>
			<!--
            <object id="s_sib"
                  type="application/x-sibelius-score"
                  data="'.$src.'">
                <param name="src" value="'.$src.'" />
                <param name="scorch_minimum_version" value="3000" />
                <param name="scorch_shrink_limit" value="100" />
                <param name="shrinkwindow" value="0" />
                <div class="error-message">
                <p>To view, play, and print the sheet music, you need to install a plugin:</p>
                <ul><li><a class="red" href="http://www.sibelius.com/scorch/">Get the free Scorch plugin</a></li></ul>
                <p><b>Important Notice for Chrome:</b> support for Scorch (and similar NPAPI type plugins) has been removed since version 45 (September 2015).</p>
                <p>For more help, see <a class="blue" href="'.level_url().'help/More_Help/Sheet_Music/">Sheet Music</a>.</p>
                </div>
            </object>
            -->
            <!--#score:end-->
			';
		}
		// insert within wrapper
        $this->html = '
        <div id="score" style="width:'.$width.'px; height:'.$height.'px;"
            data-src-pdf-standard="'.($document->has_pdf_standard ? $url_stub.'.pdf'  : '').'"
            data-src-pdf-shaped="'.  ($document->has_pdf_shaped   ? $url_stub.'+.pdf' : '').'"
            data-src-sib-standard="'.($document->has_sib_standard ? $url_stub.'.sib'  : '').'"
            data-src-sib-shaped="'.  ($document->has_sib_shaped   ? $url_stub.'+.sib' : '').'">
        <div class="wrapper">
            '.$this->html.'
        </div>
        </div>
        <script>TT.Music.initScore();</script>
        ';
	}
}

class hSource extends HTML {
	function hSource($source) {
		if(!count($source)) return; // return if no sources given

        global $url_bits;
		$editable_source = "data-editable='tt3_music|url={$url_bits[1]}|source'";
        		
		$this->html = "
		<div id='source' class='panel'><ul $editable_source>
		Source". (count($source) > 1 ? 's' : '') .':';

		foreach($source as $s) {
			preg_match_all("'<publisher(?: year=\"(.+?)\")?(?: work=\"(.+?)\")?>(.*?)</publisher>'",$s->s_publisher,$pm);
			$pub_line = '';
			$year_line = '';
			foreach($pm[3] as $i => $publisher) {
				$pub_line .= ($pub_line && $publisher ? 'and ' : '') . $publisher;
				$year_line .= ($pm[1][$i] ? ', ' : '') . ($pm[2][$i] ? $pm[2][$i].' ' : '') . preg_replace(array("'b'","'c'"),array('<i>before</i> ','<i>circa</i> '),$pm[1][$i]);
			}
			if($pub_line) { $pub_line .= ', '; }
			$title_line = ($s->title ? '<i>'.$s->title.'</i>' : '');
			if($s->s_url) { $title_line = $s->title . ($s->id ? '' : ' ('.$s->s_url.')'); } // if source is a website, add site url if ID url is not present
			$id_line = ($s->id ? ' ('.$s->id.')' : '');
			$notes = ($s->notes ? ($title_line ? '; ' : '') . $s->notes : '');
			$this->html .= "\r\n"."<li>{$pub_line}{$title_line}{$year_line}{$id_line}{$notes}</li>";
		}
		
		$this->html .= '
		</ul></div>
		';
	}
}

// generates jump-menu list and Table of Contents
function doc_outline(&$document, $type, $toc = false) {
	// returns jump menu code, changes toc xml passed by reference
	$xml = '';

	// all documents have at least a Whole Document view and Table of Contents
	$menu =
	"\r\n\t".'<option value="_"'.($document->section == '_' ? ' selected="selected"' : '').'>Whole Document</option>'
	."\r\n\t".'<option value=""'.($toc ? ' selected="selected"' : '').'>Table of Contents</option>';
	
	// shorter alias
	$parts = &$document->parts;
	for($i=0; $i < count($parts); $i++) {
		// adds menu option group whether or not part toc data is present
		$menu .= "\r\n\t".'<optgroup'.($parts[$i]->type == 'preface' ? ' class="preface"' : '').' label="'.$parts[$i]->toc->title.'">';
		if($parts[$i]->type == 'break') {
			// adds part "breaks" 
			$xml .= '<hr />'."\r\n";
		}
		if($parts[$i]->toc->xml && $parts[$i]->type != 'list') {
			$toc_xml = '<xml>'.$parts[$i]->toc->xml.'</xml>';
			parse_xml_texts($toc_xml);
			global $parsed;
			apply_firsts($parsed);
			$toc_xml = $parsed;
			$xml .= $toc_xml."\r\n";
			// clear $parsed
			$parsed = '';
		}

		// if list is prefatory, enclose in div
		$xml .= ($parts[$i]->type == 'preface' ? '<div class="preface">'."\r\n" : '');
		// if list is a list, make collapsible
// enclose in div, and insert list "tab" ul
		if($parts[$i]->type == 'list') {
			$xml .= '<div class="preface list collapsible" id="list'.$i.'">'."\r\n"
				.'<ul class="toggle"><li><a href="#list'.$i.'">'.$parts[$i]->toc->title.'</a></li></ul>'."\r\n"
				.'<ul>'."\r\n";
		} else {
			$xml .= '<ul>'."\r\n";
		}

		for($j=0; $j < count($parts[$i]->sections); $j++) {
			// if section is an "image" (image only) or "illustration" (image mostly, with some text, e.g. caption), add image icon
			if($parts[$i]->sections[$j]->type && substr_count('image,illustration',$parts[$i]->sections[$j]->type)) {
				$menu_prefix = '&#8864; ';
				$item_class = ' class="i"';
			} else { // clear settings
				$menu_prefix = '';
				$item_class = '';
			}
			$item_start = '<li'.$item_class.'>';
			if($document->type == 'bible') {
				$working_title = $parts[$i]->sections[$j]->id;
				$working_prefix = ($document->title == 'Psalms' ? 'Psalm ' : 'Chapter ');
			} else {
				$working_title = $parts[$i]->sections[$j]->title;
			}
			$url_st = title_url_format($working_title);
			$url_t = $document->url_title;
			// gets current section title (if not Table of Contents or Whole Document) for testing if it matches current jump menu option
			if(!$toc && $document->section != '_') {
				if($document->type == 'bible') {
					$section = $parts[$document->this_pNum]->sections[$document->this_sNum]->id;
				} else {
					$section = $parts[$document->this_pNum]->sections[$document->this_sNum]->title;
				}
			}
			// if section type is music and score is available, add link
			if($parts[$i]->sections[$j]->type == 'music') {
				if(stream_resolve_include_path('library/music/'.substr($url_st,0,1).'/'.$url_st.'/'.$url_st.'.sib')) {
					$item_start = '<li class="score_link"><a class="score_link" href="'.level_url().'music/'.$url_st.'/score/" title="View Score Sheet Music">'
						.'<img src="'.level_url_webfront().'link_score.gif" alt="View Score Sheet Music" /></a>';
				}
				$item_link = '<a href="'.level_url().'music/'.$url_st.'/">';
				// adds to jump menu options, formatting specially to instruct _urlhandler.php to send directly to music title, selecting if it matches current title
				$menu .= "\r\n\t". '<option value="music/'.$url_st.'/"'.(title_url_format($section)==$url_st ? ' selected="selected"' : '').'>&#9835; '.$working_prefix.apply_formatting($working_title).'</option>';
			} else {
				// non-music sections must exactly match url: $url_bitz[2]
				global $url_bits;
				$item_link = '<a href="'.level_url().$type.'/'.$url_t.'/'.$url_st.'/">';
				// adds to jump menu options, selecting if it matches current title
				$menu .= "\r\n\t". '<option value="'.$url_st.'"'.($url_bits[2]==$url_st ? ' selected="selected"' : '').'>'.$menu_prefix.$working_prefix.apply_formatting($working_title).'</option>';
			}
			$subtitle = $parts[$i]->sections[$j]->subtitle;
			$blurb = $parts[$i]->sections[$j]->blurb;
			// adds to toc xml
			$xml .= "\t".$item_start.$item_link
				.apply_formatting($working_prefix.$working_title).'</a>'
				.($subtitle ? '<br />'.$subtitle : '')
				.($blurb ? '<br /><span class="blurb">'.apply_formatting($blurb).'</span>' : '')
				.'</li>'."\r\n";
			// if last section in part, close jump menu group and Table of Contents list
			if($j == count($parts[$i]->sections) - 1) {
				$menu .= "\r\n\t".'</optgroup>';
				$xml .= '</ul>'."\r\n";
				// if list is prefatory close div
				$xml .= ($parts[$i]->type == 'preface' ? '</div>'."\r\n" : '');
				// if list is list (e.g., of illustrations) close div
				$xml .= ($parts[$i]->type == 'list' ? '</div>'."\r\n" : '');
			}
		}
	}
	if($toc) {
	    $document->xml = $xml . "<script>TT.Utility.initTOC();</script>";
    }
	return $menu;
}

// generates jump-menu and (listing or index TOC)
function load_listing($type,&$document) {
	$listdata = (substr_count('|tune|composer|composed|key|meter|',$document->sortby) ? 'scores' : 'lyrics');
	
	$index = !$document->section;
	
	if($index && substr_count('title',$document->sortby)) {
		//global database variable array passed to database connection from included "f_dbase.php"
		global $db;
		if(!$db['link']) {
			db_connect($db);
			$db_collection = ($document->collection == '_' ? '%' : $document->collection);
			$results = mysql_query("SELECT title,title_sort,url FROM ". $db["tt3_indices"] ." WHERE type = '".$type."' AND collection LIKE '%/".$db_collection."/%' AND title_sort LIKE '".$document->sortby."|%'");
			while($r = mysql_fetch_assoc($results)) {
				// grabs the first character after the pipe: field = sortby|title
				$indexed = strtoupper( substr($r['title_sort'],strpos($r['title_sort'],'|')+1,1) );
				if(!$indices[$indexed]) {
					$indices[$indexed]['title'] = $r['title'];
					$indices[$indexed]['url'] = $r['url'];
				} else {
					$indices[$indexed]['after'] = true;
				}
			}
			db_disconnect($db);
		}
	}
	
//	global $rewrite_title_url;
	// returns jump menu code, changes listing xml passed by reference
	$xml = '';
	
	// all listings have at least a Whole List view and sort Index
	$menu =
	"\r\n\t".'<option value="_">Whole List</option>'
	."\r\n\t".'<option value=""'.($index ? ' selected="selected"' : '').'>'.ucfirst($document->sortby).' Index</option>'
	."\r\n\t".'<optgroup>';

	if($index) {
		$xml .= '<h2 class="first">'.ucfirst($document->sortby).' Index'.($document->order == 'descending' ? ' (descending)': '').'</h2>';
		$xml .= '<ul>'."\r\n";
	}
	//shorter alias
	$items = &$document->items;
	// flag for looking for previous section title
	$pFlag = ($document->section ? true : false);
	// flag for looking for next section title immediately set if at Index
	$nFlag = ($document->section && $document->type != 'bible' ? false : true);
	if($document->section == '_' && $document->type != 'bible') {
		$document->next_title = ucfirst($document->sortby).' Index';
		$nFlag = false;
	}
	// looping variable to compare with object's matching variable to determine if new section is needed
	$sort_m = false;
	foreach($items as $key => $item) {
		if($sort_m !== $item->sort_match) {
			$sort_m = $item->sort_match;
			// save it as the title immediately next after the current one; turn flag off
			if($nFlag) {
				$document->next_title = $item->sort_real;
				$nFlag = false;
			}
			// filters new section based on requested $document->section
			if(title_url_format($item->sort_real) == $document->section || $document->section == '_') {
				$si++;
				// spells out accidentals notation for browsers that don't render flats and sharps in HTML entities
				if(substr_count($item->sort_real,'&#')) {
					$item->sort_real = '<span title="'.preg_replace(array("'&#9837;'","'&#9839;'"),array(" flat"," sharp"),$item->sort_real).'">'.$item->sort_real.'</span>';
				}
				// if section is a number, it must be linked specially
				$section_url = ($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real));
				$section_link = '<a href="'.level_url().$document->type.'/'.$document->collection.'/'.$section_url.'/'.add_query().'">'.$item->sort_real.'</a>';
				$sort_section =
				"\r\n\t".'<tr><td class="section_head"'.($type != 'texts' ? ' colspan="2"' : '').'><div class="left"><p>'.$section_link.'</p></div><div class="right"><p><a href="#logo" title="Return to top">^</a></p></div></td></tr>';
				if($type == 'texts') {
					$sort_section .=
					"\r\n\t".'</table>'
					."\r\n\t".'<table summary="">'."\r\n\t";
				}
				$pFlag = false; // stop looking for previous section title
				 // if not at Whole List, start looking for next section title
				if($document->section != '_') { $nFlag = true; }
				// if no previous title being found yet, and this is not the whole list,
				// this indicates the current title is the first section, so the previous is the Whole List
				if(!$document->prev_title && $document->section != '_') {
					$document->prev_title = ucfirst($document->sortby).' Index';
				}
//$sort_section .= "<h1>2</h1>". print_r($item,true);              
			} else {
				// save it in case it is the title immediately previous to the current one
				if($pFlag) { $document->prev_title = $item->sort_real; }
			}
			if($index) {
				if(substr_count('title',$document->sortby)) {
					$xml .= "\r\n\t".'<li class="index">'
						.'<div class="item"><a href="'.($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real)).'/?sortby='.$document->sortby.($document->order == 'descending' ? '&amp;order=descending' : '').'">'.$item->sort_real.'</a></div>'
						.'<div class="excerpt"><span><a href="'.level_url().$indices[$item->sort_real]['url'].'">'.$indices[$item->sort_real]['title'].'</a>'.($indices[$item->sort_real]['after'] ? ', ... ' : '').'</span></div>'
						.'</li>';
				} else {
					$xml .= "\r\n\t".'<li><a href="'.($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real)).'/?sortby='.$document->sortby.($document->order == 'descending' ? '&amp;order=descending' : '').'">'.$item->sort_real.'</a></li>';
				}
			}
			$menu .= "\r\n\t".'<option value="'.($document->sortby == 'number' ? $item->sort_match.'00' : title_url_format($item->sort_real)).'"'.(title_url_format($item->sort_real) == $document->section ? ' selected="selected"' : '').'>'.$item->sort_real.'</option>';
		} else {
			$sort_section = '';
		}

		// if listing texts, update collection name, in case a title url needs rewriting
//		if($type == 'texts') { $rewrite_title_url = $item->collection; }
		
		$url_title = $item->url_title;
		$tooltip_title = title_tooltip_format($item->title);

		// variables used if sorting by scores
		$scoretitle;
		$scoreauthor;
		$scoremeter;
		$scorekey;
		if($type == 'music') {
			// inserts links to Scorch pages, if available
			$score_links = '';
			if($item->score) {
				foreach($item->score as $s_id => $score) {
					$s_t = '_'.strtolower(str_replace('_','',title_url_format($score->title))).'0_';
//echo "\r\n$s_t|$key";                    
					// if sorting by score data, score must match title key
					if((substr_count($key,$s_t) && $listdata == 'scores') || $listdata != 'scores') {
						if($score->sib) {
							// create tooltip-compatible title|author string
							$tooltip = ($score->title ? $score->title.' | ' : '').preg_replace("'^.+>(.*?)<[\s\S]+$'","$1",$score->author);
							$score_links .= '<a href="'.level_url().'music/'
							// append score id, if any, to link
							.title_url_format($item->title).'/score'.($score->id ? '_'.$score->id : '')
							.'/" title="'.title_tooltip_format($tooltip).'">'
							.'<img src="'.level_url_webfront().'link_score.gif" alt="'.title_tooltip_format($tooltip).'" /></a> ';
						} elseif($listdata == 'scores') {
							continue 2; // if sorting by scores and no score is available, skip
						}
						$scoretitle = $score->title;
						$scoreauthor = preg_replace("'^.+>(.*?)<[\s\S]+$'","$1",$score->author);
						$scoremeter = $score->meter;
						$scorekey = preg_replace(array("'b'","'s'"),array("&#9837;","&#9839;"), $score->key); // converts flats (b) and sharps (s) notation to HTML characters
						if(substr_count($scorekey,'&#')) {
							$scorekey = '<span title="'.preg_replace(array("'&#9837;'","'&#9839;'"),array(" flat"," sharp"),$scorekey).'">'.$scorekey.'</span>'; // for browsers that don't render flats and sharps notation, spell out
						}
						// the limitations of listing by scores prevents multiple scores per listing
						if($listdata == 'scores') { break; }
					}
				}
			}
		} elseif($type == 'texts') {
			if($document->sortby == 'issue') { // search engines should prioritize sorting by issue page
				$coll = str_replace('_',' ',$document->collection);
				$coll_prefix = (substr_count('|Foundation Truth|Treasures of the Kingdom|Dear Princess|',$coll) ? $coll.' ' : '');
			}
			$text_header =
			"\r\n\t".'<tr><td class="icon" rowspan="3"><a href="'.level_url().'texts/'.$url_title.'/" title="'.$coll_prefix.$tooltip_title.'"><img src="'.level_url().'texts/'.$url_title.'.jpg" alt="'.$coll_prefix.$tooltip_title.'" width="120" height="120" /></a></td>'
			."\r\n\t\t".'<td class="top" colspan="2"><div class="right"><p>'.convert_date($item->date).'</p></div></td></tr>';
		}
		// filters item based on requested $document->section
		if (title_url_format($item->sort_real) == $document->section || $document->section == '_') {
			$sj++;
			// if under copyright (only applies to music sorted by number), display symbol
			if($document->sortby == 'number' && $item->copyright['year'] >= 1923 && !$item->copyright['cc'] && !$item->copyright['tt']) {
				$copyright = '<span class="red">&copy;</span> ';
			} else {
				$copyright = '';
			}
			// if sorting by scores, uses score data if present
			$titleline = ($listdata == 'scores' && strlen($scoretitle) ? $scoretitle : $item->title);
			// if year data is present, list only first year given
			$authorline = ($listdata == 'scores' ? $scoreauthor : $item->author).($item->year ? ', '.preg_replace("'(\d{4}).*'","$1",$item->year) : '');
			$subjectline = ($listdata == 'scores' ? $scoremeter : ($document->sortby == 'scripture' ? $item->scripture : $item->subject));
			//adds, if any present, (score key) or (collection music number, if all '_' collections are not being listed)
			if($scorekey && $listdata == 'scores') {
				$parenline = ' <span> ('.$scorekey.')</span>';
			} elseif($item->coll_id && $document->collection != '_') {
				$parenline = ' <span> (#'.$item->coll_id.')</span>';
			} else {
				$parenline = false;
			}
			static $tableclass; // flag for setting first table to have 0 margin-top
			$xml .= "\r\n\r\n\t"
			.'<table'.(!$tableclass ? ' class="first"' : '').' summary="">'
			.$sort_section
			 //adds 
			.$text_header
			."\r\n\t".'<tr><td class="title">'.$copyright.'<span class="title">'.$score_links.'<a href="'.level_url().$type.'/'.$url_title.'/">'.apply_formatting($titleline).'</a></span>'
			.$parenline.'<span> | '.str_replace(' ','&nbsp;',$authorline).'</span></td>'
			."\r\n\t".'<td class="subject"><span>'.$subjectline.'</span><a href="#"></a></td></tr>'
			// simple formatting of em dash only, as curly quotes are not distinguishable, and not easily searched from brower
			.($listdata != 'scores' ? "\r\n\t".'<tr><td class="blurb" colspan="2"><div>'.str_replace('--','&#8212;',$item->blurb).'</div></td></tr>': '')
			."\r\n\t".'</table>';
			$tableclass = true;
		}
	}
	// closes jump menu group
	if(!$xml && $document->section) {
		$menu .= "\r\n\t".'<option value="" selected="selected">&#8212;no match&#8212;</option>';
		$xml = '<h3>No matches found. Try some other options.</h3>';
	} elseif($document->section) {
		// displays # of items (in # of sections, if more than 1)
		$xml = '<h3 class="first">'.$sj.' item'.($sj > 1 ? 's' : '').($si > 1 ? ' in '.$si.' '.str_replace('composed','year',$document->sortby).' sections' : '').':</h3>'."\r\n\r\n" . $xml;
	}
	if($index) {
		$xml .= '</ul>'."\r\n";
	}
	$menu .= "\r\n\t".'</optgroup>';

	$document->xml .= $xml;
	return $menu;
}
?>
