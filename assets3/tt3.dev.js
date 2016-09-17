/* use http://refresh-sf.com/ for production */

/*************************************************************************************************/
// [2015-02-20] added
/*!
 * jQuery Cookie Plugin v1.4.1
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2006, 2014 Klaus Hartl
 * Released under the MIT license
 * 
 * Examples:
 * set: $.cookie('name', 'value', { expires: 7 [days], path: '/', domain: 'example.com', secure: true });
 * get: $.cookie('name');
 * del: $.removeCookie('name');
 */
(function (factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD
        define(['jquery'], factory);
    } else if (typeof exports === 'object') {
        // CommonJS
        factory(require('jquery'));
    } else {
        // Browser globals
        factory(jQuery);
    }
}(function ($) {

    var pluses = /\+/g;

    function encode(s) {
        return config.raw ? s : encodeURIComponent(s);
    }

    function decode(s) {
        return config.raw ? s : decodeURIComponent(s);
    }

    function stringifyCookieValue(value) {
        return encode(config.json ? JSON.stringify(value) : String(value));
    }

    function parseCookieValue(s) {
        if (s.indexOf('"') === 0) {
            // This is a quoted cookie as according to RFC2068, unescape...
            s = s.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
        }

        try {
            // Replace server-side written pluses with spaces.
            // If we can't decode the cookie, ignore it, it's unusable.
            // If we can't parse the cookie, ignore it, it's unusable.
            s = decodeURIComponent(s.replace(pluses, ' '));
            return config.json ? JSON.parse(s) : s;
        } catch(e) {}
    }

    function read(s, converter) {
        var value = config.raw ? s : parseCookieValue(s);
        return $.isFunction(converter) ? converter(value) : value;
    }

    var config = $.cookie = function (key, value, options) {

        // Write

        if (arguments.length > 1 && !$.isFunction(value)) {
            // [2015-02-21] customized
            if (typeof options === 'number') {
                options = {
                    expires: options,
                    path: '/'
                };
            }
            //^^^

            options = $.extend({}, config.defaults, options);
            
            if (typeof options.expires === 'number') {
                var days = options.expires, t = options.expires = new Date();
                t.setTime(+t + days * 864e+5);
            }
            
            // [2015-02-21] customized
            if (value === null) {
                return $.removeCookie(key, options);
            }
            //^^^

            return (document.cookie = [
                encode(key), '=', stringifyCookieValue(value),
                options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
                options.path    ? '; path=' + options.path : '',
                options.domain  ? '; domain=' + options.domain : '',
                options.secure  ? '; secure' : ''
            ].join(''));
        }

        // Read

        var result = key ? undefined : {};

        // To prevent the for loop in the first place assign an empty array
        // in case there are no cookies at all. Also prevents odd result when
        // calling $.cookie().
        var cookies = document.cookie ? document.cookie.split('; ') : [];

        for (var i = 0, l = cookies.length; i < l; i++) {
            var parts = cookies[i].split('=');
            var name = decode(parts.shift());
            var cookie = parts.join('=');

            if (key && key === name) {
                // If second argument (value) is a function it's a converter...
                result = read(cookie, value);
                break;
            }

            // Prevent storing a cookie that we couldn't decode.
            if (!key && (cookie = read(cookie)) !== undefined) {
                result[name] = cookie;
            }
        }

        return result;
    };

    config.defaults = {};

    $.removeCookie = function (key, options) {
        if ($.cookie(key) === undefined) {
            return false;
        }

        // Must not alter options, thus extending a fresh object...
        $.cookie(key, '', $.extend({}, options, { expires: -1 }));
        return !$.cookie(key);
    };

}));

/*************************************************************************************************/
// [2016-07-08]
// on ready...
// [adapted from: https://gist.github.com/Arty2/11199162]
(function($) {
    $.fn.jphandle = function(o) {
        o.axis = (o.axis == "y" ? "y" : "x");

        this.on('mousedown touchstart', function(e) {
            var $dragbar = $(this).parents('.jp-handle-bar').addClass('jp-handle-dragging'),
                minX = $dragbar.offset().left;
            t = undefined;

            $(window).on('mousemove.jphandle touchmove.jphandle', function(e) {
                var x = e.pageX,
                    maxX = minX + $dragbar.width();
                //width could change (based on loading state) during drag

                if (x < minX)
                    x = minX;
                if (x > maxX)
                    x = maxX;
                var pct = ((x - minX) / (maxX - minX)) * 100;

                if ( typeof $JP != 'undefined')
                    $JP.jPlayer("playHead", pct);

                e.preventDefault();
            }).one('mouseup touchend touchcancel', function() {
                $(this).off('mousemove.jphandle touchmove.jphandle click.jphandle');
                $dragbar.removeClass('jp-handle-dragging');
                $('.jp-handle').focus();
            });

            e.preventDefault();
        });
        return this;
    };
})(jQuery); 


//=================================================================================================

var TT = TT || {};

//=================================================================================================

// [2015-02-20] added
TT.Utility = {
    initTranslate: function() {
        $('#btn_translate').on('click', function(){
            $el = $('#translate');
            if (!$el.hasClass('init')) {
                $.getScript("http://translate.google.com/translate_a/element.js?cb=TT.Utility.initGoogleTranslate");
                $el.addClass('init');
            }
            $(this).hide();
            $el.toggleClass('open');
            ga('send', 'event', 'Translate', ($el.hasClass('open') ? 'show' : 'hide'));
        });
    }, // initTranslate()

    initGoogleTranslate: function(){
        new google.translate.TranslateElement({pageLanguage:"en"},"google_translate");
    }, // initGoogleTranslate()
    
    initTOC: function() {
        $('.toc .collapsible').each(function(){
            var id = $(this).attr('id');
            if ($.cookie('t_'+ id) != 'open') {
                $(this).addClass('min');
            }
        }).on('click',function(){
            var id = $(this).attr('id');
            $(this).toggleClass('min');
            $.cookie('t_'+ id, ($(this).hasClass('min') ? null : 'open'), {expires:1/24/12}); // 5 minutes, only on this page
            return false;
        });
    }, // initTOC()
    
    initNotes: function() {
        $('.page .note-ref').on('click',function(){
            $el = $($(this).attr('href'));
            $el.css('top', $(this).position().top);
            $el.toggle();
            if ((b = +$el.css('bottom').replace('px','')) < 0) {
                $el.css('top', $el.position().top + b);
            }
            return false;
        });
        $('.page .note-num').on('click',function(){
            $(this).parents('.note').hide();
        });
    }, // initNotes()
        
    trackLink: function(link, category, action) {
        try {
            ga('send', 'event', category, action);
            setTimeout('window.location = "' + link.href + '"', 100);
        } catch(err){}
        return false;
    } // trackLink()
};

//=================================================================================================

// [2015-02-21] added
TT.Music = {
    format: ($.cookie('m_score_format') == 'pdf' ? 'pdf' : 'sib'),
    zoom:    $.cookie('m_score_zoom') || 760,
    notes:  ($.cookie('m_score_notes') == '+'/*'%2B'*/ ? 'shaped' : 'standard'),
    
    //---------------------------------------------------------------------------------------------
    
    init: function() {
        this.initScore();
    }, // init()

    initScore: function() {
        $('#score-format').on('click',function(){
            TT.Music.setScore('format',$(this).val());
        });
        $('#score-notes').on('click',function(){
            TT.Music.setScore('notes',$(this).val());
        });
        $('#score-zoom').on('click',function(){
            TT.Music.setZoom($(this).val());
        });
    
        $('.help-link .show, .help-link .hide').on('click', function(){
            $el = $(this).parents('.panel-toggle');
            $el.toggleClass('hide');
            ga('send', 'event', 'Score', ($el.hasClass('hide') ? 'hide' : 'show') + ' help');
            return false;
        });
    }, // initScore()
    
    setZoom: function(val) {
        if (this.zoom == val) return;
        this.zoom = val;
        $.cookie('m_score_zoom', this.zoom, 1000);
    
        var w = this.zoom,
            h = w * 1.3 + 30; // letter size ratio -- offset tall enough to eliminate scrollbars on Chrome
        
        $('#score').width(w).height(h);
    }, // setZoom()
    
    setScore: function(attr, val) {
        var default_val = $('[name="'+ attr +'"]').attr('data-default');
        if (attr == 'format') {
            if (this.format == val) return;
            this.format = val;
            $.cookie('m_score_format', (this.format != default_val ? this.format : null), 1000);
        }
        if (attr == 'notes') {
            if (this.notes == val) return;
            this.notes = val;
            $.cookie('m_score_notes', (this.notes == 'shaped' ? '+'/*%2B'/*+*/ : null), 1000);
        }
    
        var data_src = 'data-src-'+ this.format +'-'+ this.notes,
            s        = $('#score'),
            s_src    = s.attr(data_src);
                 
        if (s_src) {
            // see also _page.php
            s.html('<div class="wrapper">'
                + (this.format == 'pdf' ?
/*                  
// [2015-01-24] <object> won't fit-to-container in Chrome <http://forums.asp.net/t/1877403.aspx?Issue+with+embedded+pdf+object+in+chrome+browsers>
// [2015-01-24] <object> recommended for Safari mobile    <http://stackoverflow.com/questions/19654577/html-embedded-pdf-iframe> 
                    '<object id="s_pdf" type="application/pdf" data="'+ s_src +'">'
                  + '<embed src="'+ s_src +'" type="application/pdf" />'
                  + '</object>'
/*/              
                  '<iframe id="s_pdf" frameborder="0" src="'+ s_src +'"></iframe>'
//*/                
                  : '')
                + (this.format == 'sib' ?
                    '<object id="s_sib" type="application/x-sibelius-score"'
                  + ' data="'+ s_src +'"><param name="src" value="'+ s_src +'" />'
                  + '<param name="scorch_minimum_version" value="3000" /><param name="scorch_shrink_limit" value="100" /><param name="shrinkwindow" value="0" /></object>'
                  : '')
                + '</div>');
        } else {
            // adapted from _page.php
            var subject  = encodeURI("Request: "+ $('title').text().replace(/ >.+/,'') +" (."+ this.format +")");
            var comments = encodeURI("I would like to have the ."+ this.format +" sheet music for this song added to your priority list. Thank you.");
            var url      = encodeURI(window.location.href);
            s.html('<div class="wrapper">'
                + '<div class="error-message">'
                + '<p>Sorry, the sheet music is not available in this format (.'+ TT.Music.format +'):</p>'
                + '<ul><li><a class="blue" href="'+ $('body').attr('data-level') +'contact/?subject='+ subject +'&amp;comments='+ comments +'&amp;url='+ url +'">Request sheet music</a></li></ul>'
                + '</div>'
                + '</div>');
        }
        
    } // setScore()
};

//=================================================================================================
// [2016-07-08]
TT.Player = {
    d_cookie: {
        expires: 1000,
        path: '/',
        domain: window.location.hostname.replace(/^[^.]*(\.timelesstruths.org)/, "$1"),
    },
    selector: '.use-player',
    ready   : false,
    visible : false,
    href    : '',
    quality : ($.cookie('t_audio_quality') == 'low' ? 'low' : 'standard'),

    jp_options : {
        swfPath : $('#jp').attr('data-swfpath'),
        cssSelectorAncestor : "#player",
        wmode : "window", // needed for FF 3.6
        remainingDuration : true,
        toggleDuration : true,
        autoBlur : false,
        ready : function() {
            $(TT.Player.selector).click(function() {
                TT.Player.toggle($(this).attr('href'));
                return false;
            });
            $('.jp-handle').keydown(function(e) {
                e.preventDefault();
                // throttles events to 0.5 sec intervals
                if (TT.Player.key_active) {
                    return;
                }
                TT.Player.key_active = true;
                window.setTimeout(function(){
                    TT.Player.key_active = false;
                },500);
                
                var pct = $JP.data('jPlayer').status.currentPercentAbsolute;
                switch (e.key) {
                case 'ArrowLeft':
                    $JP.jPlayer("playHead", pct - 5); break;
                case 'ArrowRight':
                case ' ':
                    $JP.jPlayer("playHead", pct + 5); break;
                case 'Home':
                    $JP.jPlayer("playHead", 0); break;
                case 'End':
                    $JP.jPlayer("playHead", 99.9);
                    TT.Player.do('pause'); break;
                case 'Enter':
                    TT.Player.toggle(); break;
                }

                console.log(e.key, $JP.data('jPlayer').status);
            });
            $('.jp-play').on('click keydown', function(e) {
                if (e.type == 'click' || e.key == 'Enter') {
                    TT.Player.toggle();
                    e.preventDefault();
                }
                console.log('jp-play', $JP.data('jPlayer'));
            });
            $('#player').addClass('jp-ready');
        },
        error : function(event) {
            var err = event.jPlayer.error;
            $('.jp-error').addClass('show').html('<p class="msg">' + err.message + '</p>' + '<p class="context">(' + err.context + ')</p>');
            console.log('error', event.jPlayer.error, event);
        },
        progress : function(event) {
            console.log('progress', event.jPlayer.status.seekPercent);
        },
        timeupdate : function(event) {
            //console.log(event.jPlayer.status);
        },
        play : function(event) {
            $('[href="'+ TT.Player.href +'"]').toggleClass('is-playing',true);
            TT.Player.trackPercentPlayed(event);
            //$('[data-state="paused"]').attr('data-state', 'playing');
        },
        pause : function(event) {
            $('[href="'+ TT.Player.href +'"]').toggleClass('is-playing',false);
            TT.Player.trackPercentPlayed(event);
            //$('[data-state="playing"]').attr('data-state', 'paused');
        },
        ended : function(event) {
            $('[href="'+ TT.Player.href +'"]').toggleClass('is-playing',false);
            TT.Player.trackPercentPlayed(event);
            //$('[data-state="playing"]').attr('data-state', 'paused');
            //$JP.jPlayer("playHead", 99.99); // will reset to 0 by default
        },
        canplay : function(event) {
            $('.jp-error').removeClass('show').html('');
        },
    }, // jp_options {}

    init : function(options) {
        
        if (!$('.use-player').length) return;
           
        $('body').append('<div id="jp" data-swfpath="https://cdn.jsdelivr.net/jplayer/2.9.2/"></div>'
            + '<div id="player">'
            + '  <div class="jp-gui">'
            + '    <ul class="jp-controls">'
            + '      <li class="jp-control jp-play"><button class="jp-btn" title="play" aria-label="play" tabindex="2">'
//          + '        <svg class="icon-play" width="24" viewBox="0 0 20 20"><path d="M4,0 l 16,10 -16,10 z"></svg>'
            + '        <svg class="icon-play" width="28" viewBox="0 0 28 28"><path d="M6,2 l 21,12 -21,12 z"></path></svg>'
//          + '        <svg class="icon-pause" width="24" viewBox="0 0 20 20"><path d="M2,0 h6 v20 h-6 v-20 z M12,0 h6 v20 h-6 v-20 z"></svg>'
            + '        <svg class="icon-pause" width="28" viewBox="0 0 28 28"><path d="M4,2 h7 v24 h-7 v-24 z M17,2 h7 v24 h-7 v-24 z"></path></svg>'
            + '      </button></li>'
            + '      <li class="jp-control jp-quality"><label>Quality: </label><select tabindex="3">'
            + '        <option value="standard">standard</option>'
            + '        <option value="low">dial-up</option>'
            + '      </select></li>'
            + '      <li class="jp-control jp-download"><a href="#download" class="jp-btn" title="download" tabindex="4">'
            + '        <svg class="icon-download" width="16" viewBox="0 0 16 16"><path d="M5,1 h6 v5 h3 l-6,6 -6,-6 h3 z M2,13 h12 v2 h-12 z"></svg>'
            + '      </a></li>'
            + '    </ul>'
            + '    <div class="jp-progress">'
            + '      <div class="jp-seek-bar jp-handle-bar">'
            + '        <div class="jp-play-bar">'
            + '          <a href="#handle" class="jp-handle" tabindex="1"></a>'
            + '        </div>'
            + '      </div>'
            + '    </div>'
            + '    <div class="jp-time">'
            + '      <div class="jp-current-time" aria-label="time" role="timer"></div>'
            + '      <div class="jp-duration" aria-label="duration" role="timer" title="toggle total/remaining time"></div>'
            + '    </div>'
            + '  </div><!--/.jp-gui-->'
            + '  <div class="jp-error"></div>'
            + '  <div class="jp-no-solution">'
            + '    <span>Update Required</span>'
            + '    To play the media you will need to either update your browser to a recent version or update your <a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>.'
            + '  </div><!--/.jp-no-solution-->'
            + '</div><!--/#player-->'
        );
        
        $('.jp-handle').jphandle({
            axis : "x"
        });
        $('.jp-control select').val(this.quality).on('change', function() {
            TT.Player.set('quality', $(this).val());
        });
        $JP = $("#jp").jPlayer(TT.Player.jp_options);
        
        // [2016-07-14] used during transition period when forwarding from previous audio pages
        if ((href = $.cookie('player-load-href'))) {
            this.load(href);
            $.cookie('player-load-href',null,-1);
        }
    }, // init()

    do : function(event) {
        $JP.jPlayer(event);
    }, // do()
    
    show: function() {
        this.visible = true;
        $('html').addClass('player-visible');
    }, // show()
    
    load: function(href) {
        if (href && href != this.href) {
            this.show();
            this.href = href;
            $('.jp-download [href]').attr('href', this.href);
            $('#player').attr('data-href', this.href);
            $JP.jPlayer("setMedia", {
                mp3 : this.href
            });
        }
    },

    toggle : function(href) {
        this.load(href);

        var paused = $JP.data('jPlayer').status.paused;

        this.do( paused ? 'play' : 'pause');
        $('.jp-play .jp-btn').attr('title', (!paused ? 'play' : 'pause')).attr('aria-label', (!paused ? 'play' : 'pause'));
        //$('[href="'+ this.href +'"]').toggleClass('is-playing',paused);
        console.log('toggle', !paused);
    }, // toggle()

    set : function(option, value) {
        if (option == 'quality') {
            $.cookie('t_audio_quality', (value == 'low' ? 'low' : null), TT.Player.d_cookie);
            // days
        }
    }, // set()

    trackPercentPlayed: function(jp_event) {
        var pct = (jp_event.type == 'jPlayer_ended' ? 100 : Math.floor(jp_event.jPlayer.status.currentPercentAbsolute));
        ga('send', 'event', 'Audio', jp_event.type, TT.Player.href.replace(/^.*\//,''), pct);
    }, // trackPercentPlayed()
};

//=================================================================================================
// [2016-04-16]
TT.Admin = {
    init: function() {
        $('html').addClass('admin');
        this.initEditable();
    }, // init()
    
    initEditable: function() {
        $('[data-editable]').append('<a class="edit" title="edit" href="#edit"></a>');
        $('[data-editable]').on('click','.edit',function(){
            if ($(this).parents('[data-editable]').attr('data-status') == 'loaded') return false;

            TT.Admin.edit($(this).parent(), 'get', function(data){
                $('[data-editable="'+ data.edit +'"]').data('originalValue',data.value).attr('data-status','loaded')
                    .find('.edit').before('<textarea>'+ data.value +'</textarea>');
                console.log('get',data);
            });
            return false;
        });
        $('[data-editable]').on('change', 'textarea', function(){
            var modified = 'true',
                v = $(this).val(),
                p = new DOMParser(),
                d = p.parseFromString('<xml>'+ v +'</xml>', "text/xml");
                
            if (d.documentElement.nodeName == 'parsererror') {
                modified = 'invalid';
            }
            
            $(this).attr('data-modified',modified);
        });
        $('[data-editable]').on('click','[data-modified] + .edit',function(){
            TT.Admin.edit($(this).parent(), 'set', function(data){
                console.log('set',data);
                window.location.reload();
            });
        });
    }, // initEditable()
    
    edit: function($el, action, fn) {
        var edit = $el.attr('data-editable'),
            url  = 'http://localhost/admin.timelesstruths.org/Editable',
            data = {view:false, action:action, edit:edit};
            
        if (action == 'set') {
            var $el = $el.find('textarea');
            if ($el.attr('data-modified') == 'invalid') return false;
            data.value = escape($el.val());
        }

        $.get(url, data, fn, 'json');
    } // edit()
};

//=================================================================================================

// [2016-03-28] added
TT.Dev = {
    init: function() {
        $('body').append("<div id=\'dev\'>Dev "
            + "<select data-opt=\'dev\'>"
            + "<option value='master'>master</option>"
            + "<option value='bootstrap'>bootstrap</option>"
            + "<optgroup>"
            + "<option value=''>exit &times;</option>"
            + "</optgroup>"
            + "</select>");
        $('#dev [data-opt="dev"]').on('change',function(){
            var v = $(this).val();
            console.log('v:',v);
            $.cookie('dev', (v ? v : null), {path:'/',expires:30/*days*/});
            window.location.reload();
        }).val($.cookie('dev'));
    }, // init()
};

//=================================================================================================
// on ready...
$(function(){
    if ($('html').hasClass('dev') || $.cookie('dev')) {
        TT.Dev.init();
    }

    TT.Utility.initNotes();
    
    TT.Player.init();
    
    if (window.location.hostname == 'localhost') {
        TT.Admin.init();
    }
});
