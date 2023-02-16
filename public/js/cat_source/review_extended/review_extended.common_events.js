/*
    Common events used in translation and revise page when Review Extended is active
 */

import SegmentActions from '../es6/actions/SegmentActions'
import CatToolActions from '../es6/actions/CatToolActions'

if (ReviewExtended.enabled()) {
  $(document).on('files:appended', function () {
    ReviewExtended.getSegmentsIssues()
  })

  $(window).on('segmentOpened', function (e, data) {
    var panelClosed =
      localStorage.getItem(ReviewExtended.localStoragePanelClosed) === 'true'
    if (config.isReview && !panelClosed) {
      setTimeout(() =>
        SegmentActions.openIssuesPanel({sid: data.segmentId}, false),
      )
    }
    UI.getSegmentVersionsIssuesHandler(data.segmentId)
  })

  $(document).on('translation:change', function (e, data) {
    UI.getSegmentVersionsIssues(data.sid)
    CatToolActions.reloadQualityReport()
  })

  $(document).on('header-tool:open', function (e, data) {
    if (data.name === 'search') {
      SegmentActions.closeIssuesPanel()
    }
  })
}
