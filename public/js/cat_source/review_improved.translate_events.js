if ( ReviewImproved.enabled() && !config.isReview ) {
(function($, root, RI, UI, undefined) {
    var db = window.MateCat.db;
    var issues = db.getCollection('segment_translation_issues');
    var versions = db.getCollection('segment_versions');
    var segments = db.getCollection('segments');

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
        var versions = MateCat.db.getCollection('segment_versions');
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

    $(window).on('segmentOpened', function( e ) {
        var segment = e.segment;

        ReviewImproved.versionsAndIssuesPromise( segment )
        .done(function(versions, issues) {
            updateVersionDependentViews( segment );
        });
    });

    $(document).on('segmentVersionChanged', function(e, segment) {
        updateVersionDependentViews( segment );
    });

})(jQuery, window, ReviewImproved, UI);
}
