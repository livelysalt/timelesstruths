$(document).ready(function(){
    
    $('html').addClass('dev');
    
    $('body').append('<div class="dev-switch" />').prepend('<div class="is-dev dev-v-width"><hr /></div>');
    
    if ($.cookie('dev-off')) {
        $('body').addClass('dev-off');
    }
    
    $('.dev-switch').click(function(){
        $('body').toggleClass('dev-off');
        $.cookie('dev-off', ($('body').hasClass('dev-off') ? 'true' : null));
    });

});
