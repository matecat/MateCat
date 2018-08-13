/*
    Common events used in translation and revise page when Review Extended is active
 */

if (ReviewExtended.enabled()) {
    $(document).on('files:appended', function initReactComponents() {
        if (config.isReview) {
            loadDataPromise.done(function() {
                SegmentActions.mountTranslationIssues();
            });
        }

        // setTimeout(function () {
        //     if (config.isReview && UI.currentSegment && ReviewExtended.firstLoad ) {
        //         ReviewExtended.firstLoad = false;
        //         SegmentActions.openIssuesPanel(({sid: UI.getSegmentId(UI.currentSegment)}));
        //     }
        // });
    });

    $( window ).on( 'segmentClosed', function ( e ) {
        SegmentActions.closeSegmentIssuePanel(UI.getSegmentId(e.segment));
    } );

    /*
    To close the issue panel when clicking on different segment.
     TODO: we still dont know when a segment is opened in the Segment component,
     i need this trick to know when close the panel
     */
    $( window ).on( 'segmentOpened', function ( e ) {
        if ( $(e.segment.el).find('.review-balloon-container').length === 0 &&
            !$(e.segment.el).find('.revise-button').hasClass('open')) {
            UI.closeIssuesPanel();
        }
    } );

    $(document).on('translation:change', function(e, data) {
        if (data.sid === UI.getSegmentId(UI.currentSegment)) {
            UI.getSegmentVersionsIssues(data.sid, UI.getSegmentFileId(data.segment));
        }
    });

    var loadDataPromise = (function() {
        var issues =  sprintf(
            '/api/v2/jobs/%s/%s/translation-issues',
            config.id_job, config.password
        );

        var versions =  sprintf(
            '/api/v2/jobs/%s/%s/translation-versions',
            config.id_job, config.password
        );

        return $.when(
            $.getJSON( issues ).done(function( data ) {
                $(data.issues).each(function() {

                });
            }),

            $.getJSON( versions ).done(  )
        );
    })();
}