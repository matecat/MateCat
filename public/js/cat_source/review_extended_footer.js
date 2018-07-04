
ReviewExtendedFooter = {
    enabled : function() {
        return Review.type === 'extended-footer' ;
    },
    type : config.reviewType
};

if ( ReviewExtendedFooter.enabled() ) {


    (function (ReviewExtendedFooter, $,undefined) {

        var originalGotoNextSegment = UI.gotoNextSegment;
        var originalRender = UI.render;
        $.extend(ReviewExtendedFooter, {

            submitIssue: function (sid, data_array, diff) {
                var fid = UI.getSegmentFileId(UI.getSegmentById(sid))


                var deferreds = _.map(data_array, function (data) {
                    data.diff = diff;
                    return API.SEGMENT.sendSegmentVersionIssue(sid, data)
                });

                return $.when.apply($, deferreds).done(function (response) {
                    UI.getSegmentVersionsIssues(sid, fid);
                    ReviewExtendedFooter.reloadQualityReport();
                });
            },
            submitComment : function(id_segment, id_issue, data) {
                return API.SEGMENT.sendSegmentVersionIssueComment(id_segment, id_issue, data)
                    .done( function( data ) {
                        var fid = UI.getSegmentFileId(UI.getSegmentById(id_segment));
                        UI.getSegmentVersionsIssues(id_segment, fid);
                    });
            },
            reloadQualityReport : function() {
                UI.reloadQualityReport();
            }
        });

        $.extend(UI, {

            alertNotTranslatedMessage: "This segment is not translated yet.<br /> Only translated segments can be revised.",

            render: function ( options ) {
                var promise = (new $.Deferred() ).resolve();
                originalRender.call(this, options);
                this.downOpts = {
                    offset: '100%',
                    context: $('#outer')
                };
                this.upOpts = {
                    offset: '-40%',
                    context: $('#outer')
                };
                return promise;
            },

            registerReviseTab: function () {
                // SegmentActions.registerTab('issues', true, false);
            },
            /**
             * Overwrite the Review function that updates the tab trackChanges, in this review we don't have track changes.
             * @param editarea
             */
            trackChanges: function (editarea) {
                var segmentId = UI.getSegmentId($(editarea));
                var segmentFid = UI.getSegmentFileId($(editarea));
                var currentSegment =  UI.getSegmentById(segmentId)
                var originalTranslation = currentSegment.find('.original-translation').html();
                SegmentActions.updateTranslation(segmentFid, segmentId, $(editarea).html(), originalTranslation);
            },

            submitIssues: function (sid, data, diff) {
                return ReviewExtendedFooter.submitIssue(sid, data, diff);
            },
            getSegmentVersionsIssuesHandler(event) {
                var sid = event.segment.absId;
                var fid = UI.getSegmentFileId(event.segment.el);
                UI.getSegmentVersionsIssues(sid, fid);
            },
            getSegmentVersionsIssues: function (segmentId, fileId) {
                API.SEGMENT.getSegmentVersionsIssues(segmentId)
                    .done(function (response) {
                        UI.addIssuesToSegment(fileId, segmentId, response.versions)
                    });
            },
            /**
             * To show the issues in the segment footer
             * @param fileId
             * @param segmentId
             * @param versions
             */
            addIssuesToSegment: function ( fileId, segmentId, versions ) {
                SegmentActions.addTranslationIssuesToSegment(fileId, segmentId, versions);
            },
            /**
             * To delete a segment issue
             * @param context
             */
            deleteTranslationIssue : function( context ) {
                console.debug('delete issue', context);

                var parsed = JSON.parse( context );
                var issue_path = sprintf(
                    '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s',
                    config.id_job, config.review_password,
                    parsed.id_segment,
                    parsed.id_issue
                );
                var issue_id = parsed.id_issue;
                var fid = UI.getSegmentFileId(UI.getSegmentById(parsed.id_segment));
                $.ajax({
                    url: issue_path,
                    type: 'DELETE'
                }).done( function( data ) {
                    UI.deleteSegmentIssues(fid, parsed.id_segment, issue_id);
                    UI.reloadQualityReport();
                });
            },
            /**
             * To remove Segment issue from the segment footer
             * @param fid
             * @param id_segment
             * @param issue_id
             */
            deleteSegmentIssues: function ( fid, id_segment, issue_id ) {
                SegmentActions.confirmDeletedIssue(id_segment, issue_id);
                UI.getSegmentVersionsIssues(id_segment, fid);
            },
            /**
             * To know if a segment has been modified but not yet approved
             * @param sid
             * @returns {boolean}
             */
            segmentIsModified: function ( sid ) {
                var segmentFid = UI.getSegmentFileId(UI.currentSegment);
                var segment = SegmentStore.getSegmentByIdToJS(sid, segmentFid);
                var versionTranslation = $('<div/>').html(UI.transformTagsWithHtmlAttribute(segment.versions[0].translation)).text();

                if (UI.currentSegment.hasClass('modified') && versionTranslation.trim() !== UI.getSegmentTarget(UI.currentSegment).trim()) {
                    return true;
                }
                return false;
            },

            submitComment : function(id_segment, id_issue, data) {
                return ReviewExtendedFooter.submitComment(id_segment, id_issue, data)
            },

            setDisabledOfButtonApproved: function (sid,isDisabled ) {
                var div =$("#segment-"+sid+"-buttons").find(".approved, .next-unapproved");
                if(!isDisabled){
                    div.removeClass('disabled').attr("disabled", false);
                }else{
                    div.addClass('disabled').attr("disabled", false);
                }

            },

            gotoNextSegment: function ( sid ) {
                if (config.isReview && sid) {
                    this.setDisabledOfButtonApproved(sid, true);
                }
                originalGotoNextSegment.apply(this);
                return false;
            }

        });
    })(Review, jQuery);
}
