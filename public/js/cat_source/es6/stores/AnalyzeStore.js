/*
 * Analyze Store
 */

import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import AnalyzeConstants from '../constants/AnalyzeConstants'
import assign from 'object-assign'
import Immutable from 'immutable'

EventEmitter.prototype.setMaxListeners(0)

let AnalyzeStore = assign({}, EventEmitter.prototype, {
  volumeAnalysis: null,

  project: null,

  updateAll: function (volumeAnalysis, project) {
    this.volumeAnalysis = Immutable.fromJS(volumeAnalysis)
    this.project = Immutable.fromJS(project)
  },
  updateAnalysis: function (volumeAnalysis) {
    this.volumeAnalysis = Immutable.fromJS(volumeAnalysis)
  },
  updateProject: function (project) {
    this.project = Immutable.fromJS(project)
  },
  emitChange: function (event, args) {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case AnalyzeConstants.RENDER_ANALYSIS:
      AnalyzeStore.updateAll(action.volumeAnalysis, action.project)
      AnalyzeStore.emitChange(
        AnalyzeConstants.RENDER_ANALYSIS,
        AnalyzeStore.volumeAnalysis,
        AnalyzeStore.project,
      )
      break
    case AnalyzeConstants.UPDATE_PROJECT:
      AnalyzeStore.updateProject(action.project)
      AnalyzeStore.emitChange(
        AnalyzeConstants.UPDATE_PROJECT,
        AnalyzeStore.project,
      )
      break
    case AnalyzeConstants.UPDATE_ANALYSIS:
      AnalyzeStore.updateAnalysis(action.volumeAnalysis)
      AnalyzeStore.emitChange(
        AnalyzeConstants.UPDATE_ANALYSIS,
        AnalyzeStore.volumeAnalysis,
      )
      break
    case AnalyzeConstants.SHOW_DETAILS:
      AnalyzeStore.emitChange(AnalyzeConstants.SHOW_DETAILS, action.idJob)
      break
  }
})
module.exports = AnalyzeStore
