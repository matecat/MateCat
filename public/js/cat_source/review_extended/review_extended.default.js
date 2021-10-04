import _ from 'lodash'

import SegmentActions from '../es6/actions/SegmentActions'
import {getSegmentsIssues} from '../es6/api/getSegmentsIssues'

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
    getSegmentsIssues().then((data) => {
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
