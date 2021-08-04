import AppDispatcher from '../stores/AppDispatcher'
import QRApi from '../ajax_utils/quality_report/qrAjax'
import QRConstants from '../constants/QualityReportConstants'
import {getQualityReportSegmentsFiles} from '../api/getQualityReportSegmentsFiles'
let QualityReportActions = {
  loadInitialAjaxData(data) {
    getQualityReportSegmentsFiles(data).then(function (response) {
      if (response.segments) {
        AppDispatcher.dispatch({
          actionType: QRConstants.RENDER_SEGMENTS,
          files: response,
        })
      }
    })
    QRApi.getQRinfo().done(function (response) {
      if (response.job) {
        AppDispatcher.dispatch({
          actionType: QRConstants.RENDER_REPORT,
          job: response.job.chunks[0],
        })
      }
    })
  },

  getMoreQRSegments(filter, segmentId) {
    getQualityReportSegmentsFiles(filter, segmentId).then(function (response) {
      if (response.segments && response.segments.length > 0) {
        AppDispatcher.dispatch({
          actionType: QRConstants.ADD_SEGMENTS_QR,
          files: response,
        })
      } else {
        AppDispatcher.dispatch({
          actionType: QRConstants.NO_MORE_SEGMENTS,
        })
      }
    })
  },

  filterSegments(filter, segmentId) {
    getQualityReportSegmentsFiles(filter, segmentId).then(function (response) {
      if (response.segments) {
        AppDispatcher.dispatch({
          actionType: QRConstants.RENDER_SEGMENTS,
          files: response,
        })
      }
    })
  },
}

export default QualityReportActions
