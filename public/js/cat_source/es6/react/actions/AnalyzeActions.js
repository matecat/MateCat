let AppDispatcher = require('../dispatcher/AppDispatcher');
let AnalyzeConstants = require('../constants/AnalyzeConstants');


let AnalyzeActions = {

    renderAnalysis: function (volumeAnalysis, project) {
        AppDispatcher.dispatch({
            actionType: AnalyzeConstants.RENDER_ANALYSIS,
            volumeAnalysis: volumeAnalysis,
            project: project
        });
    },
    updateVolumeAnalysis: function (volumeAnalysis) {
        AppDispatcher.dispatch({
            actionType: AnalyzeConstants.UPDATE_ANALYSIS,
            volumeAnalysis: volumeAnalysis,
        });
    },
    updateProject: function (project) {
        AppDispatcher.dispatch({
            actionType: AnalyzeConstants.UPDATE_PROJECT,
            project: project
        });
    }
};

module.exports = AnalyzeActions;