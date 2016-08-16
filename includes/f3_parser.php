<?php
// f3_parser.php
$parsed = ''; // holds parsed text

define('FORMAT',(($v = $_REQUEST['format']) ? $v : 'html')); // ('html','mobi','pdf')

function parse_xml_texts($str_xml) {
	// runs text formatting that depends on pre-parsed structure
	$str_xml = apply_formatting($str_xml,'pre');
//	global $parsed; $parsed = $str_xml; return;
	// create parser
	$xml_parser = xml_parser_create();
	// define parsing handler functions
	xml_set_element_handler($xml_parser, 'parse_startEl', 'parse_endEl');
	xml_set_character_data_handler($xml_parser, 'parse_doChar');
	// parses xml string
	if(!xml_parse($xml_parser, $str_xml))
	{
		$error = sprintf("%s at line %d column %d index %d [#%d]", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser), xml_get_current_column_number($xml_parser), xml_get_current_byte_index($xml_parser), ord(substr($str_xml, xml_get_current_byte_index($xml_parser)-1, 1)) );
		$troubleshoot = '...' . htmlentities(substr($str_xml, xml_get_current_byte_index($xml_parser) - 20, 40)) . '...';
		die("<br /><h2 style='color:red;'>$troubleshoot</h2><h2>XML error: $error</h2><h2>Please <a href='".level_url()."contact/?subject=".urlencode("XML error on ".$_SERVER['REQUEST_URI'])."&amp;comments=".urlencode("http://library.timelesstruths.org".$_SERVER['REQUEST_URI']."\r\n\r\n$error")."'>report the problem</a>.</h2>");
	}
	// closes parser
	xml_parser_free($xml_parser);
}

function parse_startEl($parser, $element, $attrs) {
	global $parsed;
	$parsed .= xml_to_html(true, $element, $attrs);
}

function parse_doChar($parser, $data) {
	global $parsed;
	$parsed .= xml_to_html(true, 'ELEMENT', array('DATA'=>$data));
}

function parse_endEl($parser, $element) {
	global $parsed;
	$parsed .= xml_to_html(false, $element);
}

function xml_to_html($parse, $element, $attrs = false) {
	global $page; // only available to access variables; be careful about changing any
	$document = &$page->document; // shorter alias
	$parts = &$document->parts; // shorter alias
	// assigned to other variables to more readily identify them
	if($_GET['query']) {
		$url_type = $_GET['query'];
	} else {
		$url_type = $document->type;
	}
	$url_title = $document->url_title;
	$url_section = $document->section;
	
	
	// array for keeping track of attributes on current elements
	static $parser_flags = array();
	// keeps track of element nesting level for object that can be nested, such as lists
	if($parse && $element != 'ELEMENT') {
		$parser_flags['level']++;
	}
	// shorter aliases;
	$pfL = &$parser_flags['level'];
	$pfE = &$parser_flags[$pfL][$element];
	
	// skip if contained between IMAGE tags
	static $pfI;
	if($element == 'IMAGE') { $pfI = $pfL; }
	if($parser_flags[$pfI]['IMAGE'] && $element != 'IMAGE') {
		if(!$parse) {
			// if at end of element, delete level from array
			unset($parser_flags[$pfL]);
			// keeps track of element nesting level for object that can be nested, such as lists
			$parser_flags['level']--;
		}
		return;
	}
	// skip if contained between TOC tags
	static $pfT;
	if($element == 'TOC') { $pfT = $pfL; }
	if(isset($parser_flags[$pfT]['TOC']) && $element != 'TOC') {
		if(!$parse) {
			// if at end of element, delete level from array
			unset($parser_flags[$pfL]);
			// keeps track of element nesting level for object that can be nested, such as lists
			$parser_flags['level']--;
		}
		return;
	}
	
//print_r($parser_flags);
	$html = '';
	// assigns attributes of current element to holding array
	if($parse) { $pfE = $attrs; }

/* DEBUGGING	
	echo "\n->";
	for($i=0; $i < $pfL; $i++) {
		echo "\t";
	}

	echo "parser_flags[".$pfL."][".$element."] : ";
	if(is_array($pfE)) {
		foreach($pfE as $key => $value) {
			echo "[".$key."=>".$value."]";
		}
	}//*/
//static $BUG; static $BUGOFF = true; if($BUGOFF && $element != 'SECTION') { return; }

	switch($element)
	{
	case 'AUTHOR':
				if($parse) {
					$html .= '<p class="author">';
				} else {
					$html .= "</p>\r\n";
				}
				break;
	case 'BLOCK':
				if($parse) {
					$html .= '<div class="block '.$pfE['ALIGN'].'"'.($pfE['STYLE'] ? ' style="'.($pfE['WIDTH'] ? $pfE['WIDTH'].'px;' : '').$pfE['STYLE'].'"' : ($pfE['WIDTH'] ? ' style="width:'.$pfE['WIDTH'].'px"' : '')).'>';
				} else {
					$html .= '</div>'."\r\n\r\n";
				}		
				break;
	case 'BLURB':
				if($parse) {
					// only occurs in BIBLE
					// if within a default paragraph (p), end paragraph
					if($parser_flags[2]['SECTION']['KJVp']) {
						$html .= '</ul>'."\r\n";
						$parser_flags[2]['SECTION']['KJVp'] = false;
					}
					$html .= '<div class="blurb">';
					if($url_type == 'bible') { $html .= '<p class="first">'; }
				} else {
					if($url_type == 'bible') { $html .= '</p>'; }
					$html .= '</div>'."\r\n\r\n";
				}		
				break;
	case 'BREAK':
				if($parse) {
					// if within a default paragraph (p), end paragraph
					// only occurs in Bible
					if($parser_flags[2]['SECTION']['KJVp']) {
						$html .= '</ul>'."\r\n";
						$parser_flags[2]['SECTION']['KJVp'] = false;
					}
					if($pfE['TYPE'] == 'minor') { $break_class = ' class="minor"'; }
					$html .= '<hr'.$break_class.($pfE['WIDTH'] ? ' style="width:'.$pfE['WIDTH'].'px"' : '').' />';
				}
				break;
	case 'DOCUMENT': // this is the level 1 element, and should be ommited from the HTML
				break;
	case 'HEADING':
				if($pfE['TYPE'] == 'minor') {
					$h_size = 'h3';
				} else {
					$h_size = 'h2';
				}
                // if within a paragraph, text must be only visually pulled into a heading
                $is_pullquote = isset($parser_flags[ $parser_flags['level']-1 ]['P']);
				if($parse) {
					// only occurs in BIBLE
					// if within a default paragraph (p), end paragraph
					if($parser_flags[2]['SECTION']['KJVp']) {
						$html .= '</ul>'."\r\n";
						$parser_flags[2]['SECTION']['KJVp'] = false;
					}
					$p_type = ($pfE['TYPE'] == 'subtitle' ? 'subtitle' : '');
					$p_align = (isset($pfE['ALIGN']) ? ' '.$pfE['ALIGN'] : '');
					$html .= '<'. ($is_pullquote ? 'b' : $h_size).($p_type || $p_align || $is_pullquote ? ' class="'.$h_size.' '.$p_type.$p_align.'"' : '').'>';
				} else {
					$html .= '</'.($is_pullquote ? 'b' : $h_size).'>';
				}
				break;
	case 'IMAGE':
				if($parse) {
					// directory path relative from root
					if($url_type == 'texts') {
						$relroot = 'library/texts/';
					} elseif($url_type == 'help') {
						$relroot = 'www/help/';
					}
					// inserts image path relative from page
					$image_path = ($pfE['TYPE'] == "root" ? $pfE['FILE'] : $relroot.$url_title[0].'/'.$url_title.'/'.$pfE['FILE'])
                                . (!preg_match("/.(jpg|gif|png)$/",$pfE['FILE']) ? ".jpg" : '');
					// if image dimensions aren't specified, extract from file
					if(!$pfE['WIDTH'] && !$pfE['HEIGHT']) {
						$image_size = getimagesize($image_path);
						$pfE['WIDTH'] = $image_size[0];
						$pfE['HEIGHT'] = $image_size[1];
					}
					// if image is linked to PDF, add PDF filesize to title
					$image_filesize = ($parser_flags[$pfL-1]['LINK']['TYPE'] == 'pdf' ? ' ('.format_filesize(dirname($image_path).'/'.$parser_flags[$pfL-1]['LINK']['LINK']).')' : '');
					$altline = ' alt="'.$pfE['TEXT'].'"'.(isset($pfE['TEXT']) ? ' title="'.$pfE['TEXT'].$image_filesize.'"' : '');
                    $styleline = ($pfE['STYLE'] ? $pfE['STYLE'] : '');
					// if image alignment is not specified, default is center
					if(!$pfE['ALIGN']) { $pfE['ALIGN'] = 'center'; }
					if($pfE['ALIGN'] == 'inline') {
						$html .= '<img style="vertical-align:middle;'.$styleline.'" src="'.level_url().$image_path.'"'
							.$altline
							.' width="'. $pfE['WIDTH'] .'"'
							.' height="'. $pfE['HEIGHT'] .'"'
							.' />';
					} else {
						// if within link, don't add div container
						$html .= (!$parser_flags[$pfL-1]['LINK'] ? '<div class="'.$pfE['ALIGN'].'">' : '')
							.'<img '.($pfE['CLASS'] ? 'class="'.$pfE['CLASS'].'" ' : '').'src="'.level_url().$image_path.'"'
							.$altline
							.' width="'. $pfE['WIDTH'] .'"'
							.' height="'. $pfE['HEIGHT'] .'"'
							.($styleline ? ' style="'.$styleline.'"' : '')
							.' />'
							.(!$parser_flags[$pfL-1]['LINK'] ? '</div>' : '');
					}
				}
				break;
	case 'LINK':
				if($parse) {
				} else {
					if(strlen($pfE['TEXT'])) {
						// if no non-email LINK attribute is present, use text accumulated from link ELEMENT
						if(!isset($pfE['LINK']) && $pfE['TYPE'] != 'email') {
							$pfE['LINK'] = ($pfE['TYPE'] == 'external' ? $pfE['TEXT'] : title_url_format($pfE['TEXT']));
						}
					} elseif(!$pfE['ANCHOR'] || $pfE['TYPE'] != 'anchor') {
						$class = ' class="lib_ref"';
						$pfE['TEXT'] = '*';
					}
					// if no link text is given, assume reference
					switch($pfE['TYPE']) {
						// current page link anchor, only insert href if LINK attribute is present
						case 'anchor':
							if($pfE['LINK']) { $link = ' href="#'.$pfE['LINK'].'"'; }
							break;
						// current document
						case 'document':
							$anchorline = ($pfE['ANCHOR'] ? '?anchor='.$pfE['ANCHOR'].'#'.rawurlencode($pfE['ANCHOR']) : '');
							$link = ' href="'.level_url().$url_type.'/'.$url_title.(strlen($pfE['LINK']) ? '/'.title_url_format($pfE['LINK']) : '').'/'.$anchorline.'"';
							break;
						// relative from root
						case 'root':
							if( substr_count($pfE['LINK'],'music') || substr_count($pfE['LINK'],'texts') ) { $class = ' class="green"'; }
							$link = ' href="'.level_url().$pfE['LINK'].'"';
							break;
						// library texts or music resource
						// if called from type 'help', a 'blue' page, make sure link is green
						case 'bible':
							$link = ($url_type == 'help' || $url_type == 'bible' ? ' class="green"' : '').' href="'.level_url().$pfE['TYPE'].'/'.preg_replace(array("'(?<=[123]) '","' '"),array('_','/'),$pfE['LINK']).'/" title="'.title_tooltip_format($pfE['LINK']).'"';
							break;
						// anchor links only occur in texts
						case 'texts':
							$anchorline = ($pfE['ANCHOR'] ? '?anchor='.$pfE['ANCHOR'].'#'.rawurlencode($pfE['ANCHOR']) : '');
						case 'music':
                            if (FORMAT == 'mobi') {
                                $link = ($url_type == 'help' || $url_type == 'music' ? ' class="green"' : '').' href="http://library.timelesstruths.org/'.$pfE['TYPE'].'/'.str_replace('*','/',title_url_format(str_replace('/','*',$pfE['LINK']))).($pfE['LINK'] ? '/' : '').$anchorline.'" title="'.title_tooltip_format($pfE['LINK']).'"';
                            } else {
                                $link = ($url_type == 'help' || $url_type == 'music' ? ' class="green"' : '').' href="'.level_url().$pfE['TYPE'].'/'.str_replace('*','/',title_url_format(str_replace('/','*',$pfE['LINK']))).($pfE['LINK'] ? '/' : '').$anchorline.'" title="'.title_tooltip_format($pfE['LINK']).'"';
                            }
							break;
						// special resource links
						case 'email':
							$link = ' href="'.level_url().'contact/?to='.$pfE['LINK'].'"';
							$pfE['TEXT'] = (!$pfE['TEXT'] || $pfE['TEXT'] == '*' ? 'email' : $pfE['TEXT']);
							$class = '';
							break;
						case 'pdf':
							$link = ' href="'.level_url().'library/'.$url_type.'/'.$url_title[0].'/'.$url_title.'/'.$pfE['LINK'].'"';
							break;
						// site search
						case 'search':
							$link = ' href="'.level_url().'search/?query='.$pfE['QUERY'].'&amp;q='.$pfE['LINK'].'"';
							break;
						// off-site link
						case 'external':
							$class = ' class="red"';
							$link = ' href="'.$pfE['LINK'].'"';
							break;
					}
					// if ANCHOR, set link name
					if($pfE['ANCHOR']) {
						$anchor = ' name="'.$pfE['ANCHOR'].'"';
					}
					$html .= '<a'.$class.$anchor.$link.'>'.apply_formatting($pfE['TEXT']).'</a>';
				}
				break;
	case 'LIST': // convert to standard HTML list
				$listtype = ($pfE['TYPE'] == 'ordered' ? 'ol' : 'ul'); // TYPE 'recipe' is a subset of TYPE 'unordered'
				if($parse) {
					$html .= '<'.$listtype.($pfE['TYPE'] == 'recipe' ? ' class="recipe"' : ($pfE['STYLE'] ? ' class="list-'.$pfE['STYLE'].'"' : '')).'>';
				} else {
					$html .= '</'.$listtype.'>';
				}
				break;
	case 'ITEM': // convert to standard HTML list item
				if($parse) {
					$html .= "<li>";
				} else {
					$html .= "</li>\r\n";
				}
				break;
	case 'NOTE': // triggers footnote compiler
				if($parse) {
					// notes should be in a 1-base array
					$parser_flags[1]['DOCUMENT']['NOTEID']++;
					$parser_flags[1]['DOCUMENT']['NOTING'] = true;
				} else {
					// closes note and sets anchored link
					$noteID = $parser_flags[1]['DOCUMENT']['NOTEID'];
                    if (FORMAT == 'mobi') {
                        $html .= '<a class="note-ref" id="ref'.$noteID.'" href="#note'.$noteID.'"><sup>'.$noteID.'</sup></a>';
                    } else {
                        $html .= '<a class="note-ref" id="ref'.$noteID.'" href="#note'.$noteID.'">'.$noteID.'</a>';
                    }
					$parser_flags[1]['DOCUMENT']['NOTING'] = false;
				}
				break;
	case 'P':
				if($parse) {
					$html .= '<p';
					$p_align = (isset($pfE['ALIGN']) ? ' '.$pfE['ALIGN'] : '');
					$html .= (isset($pfE['TYPE']) || isset($pfE['ALIGN']) ? ' class="'.$pfE['TYPE'].$p_align.'"' : '');
					$html .= '>';
				} else {
					$html .= "</p>\r\n";
					// if at the end of a pargraph, outside a note, place notes, if any
					if(!$parser_flags[1]['DOCUMENT']['NOTING'] && $parser_flags[1]['DOCUMENT']['NOTES']) {
						foreach($parser_flags[1]['DOCUMENT']['NOTES'] as $num => $note) {
							$html .= '<div class="note" id="note'.$num.'">'
                                  .  (FORMAT == 'mobi' || FORMAT == 'pdf' ?
                                         '<p class="note-num noindent">[Note '.$num.': '
                                       . preg_replace("'^\s*<p>([\s\S]*)</p>\s*$'","$1 <span class='backlink'>&laquo;<a href='#ref{$num}'>back</a>&raquo;</span>]</p>",$note)
                                     :
    								     '<p class="note-num noindent"><span>['.$num.']:</span></p>'
    								   . preg_replace("'^\s*<p>([\s\S]*)</p>\s*$'","<p><span class=\"hidden\">[</span>$1<span class=\"hidden\">]</span></p>",$note)
                                     )
								  .  "</div>\r\n";
						}
						$parser_flags[1]['DOCUMENT']['NOTES'] = false;
					}
				}
				break;
	case 'PM':	// BIBLE paragraph marker
				if($parse) {
					// SECTION elements are always level 2
					$parser_flags[2]['SECTION']['KJVpm'] = true;
				}
				break;
	case 'Q':
				if($url_type == 'bible') { // in BIBLE, indicates quoting Jesus
					if($parse) {
						$html .= '<span class="KJV_Jesus">';
					} else {
						$html .= "</span>";
					}
				}		
				break;
	case 'QUOTE': // attributes TYPE=normal(default),recipe
				if($parse) {
					$html .= '<blockquote>';
				} else {
				    if($pfE['SCRIPTURE']) {
                        $cite = true;
                        $scriptureline = scripture_ref($pfE['SCRIPTURE'],/*is_ref:*/true, /*$str_showing:*/($pfE['DISPLAY'] ? $pfE['DISPLAY'] : null), /*$str_scripture:*/null, /*$is_direct:*/true);				        
                        if($pfE['VERSION']) {
                            $scriptureline .= scripture_version($pfE['VERSION']);
                        }
				    }
					if($pfE['QUOTING']) {
						$cite = true;
						$quotingline = $pfE['QUOTING'];
						// if author or source is named, prefix work with correct punctuation
						$quotingline = $quotingline . ($pfE['AUTHOR'] || $pfE['WORK'] ? '&mdash;quoted in ' : '');
					}
					if($pfE['AUTHOR']) {
						$cite = true;
						$authorline = $pfE['AUTHOR'];
					}
					if($pfE['WORK']) {
						$cite = true;
						$work_url = title_url_format($pfE['WORK']);
						// if it's in the library, provide a link
						if(file_exists('library/texts/'.$work_url[0].'/'.$work_url.'/'.$work_url.'.xml')) {
							$workline = '<a href="'.level_url().'texts/'.$work_url.'/"><i>'.apply_formatting($pfE['WORK']).'</i></a>';
						} else {
							$workline = '<i>'.apply_formatting($pfE['WORK']).'</i>';
						}
						// if author or source is named, prefix work with correct punctuation
						$workline = ($authorline || $sourceline ? '; ' : '').$workline;
					}
					if($pfE['PUBLISHER']) {
						$cite = true;
						// if publisher is named, enclose in parentheses
						$publine = ' ('.$pfE['PUBLISHER'].')';
					}
					if($pfE['PERIODICAL']) {
						$cite = true;
						$periodline = ($pfE['FROM'] || $pfE['AUTHOR'] ? '; ' : '').'<i>'.$pfE['PERIODICAL'].'</i>';
					}
					if($pfE['ISSUE']) {
						$cite = true;
						$issueline = ' '.$pfE['ISSUE'];
					}
					if($pfE['FROM']) {
						$cite = true;
						// if it's in the library, provide a link
						if(($work_url = title_url_format($pfE['WORK'])) && file_exists('library/texts/'.$work_url[0].'/'.$work_url.'/'.$work_url.'.xml')) {
							$quo_l = '&ldquo;';
							$quo_r = '&rdquo;';
							$fromline = '<a href="'.level_url().'texts/'.$work_url.'/'.title_url_format($pfE['FROM']).'/">'.apply_formatting($pfE['FROM']).'</a>';
						} else {
							// if pp. notation not found, and 'quoted' not found, and if work/publication is found, consider it the title of a chapter or article, and enquote it
							if(!substr_count($pfE['FROM'],'pp.') && !substr_count($pfE['FROM'],'quoted') && ($workline || $publicationline)) {
								$quo_l = '&ldquo;';
								$quo_r = '&rdquo;';
							} 
							$fromline = apply_formatting($pfE['FROM']);
						}
						// if work is named, prefix from with correct punctuation
						$fromline = ($workline || $authorline ? ', ' : '').$quo_l.$fromline.$quo_r;
					}
                    if($pfE['NOTE']) {
                        $cite = true;
                        $noteline = '; '.$pfE['NOTE'];
                    }
					// if there's any of the citation attributes, add the citation line
					if($cite == true) {
					    if (FORMAT == 'mobi') {
                            $html .= "</blockquote>\r\n"
                                  .  '<blockquote class="blurbref">&mdash; '.$scriptureline.$quotingline.$authorline.$workline.$publine.$fromline.$periodline.$issueline.$noteline;
					    } else {
                            $html .= '<p class="citation"><span>['.$scriptureline.$quotingline.$authorline.$workline.$publine.$fromline.$periodline.$issueline.$noteline.']</span></p>'."\r\n";
                        }
					}
					
					$html .= "</blockquote>\r\n\r\n";
				}		
				break;
	case 'SCRIPTURE':
				if($parse) {
					// if TYPE == "quote" consider SCRIPTURE a quote
					if($pfE['TYPE'] == 'quote') {
						// variable for putting scriptures in quotes; double-quote by default
						// if 'inquote', i.e, quoted in dialog, then apply single quotes
						$s_quote = ($pfE['MODE'] == 'inquote' ? "&lsquo;" : "&ldquo;");
						// closes scripture formatting
						$html .= '<span class="scripture">'.$s_quote;
					// if TYPE == "reference" then it is considered a reference
					} elseif($pfE['TYPE'] == 'reference'){
						// if MODE == "direct" consider SCRIPTURE a direct reference
						// this will be handled at parse_doChar()
					}
				} else {
					// if TYPE == "quote" consider SCRIPTURE a quote
					if($pfE['TYPE'] == "quote") {
						// variable for putting scriptures in quotes; double-quote by default
						// if 'inquote', i.e, quoted in dialog, then apply single quotes
						$s_quote = ($pfE['MODE'] == 'inquote' ? "&rsquo;" : "&rdquo;");
						// if reference is supplied, and it's not an empty ("") reference, add link
						if(isset($pfE['REF'])){
							if($pfE['REF']) { // if reference is given, use it
								$ref_link = scripture_ref($pfE['REF'],/*is_ref:*/false, /*$str_showing:*/($pfE['DISPLAY'] ? $pfE['DISPLAY'] : null), /*$str_scripture:*/null, /*$is_direct:*/($pfE['MODE'] == 'direct'));
							} else { // if reference is empty, send scripture text for searching
								$ref_link = scripture_ref(false,/*is_ref:*/false,/*$str_showing:*/null, /*$str_scripture:*/preg_replace(array("'-'","'\[[^\]]*\]'","'\W'","'\s+'"),array('','',' ',' '),$parser_flags[$pfL]['ELEMENT']['DATA']) );
							}
                            if (FORMAT == 'mobi' || FORMAT == 'pdf') {
                                $html .= $s_quote.'</span>'.$ref_link;
                                if (isset($pfE['ENDPUNC'])) {
                                    $html .= $pfE['ENDPUNC'];
                                } elseif (FORMAT == 'pdf'){
                                    $html .= ".";
                                }
                            } else {
                                $html .= $s_quote.$ref_link.'</span>';
                            }
						// if not supplied, merely end quote and formatting
						} else {
							$html .= $s_quote . "</span>";
						}
						if($pfE['VERSION']) {
                            $html .= scripture_version($pfE['VERSION']);
						}
					// if TYPE == "reference" then it is considered a reference
					} elseif($pfE['TYPE'] == 'reference' || $pfE['TYPE'] == 'ref'){
						// if MODE does not == 'direct' consider reference indirect
						if($pfE['MODE'] != 'direct'){
							$html .= ($pfE['MODE'] == 'square' ? ' [' : ' (');
							$html .= scripture_ref($pfE['REF'], /*is_ref:*/true, /*$str_showing:*/($pfE['DISPLAY'] ? $pfE['DISPLAY'] : null));
							$html .= ($pfE['MODE'] == 'square' ? ']' : ')');
						}
					}
				}			
				break;
	case 'SECTION':
                if(FORMAT == 'pdf') {
                    if($parse) $html .="\r\n\r\n<hr />\r\n\r\n";
                    break;
                }
				if($parse) {
					// adds section headers if parsing Whole Document
					if($url_section == '_' || $_GET['query']) {
						static $pi, $si;
						if($_GET['query']) {
							$working_title .= $document->sections[(int)$si]->request;
							$working_url = urlencode($working_title);
						} elseif($url_type == 'bible') {
							$working_url = $parts[(int)$pi]->sections[(int)$si]->id;
							$working_title = ($url_title == 'Psalms' ? 'Psalm ' : 'Chapter ').$working_url;
						} else {
							$working_title = $parts[(int)$pi]->sections[(int)$si]->title;
							// see also classes_doc.php
							// a music section gets a special prefix
							if($parts[(int)$pi]->sections[(int)$si]->type == 'music') {
								$working_prefix = 'MUSIC_';
							}
							$working_url = $working_prefix . title_url_format($working_title);
						}
						if($_GET['query']) {
							$sectionlink = '<a href="'.level_url().'search/?query=bible&amp;passage='.$working_url.'">'.$working_title.'</a>';
						} else {
							$sectionlink = '<a href="'.level_url().$url_type.'/'.title_url_format($url_title).'/'.$working_url.'/">'.apply_formatting($working_title).'</a>';
						}
						// query calls sections are based on the 'passage' argument, rather than 'section' part of the url
						if(!$_GET['query'] || count($document->sections) > 1) {
							$html .= "\r\n\r\n".'<div class="section_head'.(!$pi && !$si ? ' first' : '').'"><table><tr><td class="section_head"><div class="left">'.$sectionlink.'</div><div class="right"><p><a href="#logo" title="Return to top">^</a></p></div></td></tr></table></div>'."\r\n\r\n\r\n";
						}
					}
					if($_GET['query']) {
						// h1 heading already included in parsed data
					} elseif($url_type == 'bible') {
						$html .= "\r\n".'<h1>'.($url_title == 'Psalms' ? 'Psalm ' : 'Chapter ').$pfE['ID'].'</h1>';
					}
					
					if($si == count($parts[(int)$pi]->sections)-1) {
						$si = 0;
						$pi++;
					} else {
						$si++;
					}
				} else {
					// if within a default paragraph (p), end paragraph
					// only occurs in KJV
					if($parser_flags[2]['SECTION']['KJVp']) {
						$html .= '</ul>'."\r\n";
						$parser_flags[2]['SECTION']['KJVp'] = false;
					}
				}
				break;
	case 'SPAN':
				if($parse) {
					$html .= '<span class="'.$pfE['TYPE'].'">';
				} else {
					$html .= '</span>';
				}
				break;
	case 'TITLE':
				if($document->section == '') {
					$h_size = 'h2';
				} else {
					$h_size = 'h1';
				}
				if($parse) {
					// only occurs in BIBLE
					// if within a default paragraph (p), end paragraph
					if($parser_flags[2]['SECTION']['KJVp']) {
						$html .= '</ul>'."\r\n";
						$parser_flags[2]['SECTION']['KJVp'] = false;
					}
					$html .= '<'.$h_size.'>';
				} else {
					$html .= '</'.$h_size.'>'."\r\n";
				}
				break;
	case 'TOC': // this is the toc level 1 element, and should be ommited from the HTML
				break;
	case 'VERSE':
				if($url_type == 'bible') {
					// if not within a default paragraph (p), and a paragraph mark (pm) instruction has not been given, begin paragraph
					if(!$parser_flags[2]['SECTION']['KJVp'] && !$parser_flags[2]['SECTION']['KJVpm']) {
						$html .= '<ul class="KJV">';
						$parser_flags[2]['SECTION']['KJVp'] = true;
					}
					if($parse) {
						// SECTION elements are always level 2
						// if KJV paragraph marker has been set, add to next verse
						if($parser_flags[2]['SECTION']['KJVpm']) {
							// if within a default paragraph (p), end paragraph
							if($parser_flags[2]['SECTION']['KJVp']) {
								$html .= '</ul>'."\r\n";
								$parser_flags[2]['SECTION']['KJVp'] = false;
							}
							// if not within a default paragraph (p), begin paragraph
							if(!$parser_flags[2]['SECTION']['KJVp']) {
								$html .= '<ul class="KJV">'."\r\n";
								$parser_flags[2]['SECTION']['KJVp'] = true;
							}
							$parser_flags[2]['SECTION']['KJVpm'] = false;
							$paraline = '<span class="KJV_para">&#182; </span>';
						}
						$html .= '<li><span class="KJV_num">'.$pfE['ID'].'&nbsp;</span>'.$paraline;
					} else {
						$html .= '</li>';
					}
				} else {
					if($parse) {
						// if VERSE ID == "refrain" indicate as chorus
						if($pfE['ID'] == "refrain") {
							$html .= '<p class="verse refrain_line">Refrain:</p><p class="verse refrain">';
						} else {
							$html .= '<p class="verse">';
						}
					} else {
						// close verse
						$html .= "</p>\r\n";
						// if at the end of a verse, outside a note, place notes, if any
						if(!$parser_flags[1]['DOCUMENT']['NOTING'] && $parser_flags[1]['DOCUMENT']['NOTES']) {
							foreach($parser_flags[1]['DOCUMENT']['NOTES'] as $num => $note) {
								$html .= '<div class="note" id="note'.$num.'">'
									.'<p class="note-num noindent"><span>['.$num.']:</span></p>'
									.preg_replace("'^\s*<p>([\s\S]*)</p>\s*$'","<p><span class=\"hidden\">[</span>$1<span class=\"hidden\">]</span></p>",$note)
									."</div>\r\n";
							}
							$parser_flags[1]['DOCUMENT']['NOTES'] = false;
						}
					}
				}			
				break;
	case 'XHTML': // output exactly as written; contents should be written the same as for the HTML pre tag
				if($parse) {
					if($pfE['TYPE'] == 'code') {
						$html = '<blockquote class="pre"><div><pre>';
					}
				} else {
					if($pfE['TYPE'] == 'code') {
						$html .= '</pre></div></blockquote>';
					}
				}
				break;

	case 'ELEMENT': //case 'ELEMENT' only happens with element data
				// alias for referencing currently populated elements at the same level
				$pfEL = &$parser_flags[$pfL];

				// parse code
				if(isset($pfEL['XHTML'])) {
					$htmldata = stripslashes(rawurldecode($pfE['DATA']));
					if($pfEL['XHTML']['TYPE'] == 'code') {
						$htmldata = htmlentities($htmldata);
					}
					$html .= $htmldata;
					break;
				}

				// parse current title
				if(isset($pfEL['TITLE'])) {
					// if section type is music, add link
					// SECTION elements are always level 2
					if($parser_flags[2]['SECTION']['TYPE'] == 'music') {
						$url_st = title_url_format($pfE['DATA']);
						// if score is available, add score link
						if(file_exists('library/music/'.substr($url_st,0,1).'/'.$url_st.'/'.$url_st.'.sib')) {
							$html .= '<a class="score_link" href="'.level_url().'music/'.$url_st.'/score/" title="View Score Sheet Music">'
								.'<img src="'.level_url_webfront().'link_score.gif" alt="View Score Sheet Music" /></a>';
						}
						$html .= '<a href="'.level_url().'music/'.$url_st.'/" title="View Lyrics">'.apply_formatting($pfE['DATA']).'</a>';
						// end ELEMENT parsing
						break;
					}
				}

				// adds current link ELEMENT data to link TEXT
				if(isset($pfEL['LINK'])) {
					$pfEL['LINK']['TEXT'] .= $pfE['DATA'];
					// end ELEMENT parsing
					break;
				}
	
                if($pfEL['SCRIPTURE']['TYPE'] == 'quote' && FORMAT == 'pdf') {
                    preg_match("'^(.+)?([,.])$'",$pfE['DATA'],$m);
                    if ($m) {
                        $pfE['DATA'] = $m[1];
                        $pfEL['SCRIPTURE']['ENDPUNC'] = $m[2];
                    }
                }

				// if current scripture reference is direct, apply link
				if($pfEL['SCRIPTURE']['MODE'] == 'direct') {
					// if REF attribute is given, use it, otherwise use ELEMENT data
					$html .= scripture_ref( ($pfEL['SCRIPTURE']['REF'] ? $pfEL['SCRIPTURE']['REF'] : $pfE['DATA']) , /*is_ref:*/true, /*$str_showing:*/$pfE['DATA'], /*$str_scripture:*/null, /*$is_direct:*/true);
					// end ELEMENT parsing
					break;
				}

				$html .= apply_formatting($pfE['DATA']);
				break;
				
	default: // if not matching other elements, assuming standard HTML element; include style if given
				// be sure to check for empty items: (BR)
				preg_match("'>(BR)<'",'>'.$element.'<',$is_empty);
				if($parse) {
					$html .= '<'.strtolower($element).($is_empty[1] ? ' /' : '').($pfE['STYLE'] ? ' style="'.$pfE['STYLE'].'"' : '').'>';
				} elseif(!$is_empty[1]) {
					$html .= '</'.strtolower($element).'>';
				}
				break;
	}

	
	// if within link, add to link
	if($parse && $parser_flags[$pfL-1]['LINK']) {
		$parser_flags[$pfL-1]['LINK']['TEXT'] .= $html;
	// special case for within links
	} elseif(!$parse && $parser_flags[$pfL-1]['LINK']) {
		$parser_flags[$pfL-1]['LINK']['TEXT'] .= $html;
	// if within note, add to note
	} elseif($parser_flags[1]['DOCUMENT']['NOTING']) {
		$parser_flags[1]['DOCUMENT']['NOTES'][ ($parser_flags[1]['DOCUMENT']['NOTEID']) ] .= $html;
	} else {
		$do_return = true;
	}

	if(!$parse) {
		// if at end of element, delete level from array
		unset($parser_flags[$pfL]);
		// keeps track of element nesting level for object that can be nested, such as lists
		$parser_flags['level']--;
	}
	
	if($do_return) {
		return $html;
	}
}

// inserts link to scripture reference
function scripture_ref($str_reference, $is_ref = false, $str_showing = null, $str_scripture = null, $is_direct = false) {
	$arr_scrip_arabic_s = array(
			"'(^|\s)1 (Sam|Kin|Chr|Cor|The|Tim|Pet|Joh)'",	//books with ordinal [1 ]
			"'(^|\s)2 (Sam|Kin|Chr|Cor|The|Tim|Pet|Joh)'",	//books with ordinal [2 ]
			"'(^|\s)3 John'"								//books with ordinal [3 ]
			);
	$arr_scrip_arabic_r = array(
			"$1I&nbsp;$2",	//1
			"$1II&nbsp;$2",	//2
			"$1III&nbsp;John"	//3
			);
	$arr_scrip_roman_s = array(
			"'(\s?)III John'",								//books with ordinal [III ]
			"'(\s?)II (Sam|Kin|Chr|Cor|The|Tim|Pet|Joh)'",	//books with ordinal [II ]
			"'(\s?)I (Sam|Kin|Chr|Cor|The|Tim|Pet|Joh)'"	//books with ordinal [I ]
			);
	$arr_scrip_roman_r = array(
			"${1}3 John",	//3
			"${1}2 $2",	//2
			"${1}1 $2"	//1
			);

	//variable for the url reference in the Library
    $urlA =' href="http://'. (FORMAT == 'mobi' ? '' : NORMALIZED_LOCALHOST) .'bible.timelesstruths.org/'.(strlen($str_reference) ? '' : $str_scripture);
	$urlB ='"';
	//if reference is not given to show, show lookup reference
	if (!$str_showing) $str_showing = $str_reference;
	// converts digits to roman numerals for showing according to REGEXP arrays
//	$str_showing = preg_replace($arr_scrip_arabic_s,$arr_scrip_arabic_r, $str_showing);
    $str_showing = preg_replace($arr_scrip_roman_s,$arr_scrip_roman_r, $str_showing);
	// converts ASCII character 160 to " "
	$str_reference = str_replace(chr(160),' ', $str_reference);
	// convert roman numerals to digits for referencing according to REGEXP arrays, and encodes for url
	$str_reference = urlencode(preg_replace($arr_scrip_roman_s,$arr_scrip_roman_r, $str_reference));
    
	if($is_ref) {
	    if (FORMAT == 'mobi' || FORMAT == 'pdf') { // [2015-01] only localhost; requires PHP 5.3+
            if (!$is_direct) {
                $str_showing = scripture_abbreviate($str_showing);
            }

            return ($is_direct ? '' : '<span class="scriptref-inline">')//<span class="scriptlink">&lt;</span>'
                 . '<a class="scripture"'.$urlA . $str_reference . $urlB . '>'.$str_showing.'</a>'
                 . ($is_direct ? '' : /*'<span class="scriptlink">&gt;</span>*/'</span>');
	    } else {
            return '<a class="scripture"'.$urlA . $str_reference . $urlB . '>'.$str_showing.'</a>';
        }
	} else {
	    if (FORMAT == 'mobi') {
            return '<span class="scriptref-quote">'//<span class="scriptlink">&lt;</span>'
                 . '<a'.$urlA . $str_reference . $urlB . ' title="'.$str_showing.'"><sup>&dagger;</sup>'./*$str_showing.*/'</a>'
                 . /*'<span class="scriptlink">&gt;</span>*/'</span>';
        } else if (FORMAT == 'pdf') {
            return '<span> ('. scripture_abbreviate($str_showing) .')</span>';
	    } else {
            return '<a class="scripture script_ref"'.$urlA . $str_reference . $urlB . ' title="'.$str_showing.'"><span class="noprint">*</span><span class="inprint inline"> ('.$str_showing.')</span></a>';
        }
	}
}

//
function scripture_abbreviate($str_references) {
    global $db;
    db_connect($db);
    
    $str_references = preg_replace_callback(
        '|((?:[123] )?\w[\w\s]+)\s|',
        function ($m) {
            $str = $m[1];
            if ($str == 'Psalm') $str = 'Psalms'; // irregular 
            $r = mysql_fetch_object(mysql_query("SELECT print FROM bible_books WHERE name = '$str'"));
            if ($r->print) $str = str_replace(" ", "&nbsp;", $r->print);
            return "$str ";
        },
        $str_references
    );
    return $str_references;
}

//
function scripture_version($ver_abbr) {
    // array for defining acronyms in toottip
    $arr_versions = array(
        'AMP'  => 'Amplified Bible',
        'ASV'  => 'American Standard Version',
        'ESV'  => 'English Standard Version',
        'KJV'  => 'King James Version',
        'LXX'  => 'Septuagint',
        'MKJV' => 'Modern King James Version',
        'NASB' => 'New American Standard Bible',
        'NIV'  => 'New International Version',
        'NKJV' => 'New King James Version',
        'RV'   => 'Revised Version',
        'TCNT' => 'Twentieth Century New Testament',
        'WNT'  => 'Weymouth New Testament',
        'YLT'  => "Young's Literal Translation"
        );
    if (FORMAT == 'mobi') {
        return '<sup>'.$ver_abbr.'</sup>';
    } else {
        return '<span class="script_ver" title="'.$arr_versions[$ver_abbr].'">'.$ver_abbr.'</span>';
    }
}

?>