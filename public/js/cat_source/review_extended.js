ReviewExtended = {
    enabled : function() {
        return Review.type === 'extended' ;
    },
    type : config.reviewType
};

if ( ReviewExtended.enabled() ) {


    (function (ReviewExtended, $,undefined) {

        var originalClickOnApprovedButton = UI.clickOnApprovedButton;

        $.extend(ReviewExtended, {
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

            submitComment : function(id_segment, id_issue, data) {
                return API.SEGMENT.sendSegmentVersionIssueComment(id_segment, id_issue, data)
                    .done( function( data ) {
                        var fid = UI.getSegmentFileId(UI.getSegmentById(id_segment));
                        UI.getSegmentVersionsIssues(id_segment, fid);
                    });
            },
        });


        $.extend(UI, {

            alertNotTranslatedMessage: "This segment is not translated yet.<br /> Only translated segments can be revised.",

            registerReviseTab: function () {
                return false;
            },

            trackChanges: function (editarea) {
                var segmentId = UI.getSegmentId($(editarea));
                var segmentFid = UI.getSegmentFileId($(editarea));
                var originalTranslation = UI.currentSegment.find('.original-translation').html();
                SegmentActions.updateTranslation(segmentFid, segmentId, $(editarea).html(), originalTranslation);
            },

            submitIssues: function (sid, data, diff) {
                return ReviewExtended.submitIssue(sid, data, diff);
            },

            getSegmentVersionsIssuesHandler(event) {
                let sid = event.segment.absId;
                let fid = UI.getSegmentFileId(event.segment.el);
                UI.getSegmentVersionsIssues(sid, fid);
            },

            getSegmentVersionsIssues: function (segmentId, fileId) {
                // TODO Uniform behavior of ReviewExtended and ReviewImproved
                API.SEGMENT.getSegmentVersionsIssues(segmentId)
                    .done(function (response) {
                        SegmentActions.addTranslationIssuesToSegment(fileId, segmentId, response.versions);
                    });
            },

            clickOnApprovedButton: function (e, button) {
                e.preventDefault();
                var sid = UI.currentSegmentId;
                var segmentFid = UI.getSegmentFileId(UI.currentSegment);
                if ( UI.currentSegment.hasClass('modified') && !UI.checkSegmentIssues(sid, segmentFid)) {
                    SegmentActions.openIssuesPanel({ sid: sid });
                    SegmentActions.showIssuesMessage(sid);
                    return;
                }
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
                var fid = UI.getSegmentFileId(UI.getSegmentById(parsed.id_segment));
                $.ajax({
                    url: issue_path,
                    type: 'DELETE'
                }).done( function( data ) {
                    UI.getSegmentVersionsIssues(parsed.id_segment, fid);
                });
            },
            submitComment : function(id_segment, id_issue, data) {
                return ReviewExtended.submitComment(id_segment, id_issue, data)
            },

            checkSegmentIssues: function ( sid, fid ) {
                var segment = SegmentStore.getSegmentByIdToJS(sid, fid);
                return false;
            }

        });
    })(Review, jQuery);
}
