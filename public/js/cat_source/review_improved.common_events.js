// common events
//
if ( ReviewImproved.enabled() ) {

    $(document).on('files:appended', function initReactComponents() {

        loadDataPromise.done(function() {
            SegmentActions.mountTranslationIssues();
        });
    });

    var issuesPanelSideButtonEnabled = function( segment ) {
        return segment.isICELocked() || (
            !segment.isReadonly() && ( !segment.isSplit() || segment.isFirstOfSplit() )
        );
    }

    $(document).on('segment-filter:filter-data:load', function() {
        UI.closeIssuesPanel();
    });

    var updateLocalTranslationVersions = function( data ) {
        $(data.versions).each(function() {
            MateCat.db.upsert('segment_versions', 'id', this ) ;
        });
    };

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
                    MateCat.db.upsert('segment_translation_issues',
                                  'id', this ) ;
                });
            }),

            // jQuery oddity here: function must be passed in array,
            // maybe because we are inside when. Otherwise it doesn't get
            // fired.
            $.getJSON( versions ).done( [ updateLocalTranslationVersions ] )
        );
    })();

    $(document).on('editingSegment:change', function(e, data) {
        if ( data.segment == null ) {
            UI.closeIssuesPanel();
        }
    });

    $(document).on('click', function( e ) {
        if ($(e.target).closest('body') == null ) {
            // it's a detatched element, likely the APPROVE button.
            return ;
        }
        if ($(e.target).closest('header, .modal, section, #review-side-panel') == null) {
            UI.closeIssuesPanel( );
        }
    });

    $(document).on('translation:change', function(e, data) {
        var versions_path =  sprintf(
            '/api/v2/jobs/%s/%s/segments/%s/translation-versions',
            config.id_job, config.password, data.sid
        );

        $.getJSON( versions_path ).done( updateLocalTranslationVersions );
    });

    $(document).on('sidepanel:close', function() {
        UI.closeIssuesPanel();
    });

}
