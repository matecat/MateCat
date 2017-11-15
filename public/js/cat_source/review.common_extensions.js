if ( ReviewImproved.enabled() || ReviewExtended.enabled()) {

    /**
     * Split segment feature is not compatible with ReviewImproved.
     */
    window.config.splitSegmentEnabled = false;


    $.extend(UI, {

        mountPanelComponent : function() {
            UI.issuesMountPoint =   $('[data-mount=review-side-panel]')[0];
            ReactDOM.render(
                React.createElement( ReviewSidePanel, {
                    closePanel: this.closeIssuesPanel,
                    reviewType: Review.type,
                    isReview: config.isReview
                } ),
                UI.issuesMountPoint );
        },

        unmountPanelComponent : function() {
            ReactDOM.unmountComponentAtNode( UI.issuesMountPoint );
        },

        openIssuesPanel : function(data) {
            UI.closeSearch();

            $('body').addClass('side-tools-opened review-side-panel-opened');
            hackIntercomButton( true );

            var segment = UI.Segment.findEl( data.sid );
            segment.find( UI.targetContainerSelector() ).click();

            window.setTimeout( function(data) {
                var el = UI.Segment.find( data.sid ).el ;

                if ( UI.currentSegmentId != data.sid ) {
                    UI.focusSegment( el );
                }

                UI.scrollSegment( el );
            }, 500, data);
        },

        closeIssuesPanel : function() {

            hackIntercomButton( false );
            SegmentActions.closeIssuesPanel();
            $('body').removeClass('side-tools-opened review-side-panel-opened');

            if ( UI.currentSegment ) {
                setTimeout( function() {
                    UI.scrollSegment( UI.currentSegment );
                }, 100 );
            }
        },

        deleteIssue : function( issue ) {
            var message = sprintf(
                "You are about to delete the issue on string <span style='font-style: italic;'>'%s'</span> " +
                "posted on %s." ,
                issue.target_text,
                moment( issue.created_at ).format('lll')
            );

            APP.confirm({
                name : 'Confirm issue deletion',
                callback : 'deleteTranslationIssue',
                msg: message,
                okTxt: 'Yes delete this issue',
                context: JSON.stringify({
                    id_segment : issue.id_segment,
                    id_issue : issue.id
                })
            });
        }

    });

    $( document ).on( 'keydown', function ( e ) {
        var esc = '27' ;
        if ( e.which == esc ) {
            if (!$('.modal').is(':visible')) {
                UI.closeIssuesPanel();
            }
        }
    });

    $(document).on('header-tool:open', function(e, data) {
        if ( data.name == 'search' ) {
            UI.closeIssuesPanel();
        }
    });

    $(document).on('ready', function() {
        UI.mountPanelComponent();
    });

    $(window).on('segmentOpened', UI.getSegmentVersionsIssuesHandler);
}