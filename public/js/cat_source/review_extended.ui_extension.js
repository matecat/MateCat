
if ( ReviewExtended.enabled() || ReviewExtendedFooter.enabled()) {


    (function (ReviewExtended, $,undefined) {


        $.extend(ReviewExtended, {

            submitIssue: function (sid, data_array, diff) {
                var fid = UI.getSegmentFileId(UI.getSegmentById(sid))


                var deferreds = _.map(data_array, function (data) {
                    data.diff = diff;
                    return API.SEGMENT.sendSegmentVersionIssue(sid, data)
                });

                return $.when.apply($, deferreds).done(function (response) {
                    UI.getSegmentVersionsIssues(sid, fid);
                    UI.reloadQualityReport();
                });
            },

            submitComment : function(id_segment, id_issue, data) {
                return API.SEGMENT.sendSegmentVersionIssueComment(id_segment, id_issue, data)
                    .done( function( data ) {
                        var fid = UI.getSegmentFileId(UI.getSegmentById(id_segment));
                        UI.getSegmentVersionsIssues(id_segment, fid);
                    });
            }
        });

        var originalRender = UI.render;
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
                    offset: '-100%',
                    context: $('#outer')
                };
                return promise;
            },

            registerReviseTab: function () {
                return false;
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
                return ReviewExtended.submitIssue(sid, data, diff);
            },

            getSegmentVersionsIssuesHandler(event) {
                var sid = event.segment.absId;
                var fid = UI.getSegmentFileId(event.segment.el);
                UI.getSegmentVersionsIssues(sid, fid);
            },

            getSegmentVersionsIssues: function (segmentId, fileId) {
                // TODO Uniform behavior of ReviewExtended and ReviewImproved
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
                var parsed = JSON.parse( context );
                var issue_path = sprintf(
                    '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s',
                    config.id_job, config.review_password,
                    parseInt(parsed.id_segment),
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
                return ReviewExtended.submitComment(id_segment, id_issue, data)
            }

        });
    })(Review, jQuery);
}
