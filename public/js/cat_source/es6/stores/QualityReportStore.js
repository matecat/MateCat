import {EventEmitter} from 'events'
import assign from 'object-assign'
import Immutable from 'immutable'
import _ from 'lodash'

import AppDispatcher from './AppDispatcher'
import QRConstants from '../constants/QualityReportConstants'

EventEmitter.prototype.setMaxListeners(0)

let QualityReportStore = assign({}, EventEmitter.prototype, {
  _segmentsFiles: Immutable.fromJS({}),
  _files: Immutable.fromJS({}),
  _jobInfo: Immutable.fromJS({}),
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
    this._segmentsFiles = Immutable.fromJS(segmentsFiles)
    this._files = Immutable.fromJS(files)
    this._lastSegment = segmentsData._links.last_segment_id
  },

  storeJobInfo: function (job) {
    this._jobInfo = Immutable.fromJS(job)
  },

  addSegments: function (segmentsData) {
    _.forEach(segmentsData.segments, (segment) => {
      if (this._segmentsFiles.get(segment.file.id)) {
        let immFiles = Immutable.fromJS(segment)
        this._segmentsFiles = this._segmentsFiles.set(
          segment.file.id,
          this._segmentsFiles.get(segment.file.id).push(immFiles),
        )
      } else {
        this._segmentsFiles = this._segmentsFiles.set(
          segment.file.id,
          Immutable.fromJS([segment]),
        )
        this._files = this._files.set(
          segment.file.id,
          Immutable.fromJS(segment.file),
        )
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
    case QRConstants.RENDER_SEGMENTS:
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
        QRConstants.RENDER_SEGMENTS,
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
