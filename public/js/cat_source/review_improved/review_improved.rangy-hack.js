if ( Review.enabled() && Review.type == 'improved' ) {
    window.rangy_backup = window.rangy ;
    window.rangy = {
        init: function() { },
        saveSelection: function() {}
    };
}
