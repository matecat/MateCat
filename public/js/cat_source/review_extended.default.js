
ReviewExtended = {
    enabled : function() {
        return Review.type === 'extended' ;
    },
    type : config.reviewType,
    getSegmentsIssues: function (  ) {
        API.SEGMENT.getSegmentsIssues().done(  ( data ) => {
            var versionsIssues = {};
            _.each( data.issues, (issue) => {
                if (!versionsIssues[issue.id_segment]) {
                    versionsIssues[issue.id_segment] = [];
                }
                versionsIssues[issue.id_segment].push(issue);
            });
            _.each(versionsIssues, function ( issues, segmentId ) {
                SegmentActions.addPreloadedIssuesToSegment(segmentId, issues);
            })
        });
    }
};

if ( ReviewExtended.enabled() ) {
    (function (ReviewExtended, $,undefined) {
        var originalClickOnApprovedButton = UI.clickOnApprovedButton;

        $.extend(UI, {
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

        });
})(Review, jQuery);
}
