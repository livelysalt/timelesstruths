var TT = TT || {};
TT.M = TT.M || {
    format: (readCookie('m_score_format') == 'pdf' ? 'pdf' : 'sib'),
    zoom:   readCookie('m_score_zoom') || 760,
    notes:  (readCookie('m_score_notes') == '%2B' ? 'shaped' : 'standard')
};

function init() {
    init_score();
}

function init_score() {
    document.getElementById('format').onclick = function() { setScore('format',this); };
	document.getElementById('zoom').onclick   = function() { setZoom(this); };
    document.getElementById('notes').onclick  = function() { setScore('notes',this); };
//    document.getElementById('apply').style.visibility = 'hidden';

    $('.help-link .show, .help-link .hide').on('click', function(){
        $(this).parents('.panel-toggle').toggleClass('hide');
        return false;
    });
}

function setZoom( el ) {
    if (!$) return;
    var v = el.options[ el.selectedIndex ].value;
    if (TT.M.zoom == v) return;
    TT.M.zoom = v;
    createCookie('m_score_zoom', TT.M.zoom, 1000000);

	sWidth = TT.M.zoom;
	sHeight = sWidth * 1.3 + 26; // letter size ratio
	
	$('#score').width(sWidth).height(sHeight);
}

function setScore(attr, el) {
    if (!$) return;
    if(attr == 'format') {
        if(TT.M.format == el.value) return;
        TT.M.format = el.value;
        createCookie('m_score_format', (TT.M.format == 'pdf' ? 'pdf' : ''), 1000000);
    }
	if(attr == 'notes') {
        if(TT.M.notes == el.value) return;
        TT.M.notes = el.value;
        createCookie('m_score_notes', (TT.M.notes == 'shaped' ? '%2B'/*+*/ : ''), 1000000);
    }

    var data_src = 'data-src-'+ TT.M.format +'-'+ TT.M.notes,
        s        = $('#score'),
        s_src    = s.attr(data_src);
             
    if (s_src) {
        // see also _page.php
        s.html('<div class="wrapper">'
            + (TT.M.format == 'sib' ?
                '<object id="s_sib" type="application/x-sibelius-score"'
    		  + ' data="'+ s_src +'"><param name="src" value="'+ s_src +'" />'
    		  + '<param name="scorch_minimum_version" value="3000" /><param name="scorch_shrink_limit" value="100" /><param name="shrinkwindow" value="0" /></object>'
    		  :
 /* [2015-01-24] <object> won't fit-to-container in Chrome <http://forums.asp.net/t/1877403.aspx?Issue+with+embedded+pdf+object+in+chrome+browsers>
//  [2015-01-24] <object> recommended for Safari mobile    <http://stackoverflow.com/questions/19654577/html-embedded-pdf-iframe> 
    		    '<object id="s_pdf" type="application/pdf" data="'+ s_src +'">'
              + '<embed src="'+ s_src +'" type="application/pdf" />'
              + '</object>'
/*/              
                '<iframe id="s_pdf" frameborder="0" src="'+ s_src +'"></iframe>'
//*/                
    		  )
    		+ '</div>');
    } else {
        // adapted from _page.php
        var subjectline  = encodeURI("Request: "+ $('title').text().replace(/ >.+/,'') +" (."+ TT.M.format +")");
        var commentsline = encodeURI("I would like to have the ."+ TT.M.format +" sheet music for this song added to your priority list. Thank you.");
        s.html('<div class="wrapper">'
            + '<div class="error-message">'
            + '<p>Sorry, the sheet music is not available in this format (.'+ TT.M.format +'):</p>'
            + '<ul><li><a class="blue" href="'+ $('body').attr('data-level') +'contact/?subject='+ subjectline +'&amp;comments='+ commentsline +'">Request sheet music</a></li></ul>'
            + '</div>'
            + '</div>');
    }
		
	x=123;
}

addEvent(window, "load", init);
