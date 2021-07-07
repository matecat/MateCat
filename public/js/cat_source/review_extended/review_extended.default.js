window.ReviewExtended = {
  enabled: function () {
    return Review.type === 'extended'
  },
  type: config.reviewType,
  issueRequiredOnSegmentChange: true,
  localStoragePanelClosed:
    'issuePanelClosed-' + config.id_job + config.password,
  number: config.revisionNumber,
  getSegmentsIssues: function () {
    API.SEGMENT.getSegmentsIssues().done((data) => {
      let versionsIssues = {}
      _.each(data.issues, (issue) => {
        if (!versionsIssues[issue.id_segment]) {
          versionsIssues[issue.id_segment] = []
        }
        versionsIssues[issue.id_segment].push(issue)
      })
      SegmentActions.addPreloadedIssuesToSegment(versionsIssues)
    })
  },
}
