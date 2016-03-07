/**
 * Main file of review_improved feature.
 *
 * This file is divided in two parts: the first is for the
 * initialization of the review interface, the second for
 * initialization of translate page.
 *
 * For translate and review pages two different tabs are configured
 * for the segment footer.
 *
 * For translate page, review specific statuses are not offered.
 *
 */
ReviewImproved = window.ReviewImproved || {};

ReviewImproved.enabled = function() {
    return Review.type == 'improved';
}

if ( ReviewImproved.enabled() )
(function($, ReviewImproved, undefined) {

    var mountpoint ;

    $(function() {
        mountpoint = $('[data-mount=review-side-panel]')[0];
    });

    $.extend( ReviewImproved, {

        deleteIssue : function( issue ) {
            var message = sprintf(
                "You are about to delete the issue on string '%s' posted on %s." ,
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
        },

        highlightIssue : function(issue, node) {
            console.log('highlightIssue', issue, node );
            var selection = document.getSelection();
            selection.removeAllRanges();

            var contents = node.contents();
            var range = document.createRange();

            range.setStart( contents[ issue.start_node ], issue.start_offset );
            range.setEnd( contents[ issue.end_node ], issue.end_offset );

            selection.addRange( range );
        },

        loadComments : function(id_segment, id_issue) {
            var issue_comments = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s/comments',
                config.id_job, config.password,
                id_segment,
                id_issue
            );

            $.getJSON(issue_comments).done(function(data) {
                $.each( data.comments, function( comment ) {
                    MateCat.db.upsert('segment_translation_issue_comments', 'id', this );
                });
            });
        },
        submitComment : function(id_segment, id_issue, data) {
            var replies_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s/comments',
                config.id_job, config.password,
                id_segment,
                id_issue
            );

            $.ajax({
                url: replies_path,
                type: 'POST',
                data : data
            }).done( function( data ) {
                MateCat.db.segment_translation_issue_comments.insert ( data.comment );
            });
        },
        unmountPanelComponent : function() {
            ReactDOM.unmountComponentAtNode( mountpoint );
        },

        mountPanelComponent : function() {
            ReactDOM.render(
                React.createElement( ReviewSidePanel, {} ),
                mountpoint );
        },

        openPanel : function(data) {
            $('article').addClass('review-panel-opened');
            $('body').addClass('side-tools-opened review-side-panel-opened');
            hackSnapEngage( true );

            $(document).trigger('review-panel:opened', data);

            window.setTimeout( function(data) {
                var el = UI.Segment.find( data.sid ).el ;
                if ( UI.currentSegmentId != data.sid ) {
                    UI.focusSegment( el );
                }
                UI.scrollSegment( el );
            }, 500, data);
        },

        isPanelOpened : function() {
            $('article').hasClass('review-panel-opened');
        },

        closePanel : function() {
            $(document).trigger('review-panel:closed');

            hackSnapEngage( false );

            $('article').removeClass('review-panel-opened');
            $('body').removeClass('side-tools-opened review-side-panel-opened');


            window.setTimeout( function() {
                UI.scrollSegment( UI.currentSegment );
            }, 100);
        }
    });
})(jQuery, ReviewImproved);

/**
 * Review page
 */

if ( ReviewImproved.enabled() && config.isReview ) {
    SegmentActivator.registry.push(function( sid ) {
        var segment = UI.Segment.find( sid );
        // TODO: refactor this, the click to activate a
        // segment is not a good way to handle.
        segment.el.find('.errorTaggingArea').click();
    });

    $.extend(ReviewImproved, {
        submitIssue : function(sid, data, options) {

            var path  = sprintf('/api/v2/jobs/%s/%s/segments/%s/translation-issues',
                  config.id_job, config.password, sid);

            var segment = UI.Segment.find( sid );

            var submitIssue = function() {
                $.post( path, data )
                .done(function( data ) {
                    MateCat.db.segment_translation_issues.insert( data.issue ) ;
                    ReviewImproved.reloadQualityReport();

                    options.done( data );
                })
            }

            UI.setTranslation({
                id_segment: segment.id,
                status: 'rejected',
                caller: false,
                byStatus: false,
                propagate: false,
                callback : submitIssue
            });
        },
        reloadQualityReport : function() {
            var path  = sprintf('/api/v2/jobs/%s/%s/quality-report',
                config.id_job, config.password);

            $.getJSON( path )
                .success( function( data ) {
                    var review = data['quality-report'].chunk.review ;

                    window.quality_report_btn_component.setState({
                        is_pass : review.is_pass,
                        score : review.score,
                        percentage_reviewed : review.percentage
                    });
                });
        },

    });
}
