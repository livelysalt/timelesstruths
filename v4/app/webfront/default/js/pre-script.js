var TT = {
    todo: [],
    run: function(mod,fn) {
        var fn = fn || 'init';
        if (TT[mod] != undefined && TT[mod][fn] != undefined) {
            TT[mod][fn]();
        }
    }
};

TT.Search = {
    init: function() {
        document.getElementById('search').select();
    }
};
