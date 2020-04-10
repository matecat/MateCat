
ReviewExtended = {
    enabled : function() {
        return Review.type === 'extended' ;
    },
    type : config.reviewType,
    issueRequiredOnSegmentChange: true,
    localStoragePanelClosed: "issuePanelClosed-"+config.id_job+config.password,
    number: config.revisionNumber,
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

