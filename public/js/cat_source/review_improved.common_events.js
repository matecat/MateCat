// common events
//
if ( ReviewImproved.enabled() ) {

    // Globally reusable functions
    $.extend(ReviewImproved, {

        // Global vars: TODO: find a way to remove the need for these
        currentHiglight : null,
        modal : null,
        lastSelection : null,

        commentsLoaded : function(event, issue) {
            ReviewImproved.renderCommentList( issue );
        },

        loadIssuesForVersion : function( segment ) {
            var issues_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation/issues?version_number=%s',
                config.id_job, config.password,
                segment.id, segment.el.data('revertingVersion')
            );
            $.getJSON( issues_path )
            .done(function( data ) {
                if ( data.issues.length ) {
                    $.each( data.issues, function() {
                        MateCat.db.upsert('segment_translation_issues', 'id', _.clone(this) );
                    });
                }
            });
        },

        // TODO: rerender issue detail instead
        renderCommentList : function( issue ) {
            var selector = sprintf(
                '[data-issue-id=%s] [data-mount=issue-comments]:visible',
                issue.id
            );
            var mount_point = $( selector );
            if ( mount_point.length == 0 ) return;

            var comments = MateCat.db.segment_translation_issue_comments.
                findObjects({ 'id_issue': issue.id });

            var data = {
                loading : false,
                comments : _.sortBy(comments, 'created_at')
            };
            var tpl = template('review_improved/issue_comments', data);
            mount_point.html(tpl);
        },

        getSegmentRecord : function( segment ) {
            return MateCat.db.segments
                .findObject({sid : segment.id });
        },

        getTranslationText : function( segment ) {
            var record = ReviewImproved.getSegmentRecord( segment );
            var version;
            var revertingVersion = segment.el.data('revertingVersion');

            if ( revertingVersion ) {
                version = MateCat.db.segment_versions.findObject({
                    id_segment : record.sid,
                    version_number : revertingVersion + ''
                });
                return version.translation ;
            }
            else {
                return record.translation ;
            }
        },

        showIssueDetailModalWindow : function( issue ) {

            $(document).one('closed', '.remodal', function() {
                ReviewImproved.modal.destroy();
            });

            $(document).one('opened', '.remodal', function() {
                var issue_comments = sprintf(
                    '/api/v2/jobs/%s/%s/segments/%s/translation/issues/%s/comments',
                    config.id_job, config.password,
                    issue.id_segment,
                    issue.id
                );

                // ReviewImproved.renderCommentList( issue );
                $.getJSON(issue_comments).done(function(data) {
                    $.each( data.comments, function( comment ) {
                        MateCat.db.upsert('segment_translation_issue_comments', 'id', _.clone(this) );
                    });
                    $(document).trigger('issue_comments:load', issue);
                });
            });

            var tpl_data = { loading: true, issue: issue };
            var tpl = template('review_improved/issue_detail_modal', tpl_data);

            ReviewImproved.modal = tpl.remodal({});

            tpl.on('keydown', function(e)  {
                var esc = 27 ;
                e.stopPropagation();
                if ( e.which == esc ) {
                    ReviewImproved.modal.close();
                }
            });

            ReviewImproved.modal.open();
        },

        updateIssueViews : function( segment ) {
            var targetVersion = segment.el.data('revertingVersion');
            var record = MateCat.db.segments.by('sid', segment.id );
            var version = (targetVersion == null ? record.version_number : targetVersion) ;
            var issues = MateCat.db.segment_translation_issues;
            var current_issues = issues.findObjects({
                id_segment : record.sid, translation_version : version
            });

            var data = {
                issues : current_issues,
                isReview : config.isReview
            };

            var tpl = template('review_improved/translation_issues', data );

            tpl.find('.issue-container').on('mouseenter', ReviewImproved.highlightIssue);
            tpl.find('.issue-container').on('mouseleave', ReviewImproved.resetHighlight);

            UI.Segment.findEl( record.sid ).find('[data-mount=translation-issues]').html( tpl );
        },

        highlightIssue : function(e) {
            var container = $(e.target).closest('.issue-container');
            var issue = MateCat.db.segment_translation_issues.findObject({
                id : container.data('issue-id') + ''
            });
            var segment = MateCat.db.segments.findObject({sid : issue.id_segment});

            // TODO: check for this to be really needed
            if ( container.data('current-issue-id') == issue.id ) {
                return ;
            }

            // TODO: check for this to be really needed
            container.data('current-issue-id', issue.id);
            var selection = document.getSelection();
            selection.removeAllRanges();

            var area = container.closest('section').find('.issuesHighlightArea') ;

            // TODO: fix this to take into account cases when monads are in place
            var contents       = area.contents() ;
            var range = document.createRange();

            range.setStart( contents[ issue.start_node ], issue.start_offset );
            range.setEnd( contents[ issue.end_node ], issue.end_offset );

            selection.addRange( range );
        },

        resetHighlight : function(e) {
            var selection = document.getSelection();
            selection.removeAllRanges();

            var segment = new UI.Segment( $(e.target).closest('section'));
            var container = $(e.target).closest('.issue-container');

            container.data('current-issue-id', null) ; // TODO: check for this to be really needed

            var section = container.closest('section');

            var area = section.find('.issuesHighlightArea') ;
            var issue = MateCat.db.segment_translation_issues.findObject({
                id : container.data('issue-id') + ''
            });
            area.html(
                UI.decodePlaceholdersToText(
                    ReviewImproved.getTranslationText( segment )
                )
            );
        },

        versionsAndIssuesPromise : function( segment ) {
            var versions_path  = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation/versions',
                config.id_job, config.password, segment.id
            );

            var issues_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation/issues',
                config.id_job, config.password, segment.id
            );

            return $.when(
                $.getJSON( versions_path )
                .done(function( data ) {
                    if ( data.versions.length ) {
                        $.each( data.versions, function() {
                            MateCat.db.upsert('segment_versions', 'id', _.clone(this) );
                        });
                    }
                })
                ,
                $.getJSON( issues_path )
                .done(function( data ) {
                    if ( data.issues.length ) {
                        $.each( data.issues, function() {
                            this.formattedDate = moment(this.created_at).format('lll');
                            MateCat.db.upsert('segment_translation_issues', 'id',
                                              _.clone(this) );
                        });
                    }
                })
            );
        }
    });

    $(document).on('issue_comments:load', ReviewImproved.commentsLoaded);

    $(document).on('segments:load', function(e, data) {
        $.each(data.files, function() {
            $.each( this.segments, function() {
                MateCat.db.upsert( 'segments', 'sid', _.clone( this ) );
            });
        });
    });

    $(document).on('change', '.version-picker', function(e) {
        var segment = new UI.Segment( $(e.target).closest('section'));
        var target = $(e.target);
        var value = target.val();
        if ( value == '' ) {
            segment.el.removeClass('reverted');
            segment.el.data('revertingVersion', null);
        }
        else {
            segment.el.addClass('reverted');
            segment.el.data('revertingVersion', value);
            ReviewImproved.loadIssuesForVersion( segment );
        }

        $(document).trigger('segmentVersionChanged', segment);
    });

    $(document).on('click', '.action-view-issue', function(e) {
        var container =  $(e.target).closest('.issue-container') ;
        var issue = MateCat.db.segment_translation_issues
            .by('id',container.data('issue-id'));
        ReviewImproved.showIssueDetailModalWindow( issue );
    });

    $(document).on('click', 'input[data-action=submit-issue-reply]', function(e) {
        var container = $(e.target).closest('.issue-detail-modal');
        var issue = MateCat.db.segment_translation_issues
            .by('id', container.data('issue-id'));

        var data = {
          message : $('[data-ui=issue-reply-message]').val(),
          source_page : config.isReview
        };

        var replies_path = sprintf(
            '/api/v2/jobs/%s/%s/segments/%s/translation/issues/%s/comments',
            config.id_job, config.password,
            issue.id_segment,
            issue.id
        );

        $.ajax({
            url: replies_path,
            type: 'POST',
            data : data
        }).done( function( data ) {
            MateCat.db.segment_translation_issue_comments.insert ( data.comment );
            $(document).trigger('issue_comments:load', issue );
            ReviewImproved.renderCommentList( issue );
        });

    });
}
