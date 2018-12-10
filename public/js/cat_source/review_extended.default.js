
ReviewExtended = {
    enabled : function() {
        return Review.type === 'extended' ;
    },
    type : config.reviewType,
    issueRequiredOnSegmentChange: false,
    localStoragePanelClosed: "issuePanelClosed-"+config.id_job+config.password,
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
                var isSplit = sid.indexOf("-") !== -1;
                if (!isSplit && UI.segmentIsModified(sid) && this.issueRequiredOnSegmentChange) {
                    SegmentActions.showIssuesMessage(sid);
                    return;
                }
                originalClickOnApprovedButton.apply(this, [e , button]);
            },

        });
})(Review, jQuery);
}
