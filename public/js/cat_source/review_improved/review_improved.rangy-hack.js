if ( Review.enabled() && ReviewImproved.enabled() ) {
    window.rangy_backup = window.rangy ;
    window.rangy = {
        init: function() { },
        saveSelection: function() {}
    };
}
