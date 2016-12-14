var AppDispatcher = require('../dispatcher/AppDispatcher');
var ManageConstants = require('../constants/ManageConstants');


var ManageActions = {
    /********* SEGMENTS *********/

    /** Render the list of projects
     * @param projects
     */
    renderProjects: function (projects) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_PROJECTS,
            project: projects,
        });
    },
    /** Render the more projects
     * @param projects
     */
    renderMoreProjects: function (projects) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_MORE_PROJECTS,
            project: projects,
        });
    },
    /** Open the translate page with the tab open
     * @param job
     * @param prName
     */
    openJobSettings: function (job, prName) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_JOB_SETTINGS,
            job: job,
            prName: prName
        });
    },

    closeAllJobs: function () {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CLOSE_ALL_JOBS
        });
    }


};

module.exports = ManageActions;