let AppDispatcher = require('../dispatcher/AppDispatcher');
let AnalyzeConstants = require('../constants/AnalyzeConstants');


let AnalyzeActions = {
    /********* Projects *********/

    /** Render the list of projects
     * @param projects
     * @param team
     * @param teams
     * @param hideSpinner
     * */
    renderProjects: function (projects, team, teams, hideSpinner) {

        AppDispatcher.dispatch({
            actionType: AnalyzeConstants.RENDER_PROJECTS,
            projects: projects,
            team: team,
            hideSpinner: hideSpinner,
            filtering: false
        });


    },


};

module.exports = AnalyzeActions;