if ( ReviewImproved.enabled() || ReviewExtended.enabled() || ReviewExtendedFooter.enabled()) {

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
            SearchUtils.closeSearch();

            $('body').addClass('side-tools-opened review-side-panel-opened');
            window.dispatchEvent(new Event('resize'));
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
            window.dispatchEvent(new Event('resize'));
        },

        deleteIssue : function( issue ) {
            var message = '';
            if ( issue.target_text ) {
                message = sprintf(
                    "You are about to delete the issue on string <span style='font-style: italic;'>'%s'</span> " +
                    "posted on %s." ,
                    issue.target_text,
                    moment( issue.created_at ).format('lll')
                );
            } else {
                message = sprintf(
                    "You are about to delete the issue posted on %s." ,
                    moment( issue.created_at ).format('lll')
                );
            }

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
        },
        reloadQualityReport : function() {
            var path  = sprintf('/api/v2/jobs/%s/%s/quality-report',
                config.id_job, config.password);

            $.getJSON( path )
                .done( function( data ) {
                    var review = data['quality-report'].chunk.review ;

                    window.quality_report_btn_component.setState({
                        is_pass : review.is_pass,
                        score : review.score,
                        percentage_reviewed : review.percentage
                    });
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

    $(document).ready(function() {
        UI.mountPanelComponent();
    });

    $(window).on('segmentOpened', UI.getSegmentVersionsIssuesHandler);
}