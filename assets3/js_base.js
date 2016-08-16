// addEvent and cookie functions from scottandrew.com

function addEvent(obj, evType, fn){
  if (obj.addEventListener){
    obj.addEventListener(evType, fn, true);
    return true;
  } else if (obj.attachEvent){
    var r = obj.attachEvent("on"+evType, fn);
    return r;
  } else {
    return false;
  }
}

function createCookie(name,value,minutes)
{
	if (minutes)
	{
		var date = new Date();
		date.setTime(date.getTime()+(minutes*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
}

function readCookie(name)
{
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++)
	{
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function eraseCookie(name)
{
	createCookie(name,"",-1);
}

var tr_ = Object(), tr_el = Object(), tr_btn = Object();
function prepTranslate() {
	if(document.getElementById){
		tr_ = $('translate');
		tr_btn = $('btn_translate');
		tr_btn.addEvent('click', function(){
			if (!tr_.getProperty('open')) {
				tr_el = $('#google_translate')[0];
				if (!tr_el) { alert('Error: please refresh the page and try again.'); return; }
				var gt=document.createElement("script");gt.type="text/javascript";gt.async=true;gt.src="http://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit";
				var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(gt,s);
			}
			if (tr_.getProperty('open') == 'true') {
				tr_el.style.display = 'none';
				tr_btn.setText('Translate');
				tr_.setProperty('open','false');
			} else {
				tr_el.style.display = 'inline-block';
				tr_btn.setText('Hide');
				tr_.setProperty('open','true');
			}
			ga('send', 'event', 'Translate', (tr_.getProperty('open')?'show':'hide'));
		});
	}
}

function trackLink(link, category, action) {
	try {
		ga('send', 'event', category, action);
		setTimeout('window.location = "' + link.href + '"', 100);
	}catch(err){}
	return false;
}