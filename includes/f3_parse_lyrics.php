<?php
// f3_parse_lyrics.php
$parsed = ''; // holds parsed text

function parse_xml_lyrics($str_xml) {
	// runs text formatting that depends on pre-parsed structure
	$str_xml = apply_formatting($str_xml,'pre');
	// create parser
	$xml_parser = xml_parser_create();
	// define parsing handler functions
	xml_set_element_handler($xml_parser, 'parse_lyrics_startEl', 'parse_lyrics_endEl');
	xml_set_character_data_handler($xml_parser, 'parse_lyrics_doChar');
	// parses xml string
	if(!xml_parse($xml_parser, $str_xml))
	{
		$error = sprintf("%s at line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser) );
		die("<br /><h1>XML error: $error; please <a href='".level_url()."contact/?subject=".urlencode($_SERVER['REQUEST_URI'])."&amp;comments=".urlencode("XML error: $error: $str_xml")."'>report the problem</a>.</h1>");
	}
	// closes parser
	xml_parser_free($xml_parser);
}

function parse_lyrics_startEl($parser, $element, $attrs) {
	global $parsed;
	$parsed .= xml_lyrics_to_html(true, $element, $attrs);
}

function parse_lyrics_doChar($parser, $data) {
	global $parsed;
	$parsed .= xml_lyrics_to_html(true, 'ELEMENT', array('DATA'=>$data));
}

function parse_lyrics_endEl($parser, $element) {
	global $parsed;
	$parsed .= xml_lyrics_to_html(false, $element);
}

function xml_lyrics_to_html($parse, $element, $attrs = false)
{
	global $page; // only available to access variables; be careful about changing any
	$document = &$page->document; // shorter alias
	$parts = &$document->parts; // shorter alias
	// assigned to other variables to more readily identify them
	$url_title = title_url_format($document->title);
	$url_section = $document->section;


	// array for keeping track of verses
	static $pV = array();
	// keeps track of element nesting level for object that can be nested, such as lists
	if($parse && $element != 'ELEMENT') {
		$pV['level']++;
	}
	// shorter aliases;
	$pVL = &$pV['level'];
	$pVE = &$pV[$pfL][$element];
	
	// assigns attributes of current element to holding array
	if($parse) {
		$pVE = $attrs;
		if($element == 'VERSE') {
			if(!$attrs['ID']) { // if no ID is given, increments the current verse count
				$pV['this'] = &$pV['verses'][++$pV['ID']];
			} else {
				$pV['this'] = &$pV['verses'][$attrs['ID']];
			}
		}
	}
	// shorter alias;
	$pVT = &$pV['this'];

	switch($element)
	{
	case 'LYRICS':
				if($parse) {
					$pV['on'] = true;
				} else {
					$pV['on'] = false;
				}
				break;
	case 'VERSE':
				if(substr($pVE['ID'],0,7) == 'refrain') {
					if($parse) {
						$pVT .= '<ul><li class="refrain">';
						$pVT .= '<span class="refrain">'.ucfirst($pVE['ID']).':</span><br />';
					} else {
						$pVT .= '</li></ul>';
					}
				} else {
					if($parse) {
						$pVT .= '<li>';
					} else {
						$pVT .= '</li>';
					}
				}
				break;
	case 'B':
	case 'I':
				if($parse) {
					$pVT .= '<'.$element.'>';
				} else {
					$pVT .= '</'.$element.'>';
				}
				break;
	case 'BR':		
				if($parse) {
					$pVT .= '<br />';
				}
				break;

	case 'ELEMENT': //case 'ELEMENT' only happens with element data
				$pVT .= apply_formatting($pVE['DATA']);
				break;		
	}
	
	if(!$parse) {
		$pV['level']--;
		if(!$pV['on']) {
			// if finished parsing, rearrange refrain verses
			foreach($pV['verses'] as $id => $verse) {
				if($id == 'refrain') {
					// if only one refrain, and its not before the first verse (i.e. "All Things Bright and Beautiful"), move to end of first verse
					if($html) {
						$html = preg_replace("'(</li>)'","\r\n\t".$verse."$1",$html,1);
					} else {
						$html = preg_replace("'class=\"'","class=\"first ",$verse,1);
					}
				} elseif(substr($id,0,7) == 'refrain') {
					// move refrain to end of previous verse
					$html = preg_replace("'(</li>\s*)$'","<br />\r\n\t".$verse."$1",$html);
				} else {
					// lyrics verse 1 only is first if no media is being played
					if(strlen($html) == 0 && $id == 1 && !$document->section) {
						$verse = preg_replace("'<li>'",'<li class="first">',$verse);
					}
					$html .= $verse;
				}
			}
			$html = '<ol>'.$html.'</ol>';
		}
	}

	return $html;
}

?>