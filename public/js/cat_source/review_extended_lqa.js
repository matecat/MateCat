
ReviewExtendedLQA = {
    enabled : function() {
        return Review.type === 'extended-lqa' ;
    },
    type : config.reviewType
};

if ( ReviewExtendedLQA.enabled() ) {


    (function (ReviewExtendedLQA, $,undefined) {

        var originalClickOnApprovedButton = UI.clickOnApprovedButton;

        $.extend(ReviewExtendedLQA, {

            submitIssue: function (sid, data_array, diff) {
                var fid = UI.getSegmentFileId(UI.getSegmentById(sid))


                var deferreds = _.map(data_array, function (data) {
                    data.diff = diff;
                    return API.SEGMENT.sendSegmentVersionIssue(sid, data)
                });

                return $.when.apply($, deferreds).done(function (response) {
                    UI.getSegmentVersionsIssues(sid, fid);
                });
            },
        });

        $.extend(UI, {

            alertNotTranslatedMessage: "This segment is not translated yet.<br /> Only translated segments can be revised.",
            registerReviseTab: function () {
                SegmentActions.registerTab('issues', true, true);
            },
            /**
             * Overwrite the Review function that updates the tab trackChanges, in this review we don't have track changes.
             * @param editarea
             */
            trackChanges: function (editarea) {},

            submitIssues: function (sid, data, diff) {
                return ReviewExtendedLQA.submitIssue(sid, data, diff);
            },
            getSegmentVersionsIssuesHandler(event) {
                let sid = event.segment.absId;
                let fid = UI.getSegmentFileId(event.segment.el);
                UI.getSegmentVersionsIssues(sid, fid);
            },
            getSegmentVersionsIssues: function (segmentId, fileId) {
                API.SEGMENT.getSegmentVersionsIssues(segmentId)
                    .done(function (response) {
                        SegmentActions.addTranslationIssuesToSegment(fileId, segmentId, response.versions);
                    });
            },
            /**
             * Overwrite the behavior of the click on the approved button
             * @param e
             * @param button
             */
            clickOnApprovedButton: function (e, button) {
                originalClickOnApprovedButton.apply(this, [e , button]);
            },

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
                    SegmentActions.confirmDeletedIssue(parsed.id_segment,issue_id);
                    UI.getSegmentVersionsIssues(parsed.id_segment, fid);
                });
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
            }

        });
    })(Review, jQuery);
}
