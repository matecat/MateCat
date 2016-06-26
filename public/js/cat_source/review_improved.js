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

    /**
     * Split segment feature is not compatible with ReviewImproved.
     */
    window.config.splitSegmentEnabled = false;

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
            var replies_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s/comments',
                config.id_job, config.password,
                id_segment,
                id_issue
            );

            return $.ajax({
                url: replies_path,
                type: 'POST',
                data : data
            }).done( function( data ) {
                MateCat.db.segment_translation_issue_comments.insert ( data.comment );

                if( data.rebutted_entry ) {
                    ReviewImproved.updateIssueRebutted( data.rebutted_entry );
                }
           });
        },
        updateIssueRebutted : function ( rebutted_entry ) {
            var issue = MateCat.db.segment_translation_issues.by(
                'id', rebutted_entry.id
            );
            MateCat.db.segment_translation_issues.update(
                _.extend( issue, { 'rebutted_at': rebutted_entry.rebutted_at } )
            );
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
                data : { rebutted : false }
            }).done( function( data ) {
                if( data.rebutted_entry ) {
                    ReviewImproved.updateIssueRebutted( data.rebutted_entry );
                }
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
            
            var segment = UI.Segment.findEl( data.sid );

            $(document).trigger('review-panel:opened', data);

            // simulate the click to activate the segment, we don't want
            // side panel open on deactivted segments.

            segment.find( UI.targetContainerSelector() ).click();

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
            if ( ReviewImproved.isPanelOpened() ) {
                window.setTimeout( function() {
                    UI.scrollSegment( UI.currentSegment );
                }, 100);
            }
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
            var segment = UI.Segment.find( sid );
            
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
