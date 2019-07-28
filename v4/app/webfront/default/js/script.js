if (navigator.userAgent.match(/iPhone/i) || navigator.userAgent.match(/iPad/i)) {
    var viewportmeta = document.querySelectorAll('meta[name="viewport"]')[0];
    if (viewportmeta) {
        viewportmeta.content = 'width=device-width, minimum-scale=1.0, maximum-scale=1.0';
        document.body.addEventListener('gesturestart', function() {
            viewportmeta.content = 'width=device-width, minimum-scale=0.25, maximum-scale=1.6';
        }, false);
    }
}

//TT already defined
TT.Audio = {
    trackPercentPlayed: function(jp_event) {
        var pct = (jp_event.type == 'jPlayer_ended' ? 100 : Math.floor(jp_event.jPlayer.status.currentPercentAbsolute));
        ga('send', 'event', 'Audio', jp_event.type, $('.jp-audio-label').text(), pct);
    }
};

$(document).ready(function(){
    
    if ($('html').hasClass('ie6')) { return 'simple'; }
    //-----------------------------------------------
    
    $('.form-search').submit(function(){
        window.location = $('body').attr('data-relroot') + $(this).find('#search').val();
        return false;
    });
    
    $(document).on('click','.verse .w',function(x,y){
//    $('.verse .w').live('click',function(x,y){
        $(this).wrap('<a href="'+ $('body').attr('data-relroot') + $(this).attr('title') +'" />');
    });
    
    
    $('.strongs .nav-kjv-words').each(function(){
        var num = $(this).attr('data-num-words');
        if ($(this).hasClass('oversize')) { /* remove flash patch */
            $(this).addClass('list-hidden').removeClass('oversize');
        }
        if ($(this).hasClass('hideable')) {
            $(this).find('.caption').after('<span class="toggle-show">[<a href="#show" class="show"></a><a href="#hide" class="hide"></a>]</span>');
        }
        $(this).find('.show').text('show list of '+ num +' words...').click(function(){
            $(this).parents('.nav-kjv-words').removeClass('list-hidden');
            return false;
        });
        $(this).find('.hide').text('hide list').click(function(){
            $(this).parents('.nav-kjv-words').addClass('list-hidden');
            return false;
        });
    });
    
    
    $('head').append('<link rel="stylesheet" href="'+ $('body').attr('data-relroot') +'-/Bible/default/css/jplayer.skin.css" type="text/css" />');
    
    $('body').append('<div id="jquery_jplayer" class="jp-jplayer"></div> \
        <div id="jp_container" class="jp-audio"><div class="column"><div class="jp-dock"> \
            <div class="jp-type-single"> \
                <div class="jp-gui jp-interface"> \
                    <ul class="jp-controls"> \
                        <li><a href="#play" class="jp-play" title="play" tabindex="1"></a></li> \
                        <li><a href="#pause" class="jp-pause" title="pause" tabindex="1"></a></li> \
                        <!-- \
                        <li><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li> \
                        <li><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li> \
                        <li><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li> \
                        <li><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li> \
                        --> \
                        <li><a href="#download" class="jp-download" title="download" download="" tabindex="1"></a></li> \
                    </ul> \
                    <div class="jp-progress"> \
                        <div class="jp-seek-bar"> \
                            <div class="jp-play-bar"><span class="jp-audio-label"></span></div> \
                        </div> \
                    </div> \
                    <!-- \
                    <div class="jp-volume-bar"> \
                        <div class="jp-volume-bar-value"></div> \
                    </div> \
                    --> \
                    <div class="jp-current-time"></div> \
                    <div class="jp-duration"></div> \
                    <!-- \
                    <ul class="jp-toggles"> \
                        <li><a href="javascript:;" class="jp-repeat" tabindex="1" title="repeat">repeat</a></li> \
                        <li><a href="javascript:;" class="jp-repeat-off" tabindex="1" title="repeat off">repeat off</a></li> \
                    </ul> \
                    --> \
                </div> \
                <div class="jp-no-solution"> \
                    <span>Update Required</span> \
                    To play the media you will need to either update your browser to a recent version or update your <a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>. \
                </div> \
            </div> \
        </div><!--/.jp-dock--></div><!--/.column--></div>');

    var $JP = $("#jquery_jplayer").jPlayer({
        swfPath: $('body').attr('data-relroot') +'-/default/js/libs/',
        cssSelectorAncestor: "#jp_container",
        wmode: "window", // needed for FF 3.6
        ready: function() {
            
            $(".chapter-audio").text('').removeClass('no-jp').addClass('jp').attr('data-jp-state','paused').click(function(){
                var state = $(this).attr('data-jp-state');
                if (state == 'playing') {
                    $JP.jPlayer('pause');
                } else {
                    var audio = $JP.attr('data-audio');
                    if (audio != $(this).attr('href')) {
                        $JP.attr('data-audio', (audio = $(this).attr('href')) ).attr('data-a-id', $(this).attr('id') ).jPlayer("setMedia", {
                            mp3: audio
                        });
                    }
                    $JP.jPlayer('play');
                    // clear any other playing
                    $('[data-jp-state]').not($(this)).removeAttr('data-jp-state');
                }
                $('#jp_container').addClass('open').find('.jp-audio-label').text( $(this).parents('.chapter-title').find('span').text() );
                $('#jp_container').find('.jp-download').attr('href',audio);
                return false;
            });
            $('#jp_container').find('.jp-download').click(function(){
                window.location = $(this).attr('href');
            });
            
        },
        play: function(event) {
            $('[data-jp-state="paused"]').attr('data-jp-state','playing');
            TT.Audio.trackPercentPlayed(event);
        },
        pause: function(event) {
            $('[data-jp-state="playing"]').attr('data-jp-state','paused');
            TT.Audio.trackPercentPlayed(event);
        },
        ended: function(event) {
            $('[data-jp-state="playing"]').attr('data-jp-state','paused');
            TT.Audio.trackPercentPlayed(event);
        }
    });

}); // end ready()
