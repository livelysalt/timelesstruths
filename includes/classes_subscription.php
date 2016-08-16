<?php
// classes_subscription.php

// global variables available from _urlhandler.php:
// $type	- always is 'subscription'
// $title	- defaults to ''
// $section - defaults to ''

require_once "includes/f_dbase.php";
require_once "includes/f3_common.php";

// create html for page
$subscription = new Subscription();
// collect html
$html = $subscription->output();

echo $html;



//////////////////////////////////////////////////////////////////////

class Subscription {
	var $html;
	
	var $h_meta;
	var $h_body;
	var $h_navbar;
	var $h_info;
	
	function Subscription() {
		$this->h_meta = new hMeta();
		$this->h_body = new hBody();
		$this->h_navbar = new hNavBar();
		$this->h_content = new hContent();
	}
	
	function output() {
		$this->stitch();
		return $this->html;
	}
	
	function stitch() {
		global $title;
		$this->html = false;
		$this->html .= $this->h_meta->pre;
		$this->html .= $this->h_meta->meta;
		$this->html .= $this->h_meta->style;
		if($title) { $this->html .= $this->h_meta->script; }
		$this->html .= $this->h_meta->post;
		
		$this->html .= $this->h_body->pre;
		$this->html .= $this->h_body->globalnav;
		$this->html .= $this->h_navbar->nav;
		$this->html .= $this->h_content->pre;
		$this->html .= $this->h_content->form;
		$this->html .= $this->h_content->post;
		$this->html .= $this->h_body->post;
	}
}

class hMeta {
	var $html;
	function hMeta() {
		$this->pre = '
		<?xml version="1.0"?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
		
		<head>';
		$this->meta = '
			<title>Subscriptions | Timeless Truths Publications</title>
			<meta name="description" content="Free subscription to Christian magazines encouraging holy living. Foundation Truth for youths and adults. Treasures of the Kingdom for children." />
			<meta http-equiv="content-type" content="text/html; charset=utf-8" />
			<meta http-equiv="Content-Style-Type" content="text/css" />';
		$this->style = '
			<link rel="shortcut icon" href="'.level_url_webfront().'timeless.ico" />
			<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_position.css" media="all" />
			<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_font.css" media="all" />
			<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_color.css" media="screen, projection" />
			<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_print.css" media="print" />
			<link rel="stylesheet" type="text/css" href="'.level_url_webfront().'css_subscription.css" media="all" />';
		$this->script = '
			<script language="javascript" type="text/javascript src="'.level_url_webfront().'js_subscription.js"></script>';
		$this->post = '
		</head>';
	}
}

class hBody {
	var $pre;
	var $post;
	var $globalnav;
	function hBody() {
		$this->pre = '
			<body class="blue"><div id="column">';

		$google_analytics = '<script src="http://www.google-analytics.com/urchin.js" type="text/javascript"></script><script type="text/javascript">_uacct="UA-1872604-1";urchinTracker();</script>';
		$this->post = '
		<div class="navbar footer"><p>
		Site by LivelySalt. <a href="'.level_url().'help/Management_and_Policies/Copyrights/">Copyright</a> &copy; 2002-2007 Timeless Truths Publications. Hosted by <a href="http://ibiblio.org">ibiblio</a>.
		</p></div>

		</div>
		'.($_SERVER['HTTP_HOST'] != 'localhost' ? $google_analytics : '').'
		</body>'
		."\r\n".'</html>';

		$this->globalnav = '
		<div id="logo"><a href="http://library.timelesstruths.org" accesskey="1"><span>Timeless Truths Free Online Library</span></a></div>'
		."\r\n"
		.'
		<div id="globalnav">
			<div id="tab-bible"><a href="'.level_url().'bible/">Bible</a></div>
			<div id="tab-texts"><a href="'.level_url().'texts/">Texts</a></div>
			<div id="tab-music"><a href="'.level_url().'music/">Music</a></div>
			<div id="site-links"><a href="'.level_url().'">Welcome</a> | <a href="'.level_url().'about/">About Us</a> | <a href="'.level_url().'search/">Search</a> | <a href="'.level_url().'help/">Help</a></div>
		</div>
		';
	}
}

class hNavBar {
	var $nav;
	function hNavBar() {
		$this->nav = "\r\n\t".'<div class="navbar subnav"><p>'
			// javascript date should subtract from UTC 8 hours during PST, and 7 hours during PDT ----------------v
			. '<script type="text/javascript">var d=new Date();dMil=d.getUTCMilliseconds();dMil=dMil-(8*60*60*1000);d.setUTCMilliseconds(dMil);var monthname=new Array("January","February","March","April","May","June","July","August","September","October","November","December");document.write(monthname[d.getUTCMonth()] + " ");document.write(d.getUTCDate() + ", ");document.write(d.getUTCFullYear());</script>'
			. "\r\n\t".'</p></div>';
	}
}

class hContent {
	var $pre;
	var $post;
	var $form;
	function hContent() {
		$this->pre = '<div class="content" id="content">
		<div class="document">';
		
		$this->form = '
		<form id="" name="" method="POST" action="">
		
		<h2>Subscribe To...</h2>
		<table summary="subscription options">
		<tr><th>&nbsp;</th><td><input type="checkbox" id="ft" name="ft" /><label for="ft"> <i>Foundation Truth</i></label> for youths and adults (quarterly)</td></tr>
		<tr><th>&nbsp;</th><td><input type="checkbox" id="totk" name="totk" /><label for="totk"> <i>Treasures of the Kingdom</i></label> for children (quarterly)</td></tr>
		<tr><th>&nbsp;</th><td><input type="checkbox" id="web" name="web" /><label for="web"> website news</label> (less than 2 per year)</td></tr>
		</table>
		
		<h2>Email Notification</h2>
		<table summary="email subscription form">
		<tr><th>Email:</th><td><input type="text" id="email" name="email" value="" /></td></tr>
		</table>

		<h2>Print Magazine</h2>
		<table summary="print subscription form">
		<tr><th>Name:</th><td><input type="text" id="fullName" name="fullName" value="" /></td></tr>
		<tr><th>Address:</th><td><input type="text" id="street" name="street" value="" /></td></tr>
		<tr><th>City:</th><td><input type="text" id="city" name="city" value="" /></td></tr>
		<tr><th>State:</th><td><input type="text" id="state" name="state" value="" /></td></tr>
		<tr><th>ZIP:</th><td><input type="text" id="zip" name="zip" value="" /></td></tr>
		<tr><th>&nbsp;</th><td style="text-align:right;"><input type="submit" value="Verify Address &gt;&gt;" /></td></tr>
		</table>

		</form>';
		
		$this->post = '
		</div>
		</div>';
	}
}
?>