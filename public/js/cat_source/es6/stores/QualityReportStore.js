import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import assign from 'object-assign'
import Immutable from 'immutable'
import QRConstants from '../constants/QualityReportConstants'

EventEmitter.prototype.setMaxListeners(0)

let QualityReportStore = assign({}, EventEmitter.prototype, {
  _segmentsFiles: Immutable.fromJS({}),
  _jobInfo: Immutable.fromJS({}),
  storeSegments: function (files) {
    this._segmentsFiles = Immutable.fromJS(files)
  },

  storeJobInfo: function (job) {
    this._jobInfo = Immutable.fromJS(job)
  },

  addSegments: function (files) {
    _.forEach(files, (file, key) => {
      if (this._segmentsFiles.get(key)) {
        let immFiles = Immutable.fromJS(file.segments)
        this._segmentsFiles = this._segmentsFiles.setIn(
          [key, 'segments'],
          this._segmentsFiles
            .get(key)
            .get('segments')
            .push(...immFiles),
        )
      } else {
        this._segmentsFiles = this._segmentsFiles.set(
          key,
          Immutable.fromJS(file),
        )
      }
    })

    // this._segmentsFiles = this._segmentsFiles.mergeDeep(immFiles);
  },

  emitChange: function (event, args) {
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
      )
      break
    case QRConstants.ADD_SEGMENTS:
      QualityReportStore.addSegments(action.files)
      QualityReportStore.emitChange(
        QRConstants.RENDER_SEGMENTS,
        QualityReportStore._segmentsFiles,
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
