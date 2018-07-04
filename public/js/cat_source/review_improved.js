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
    return Review.type === 'improved';
};

if ( ReviewImproved.enabled() )
(function($, ReviewImproved, undefined) {



    $.extend( ReviewImproved, {

        highlightIssue : function(issue, node) {
            var selection = document.getSelection();
            selection.removeAllRanges();

            var range = document.createRange();

            /**
             * The following two lines are necessary to avoid Rangy span to get in the way when
             * we want to highlight text.
             * The first line removes rangy tags, while the second line via `normalize()`
             * rejoins text nodes that may have become splitted due to rangy span insertion.
             */
            node.contents('.rangySelectionBoundary').remove();
            node[0].normalize();

            var contents = node.contents() ; 
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
            return API.SEGMENT.sendSegmentVersionIssueComment(id_segment, id_issue, data)
                .done( function( data ) {
                MateCat.db.segment_translation_issue_comments.insert ( data.comment );

                if( data.issue ) {
                    ReviewImproved.updateIssueRebutted( data.issue );
                }
           });
        },
        updateIssueRebutted : function ( issue ) {
            MateCat.db.upsert('segment_translation_issues', 'id', issue );
        },
        undoRebutIssue : function ( id_segment, id_issue ) {
            var issue_update_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s',
                config.id_job, config.password,
                id_segment,
                id_issue
            );

            return $.ajax({
                url: issue_update_path,
                type: 'POST',
                data : { rebutted_at : null }
            }).done( function( data ) {
                if( data.issue ) {
                    ReviewImproved.updateIssueRebutted( data.issue );
                }
            });
        },

        reloadQualityReport : function() {
            UI.reloadQualityReport();
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
        submitIssue : function(sid, data_array) {
            var path  = sprintf('/api/v2/jobs/%s/%s/segments/%s/translation-issues',
                  config.id_job, config.password, sid);

            var deferreds = _.map( data_array, function( data ) {
                return $.post( path, data )
                .done(function( data ) {
                    MateCat.db.segment_translation_issues.insert( data.issue ) ;
                })
            });

            return $.when.apply($, deferreds).done(function() {
                ReviewImproved.reloadQualityReport();
            });
        },

    });
}
