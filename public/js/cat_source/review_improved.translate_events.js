if ( ReviewImproved.enabled() && !config.isReview ) {
(function($, root, RI, UI, undefined) {
    var db = window.MateCat.db;

    $(document).on('translation:change', function(e, data) {
        var segment = UI.Segment.find( data.sid );
        UI.createButtons( segment );
    });

    $(document).on('buttonsCreation', function() {
        var div = $( '<ul>' + UI.segmentButtons + '</ul>' );

        div.find( '.translated' ).text( 'FIXED' )
            .removeClass( 'translated' )
            .addClass( 'fixed' );

        div.find( '.next-untranslated' ).parent().remove();
    });

})(jQuery, window, ReviewImproved, UI);
}
