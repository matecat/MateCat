ReviewExtended = {
    firstLoad: true,
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
                    ReviewExtended.reloadQualityReport();
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

        let originalRender = UI.render;
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
                if ( UI.segmentIsModified(sid)) {
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
                var issue_id = parsed.id_issue;
                var fid = UI.getSegmentFileId(UI.getSegmentById(parsed.id_segment));
                $.ajax({
                    url: issue_path,
                    type: 'DELETE'
                }).done( function( data ) {
                    SegmentActions.confirmDeletedIssue(parsed.id_segment,issue_id);
                    UI.getSegmentVersionsIssues(parsed.id_segment, fid);
                    UI.reloadQualityReport();
                });
            },
            submitComment : function(id_segment, id_issue, data) {
                return ReviewExtended.submitComment(id_segment, id_issue, data)
            },

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
