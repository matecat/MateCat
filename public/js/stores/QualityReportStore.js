import {EventEmitter} from 'events'
import assign from 'object-assign'
import {fromJS} from 'immutable'
import {forEach} from 'lodash'

import AppDispatcher from './AppDispatcher'
import QRConstants from '../constants/QualityReportConstants'

EventEmitter.prototype.setMaxListeners(0)

let QualityReportStore = assign({}, EventEmitter.prototype, {
  _segmentsFiles: fromJS({}),
  _files: fromJS({}),
  _jobInfo: fromJS({}),
  _lastSegment: null,
  storeSegments: function (segmentsData) {
    const files = {}
    const segmentsFiles = {}
    segmentsData.segments.forEach((segment) => {
      segmentsFiles[segment.file.id]
        ? segmentsFiles[segment.file.id].push(segment)
        : (segmentsFiles[segment.file.id] = [segment])
      files[segment.file.id] = segment.file
    })
    this._segmentsFiles = fromJS(segmentsFiles)
    this._files = fromJS(files)
    this._lastSegment =
      segmentsData.segments.length > 0
        ? segmentsData._links.last_segment_id
        : undefined
  },

  storeJobInfo: function (job) {
    this._jobInfo = fromJS(job)
  },

  addSegments: function (segmentsData) {
    forEach(segmentsData.segments, (segment) => {
      const fileId = segment.file.id.toString()
      if (this._segmentsFiles.get(fileId)) {
        let immFiles = fromJS(segment)
        this._segmentsFiles = this._segmentsFiles.set(
          fileId,
          this._segmentsFiles.get(fileId).push(immFiles),
        )
      } else {
        this._segmentsFiles = this._segmentsFiles.set(fileId, fromJS([segment]))
        this._files = this._files.set(fileId, fromJS(segment.file))
      }
    })
    this._lastSegment = segmentsData._links.last_segment_id
  },

  emitChange: function () {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case QRConstants.RENDER_SEGMENTS_QR:
      QualityReportStore.storeSegments(action.files)
      QualityReportStore.emitChange(
        action.actionType,
        QualityReportStore._segmentsFiles,
        QualityReportStore._files,
        QualityReportStore._lastSegment,
      )
      break
    case QRConstants.ADD_SEGMENTS_QR:
      QualityReportStore.addSegments(action.files)
      QualityReportStore.emitChange(
        QRConstants.RENDER_SEGMENTS_QR,
        QualityReportStore._segmentsFiles,
        QualityReportStore._files,
        QualityReportStore._lastSegment,
      )
      break
    case QRConstants.RENDER_REPORT:
      QualityReportStore.storeJobInfo(action.job)
      QualityReportStore.emitChange(
        QRConstants.RENDER_REPORT,
        QualityReportStore._jobInfo,
      )
      break
    case QRConstants.NO_MORE_SEGMENTS:
      QualityReportStore.storeJobInfo(action.job)
      QualityReportStore.emitChange(QRConstants.NO_MORE_SEGMENTS)
      break
  }
})

export default QualityReportStore
