function init() {
	init_notes();
	init_toclists();
}

function init_notes() {
	if (document.getElementsByTagName) {
		var a_note = document.getElementsByTagName("a");
	} else if (document.all) {
		var a_note = document.all.tags("a");
	}
	
	for (var i=0;i<a_note.length;i++) {
		if(a_note[i].className == "notefrom") {
			a_note[i].onclick = function () { popnote(this); };
			var el_ID = a_note[i].id.substr(4, a_note[i].id.length-4 );
			var div_note = document.getElementById('to' + el_ID);
			div_note.style.display = 'none';
		}
	}
}

function popnote(el) {
	var el_ID = el.id.substr(4, el.id.length-4 );

	var div_note = document.getElementById('to' + el_ID);
	div_note.style.display = 'block';
	
	var a_noteX = document.createElement("a");
	a_noteX.href = '#' + el.id;
	a_noteX.onclick = function() { div_note.style.display = 'none';	};
	a_noteX.title = 'close';
	var Xlink = document.createTextNode('[' + el_ID + ']:');
	a_noteX.appendChild(Xlink);

	for (var i=0;i<div_note.childNodes.length;i++) {
		if(div_note.childNodes[i].className == 'notenum noindent first') {
			var p_noteX = document.createElement('p');
			p_noteX.appendChild(a_noteX);
			p_noteX.style.borderBottom = '1px solid #ccc';
			p_noteX.className = 'noindent first';
			// replace [#] with [close]
			div_note.replaceChild(p_noteX,div_note.childNodes[i]);
		}
	}
}

function init_toclists() {
	if (document.getElementsByTagName) {
		var div_list = document.getElementsByTagName('div');
	} else if (document.all) {
		var div_list = document.all.tags('div');
	}
	
	for (var i=0;i<div_list.length;i++) {
		if(div_list[i].className == 'preface list') {
			if(readCookie('toc'+div_list[i].id) != 'open') {
				poplist(div_list[i].id, 'close');
			} else {
				poplist(div_list[i].id, 'opened');
			}
		}
	}
}

function poplist(id, action) {
	var div_list = document.getElementById(id);
	for (var i=0;i<div_list.childNodes.length;i++) {
		if(action == 'open') {
			if(div_list.childNodes[i].className == 'tab_closed') {
				div_list.childNodes[i].className = 'tab_open';
				div_list.childNodes[i].childNodes[0].childNodes[0].onclick = function () { poplist(this.href.substring(this.href.indexOf('#')+1,this.href.length), 'close'); return false; };
				div_list.childNodes[i].childNodes[0].childNodes[0].title = 'close list';
			} else if(div_list.childNodes[i].className == 'closed') {
				div_list.childNodes[i].className = 'open';
			}
			// keeps this list open for five minute
			createCookie('toc'+id,'open',5);
		// if already opened within the past minute (or whatever time was set by the cookie) 
		} else if(action == 'opened') {
			if(div_list.childNodes[i].className == 'tab_open') {
				div_list.childNodes[i].childNodes[0].childNodes[0].onclick = function () { poplist(this.href.substring(this.href.indexOf('#')+1,this.href.length), 'close'); return false; };
				div_list.childNodes[i].childNodes[0].childNodes[0].title = 'close list';
			}
		} else if(action == 'close') {
			if(div_list.childNodes[i].className == 'tab_open') {
				div_list.childNodes[i].className = 'tab_closed';
				div_list.childNodes[i].childNodes[0].childNodes[0].onclick = function () { poplist(this.href.substring(this.href.indexOf('#')+1,this.href.length), 'open'); };
				div_list.childNodes[i].childNodes[0].childNodes[0].title = 'open list';
			} else if(div_list.childNodes[i].className == 'open') {
				div_list.childNodes[i].className = 'closed';
			}
			// deletes cookies if list is closed
			eraseCookie('toc'+id);
		}
	}
}

addEvent(window, "load", init);
