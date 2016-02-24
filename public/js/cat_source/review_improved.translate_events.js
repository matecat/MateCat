if ( ReviewImproved.enabled() && !config.isReview ) {
(function($, root, RI, UI, undefined) {
    var db = window.MateCat.db;

    var issues = db.segment_translation_issues ;
    var versions = db.segment_versions ;
    var segments = db.segments ;

    issues.on('update', issueRecordChanged);
    issues.on('insert', issueRecordChanged);

    versions.on('update', versionRecordChanged);
    versions.on('insert', versionRecordChanged);

    function issueRecordChanged( record ) {
        var segment = UI.Segment.find( record.id_segment );
        updateVersionDependentViews( segment );

    }

    function versionRecordChanged( record ) {
        var segment = UI.Segment.find( record.id_segment );
        updateVersionDependentViews( segment )
    }

    function updateHighlightArea( segment ) {
        var text = RI.getTranslationText( segment );
        var versions = MateCat.db.segment_versions;
        var revertingVersion = segment.el.data('revertingVersion');
        var textarea_container = template(
            'review_improved/translate_highlight_area', {
                decoded_translation : UI.decodePlaceholdersToText( text ),
                versions : versions.findObjects({ id_segment : segment.id }),
                revertingVersion : revertingVersion
            });

        segment.el.find('[data-mount=highlight-area]')
            .html( textarea_container );
    }

    function updateVersionDependentViews( segment ) {
        RI.updateIssueViews( segment );
        updateHighlightArea( segment );
    }


    $(document).on('segmentVersionChanged', function(e, segment) {
        updateVersionDependentViews( segment );
    });

    $(document).on('segment:change', function(e, data) {
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
