import AppDispatcher from '../stores/AppDispatcher'
import QRConstants from '../constants/QualityReportConstants'
import {getQualityReportSegmentsFiles} from '../api/getQualityReportSegmentsFiles'
import {getQualityReportInfo} from '../api/getQualityReportInfo'
let QualityReportActions = {
  loadInitialAjaxData(data) {
    getQualityReportSegmentsFiles(data).then((response) => {
      if (response.segments) {
        AppDispatcher.dispatch({
          actionType: QRConstants.RENDER_SEGMENTS_QR,
          files: response,
        })
      }
    })
    getQualityReportInfo().then((response) => {
      if (response.job) {
        AppDispatcher.dispatch({
          actionType: QRConstants.RENDER_REPORT,
          job: response.job.chunks[0],
        })
      }
    })
  },

  getMoreQRSegments(filter, segmentId) {
    getQualityReportSegmentsFiles(filter, segmentId).then((response) => {
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
    getQualityReportSegmentsFiles(filter, segmentId).then((response) => {
      if (response.segments) {
        AppDispatcher.dispatch({
          actionType: QRConstants.RENDER_SEGMENTS_QR,
          files: response,
        })
      }
    })
  },
}

export default QualityReportActions
