let AppDispatcher = require('../dispatcher/AppDispatcher');
let ManageConstants = require('../constants/ManageConstants');


let ManageActions = {
    /********* SEGMENTS *********/

    /** Render the list of projects
     * @param projects
     * @param team
     * @param hideSpinner
     * */
    renderProjects: function (projects, team, hideSpinner) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_PROJECTS,
            projects: projects,
            team: team,
            hideSpinner: hideSpinner,
        });
    },

    updateProjects: function (projects) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.UPDATE_PROJECTS,
            projects: projects,
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
    /** Open the translate page with the options tab open
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

    /** Open the translate page with the TM tab open
     * @param job
     * @param prName
     */
    openJobTMPanel: function (job, prName) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_JOB_TM_PANEL,
            job: job,
            prName: prName
        });
    },

    removeProject: function (project) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.REMOVE_PROJECT,
            project: project
        });
    },

    removeJob: function (project, job) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.REMOVE_JOB,
            project: project,
            job: job
        });
    },

    changeJobPassword: function (project, job, password, oldPassword) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHANGE_JOB_PASS,
            project: project,
            job: job,
            password: password,
            oldPassword: oldPassword
        });
    },

    noMoreProjects: function () {
        AppDispatcher.dispatch({
            actionType: ManageConstants.NO_MORE_PROJECTS,
        });
    },

    showReloadSpinner: function () {
        AppDispatcher.dispatch({
            actionType: ManageConstants.SHOW_RELOAD_SPINNER,
        });
    },

    changeProjectTeam: function (oldTeam, team, projectId) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHANGE_PROJECT_TEAM,
            oldTeam: oldTeam,
            team: team,
            projectId: projectId
        });
    },

    openCreateTeamModal: function () {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_CREATE_TEAM_MODAL,
        });
    },

    openModifyTeamModal: function (team) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_MODIFY_TEAM_MODAL,
            team: team
        });
    },

    openChangeProjectTeam: function (team, projectId) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_CHANGE_TEAM_MODAL,
            team: team,
            projectId: projectId
        });
    },

    renderTeams: function (teams) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_TEAMS,
            teams: teams
        });
    },

    addTeam: function (team) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.ADD_TEAM,
            team: team
        });
    },

    createTeam: function (teamName) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CREATE_TEAM,
            teamName: teamName
        });
    },

    changeTeam: function (teamName) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHANGE_TEAM,
            teamName: teamName
        });
    },

    changeUser: function (user) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHANGE_USER,
            user: user
        });
    },

    changeProjectAssignee: function (idProject, user, teamName) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHANGE_PROJECT_ASSIGNEE,
            user: user,
            idProject: idProject,
            teamName: teamName
        });
    }







};

module.exports = ManageActions;