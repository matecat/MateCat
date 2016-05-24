// common events
//
if ( ReviewImproved.enabled() ) {

    $(document).on('files:appended', function initReactComponents() {
        // Lock tags
        $('.errorTaggingArea').each(function() {
             UI.lockTags(this);
        });

        loadDataPromise.done(function() {
            $('section [data-mount=translation-issues-button]').each(function() {
                var segment = new UI.Segment( this )  ;
                if ( !segment.isSplit() || segment.isFirstOfSplit() ) {
                    ReactDOM.render( React.createElement( TranslationIssuesSideButton, {
                        sid : segment.absoluteId
                    } ), this );
                }
            });
        });
    });

    var putSegmentsInStore = function(data) {
        $.each(data.files, function() {
            $.each( this.segments, function() {
                MateCat.db.upsert( 'segments', 'sid', _.clone( this ) );
            });
        });
    }

    $(document).on('ready', function() {
        ReviewImproved.mountPanelComponent();
    });

    $(document).on('segments:load', function(e, data) {
        putSegmentsInStore( data );
    });

    $(document).on('segment-filter:filter-data:load', function() {
        ReviewImproved.closePanel();
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

    $( document ).on( 'keydown', function ( e ) {
        var esc = '27' ;
        if ( e.which == esc ) {
            if (!$('.modal').is(':visible')) {
                ReviewImproved.closePanel();
            }
        }
    });

    $(document).on('editingSegment:change', function(e, data) {
        if ( data.segment == null ) {
            ReviewImproved.closePanel();
        }
    });

    $(document).on('click', function( e ) {
        if (e.target.closest('body') == null ) {
            // it's a detatched element, likely the APPROVE button.
            return ;
        }
        if (e.target.closest('header, .modal, section, #review-side-panel') == null) {
            ReviewImproved.closePanel( );
        }
    });

    $(document).on('translation:change', function(e, data) {
        var versions_path =  sprintf(
            '/api/v2/jobs/%s/%s/segments/%s/translation-versions',
            config.id_job, config.password, data.sid
        );

        $.getJSON( versions_path ).done( updateLocalTranslationVersions );
    });

    $(document).on('translation:change', function(e, data) {
        var record = MateCat.db.segments.by('sid', data.sid);
        MateCat.db.segments.update( _.extend(record, data) );
    });

}
