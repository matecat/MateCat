import AppDispatcher from '../stores/AppDispatcher'
import AnalyzeConstants from '../constants/AnalyzeConstants'

let AnalyzeActions = {
  renderAnalysis: function (volumeAnalysis, project) {
    AppDispatcher.dispatch({
      actionType: AnalyzeConstants.RENDER_ANALYSIS,
      volumeAnalysis: volumeAnalysis,
      project: project,
    })
  },
  updateVolumeAnalysis: function (volumeAnalysis) {
    AppDispatcher.dispatch({
      actionType: AnalyzeConstants.UPDATE_ANALYSIS,
      volumeAnalysis: volumeAnalysis,
    })
  },
  updateProject: function (project) {
    AppDispatcher.dispatch({
      actionType: AnalyzeConstants.UPDATE_PROJECT,
      project: project,
    })
  },

  showDetails: function (idJob) {
    AppDispatcher.dispatch({
      actionType: AnalyzeConstants.SHOW_DETAILS,
      idJob: idJob,
    })
  },
}

module.exports = AnalyzeActions
