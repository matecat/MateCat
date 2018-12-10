if ( ReviewImproved.enabled() || ReviewExtended.enabled() || ReviewExtendedFooter.enabled()) {

    $.extend(UI, {

        openIssuesPanel : function(data, openSegment) {
            var segment = (data)? UI.Segment.findEl( data.sid ): data;
            if (config.reviewType === "improved") {
                $('body').addClass('review-improved-opened');
                hackIntercomButton( true );
                SearchUtils.closeSearch();
            } else {
                if (segment && !Review.evalOpenableSegment( segment )) {
                    return false;
                }
                $('body').addClass('review-extended-opened');
                localStorage.setItem(ReviewExtended.localStoragePanelClosed, false);
            }
            $('body').addClass('side-tools-opened review-side-panel-opened');
            window.dispatchEvent(new Event('resize'));
            if (data && openSegment) {
                segment.find( UI.targetContainerSelector() ).click();
                window.setTimeout( function ( data ) {
                    var el = UI.Segment.find( data.sid ).el;

                    if ( UI.currentSegmentId != data.sid ) {
                        UI.focusSegment( el );
                    }

                    UI.scrollSegment( el );
                }, 500, data );
            }
        },

        closeIssuesPanel : function() {

            hackIntercomButton( false );
            SegmentActions.closeIssuesPanel();
            $('body').removeClass('side-tools-opened review-side-panel-opened review-extended-opened review-improved-opened');
            if (config.reviewType === "extended") {
                localStorage.setItem(ReviewExtended.localStoragePanelClosed, true);
            }
            if ( UI.currentSegment ) {
                setTimeout( function() {
                    UI.scrollSegment( UI.currentSegment );
                }, 100 );
            }
            window.dispatchEvent(new Event('resize'));
        },

        deleteIssue : function( issue, sid, dontShowMessage) {
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
            if ( !dontShowMessage) {
                APP.confirm({
                    name : 'Confirm issue deletion',
                    callback : 'deleteTranslationIssue',
                    msg: message,
                    okTxt: 'Yes delete this issue',
                    context: JSON.stringify({
                        id_segment : sid,
                        id_issue : issue.id
                    })
                });
            } else {
                UI.deleteTranslationIssue(JSON.stringify({
                    id_segment : sid,
                    id_issue : issue.id
                }));
            }
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

    $(window).on('segmentOpened', UI.getSegmentVersionsIssuesHandler);
}