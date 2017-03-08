let AppDispatcher = require('../dispatcher/AppDispatcher');
let ManageConstants = require('../constants/ManageConstants');


let ManageActions = {
    /********* Projects *********/

    /** Render the list of projects
     * @param projects
     * @param teams
     * @param hideSpinner
     * */
    renderProjects: function (projects, teams, hideSpinner) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_PROJECTS,
            projects: projects,
            team: teams,
            hideSpinner: hideSpinner,
        });
    },

    // renderAllTeamssProjects: function (projects, teams, hideSpinner) {
    //     AppDispatcher.dispatch({
    //         actionType: ManageConstants.RENDER_ALL_TEAM_PROJECTS,
    //         projects: projects,
    //         teams: teams,
    //         hideSpinner: hideSpinner,
    //     });
    // },

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

    updateStatusProject: function (project, status) {
        UI.changeJobsOrProjectStatus('prj', project.toJS(), status).done(function () {
            AppDispatcher.dispatch({
                actionType: ManageConstants.HIDE_PROJECT,
                project: project
            });
            setTimeout(function () {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.REMOVE_PROJECT,
                    project: project
                });
            }, 1000);
        });
    },

    changeJobStatus: function (project, job, status) {
        UI.changeJobsOrProjectStatus('job', job.toJS(), status).done(
            function () {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.REMOVE_JOB,
                    project: project,
                    job: job
                });
            }
        );

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

    filterProjects: function (member, name, status) {
        this.showReloadSpinner();
        let memberUid = (member && member.toJS) ? member.get('user').get('uid') : member ;
        UI.filterProjects(memberUid, name, status).then(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.RENDER_PROJECTS,
                projects: response.data,
                hideSpinner: false,
            });
        });
    },

    changeProjectAssignee: function (teams, project, user) {
        var uid;
        if (user === -1) {
            uid = -1
        } else {
            uid = user.get("uid")
        }
        UI.changeProjectAssignee(teams.get("id"), project.get("id"), uid).done(
            function (response) {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHANGE_PROJECT_ASSIGNEE,
                    project: project,
                    user: user
                });
                if (uid !== UI.selectedUser && UI.selectedUser !== ManageConstants.ALL_MEMBERS_FILTER) {
                    setTimeout(function () {
                        AppDispatcher.dispatch({
                            actionType: ManageConstants.HIDE_PROJECT,
                            project: project
                        });
                    }, 500);
                    setTimeout(function () {
                        AppDispatcher.dispatch({
                            actionType: ManageConstants.REMOVE_PROJECT,
                            project: project
                        });
                    }, 1000);
                }
            }
        );

    },

    changeProjectName: function (team, project, newName) {
        UI.changeProjectName(team.get("id"), project.get("id"), newName).done(
            function (response) {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHANGE_PROJECT_NAME,
                    project: project,
                    newProject: response.project
                });
            }
        );

    },


    /********* Modals *********/

    openCreateTeamModal: function () {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_CREATE_TEAM_MODAL,
        });
    },

    openModifyTeamModal: function (team) {
        UI.getTeamMembers(team).then(function (data) {
            team.members = data.members;
            team.pending_invitations = data.pending_invitations;
            AppDispatcher.dispatch({
                actionType: ManageConstants.OPEN_MODIFY_TEAM_MODAL,
                team: team
            });
        });
    },

    openOutsourceModal: function (project, job, url) {
        UI.openOutsourceModal(project.toJS(), job.toJS(), url);
    },

    /********* teams *********/

    renderTeams: function (teams, defaultTeam) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_TEAMS,
            teams: teams,
            defaultTeam: defaultTeam
        });
    },

    createTeam: function (teamName, members) {
        let team;
        let self = this;
        UI.createTeam(teamName, members).then(function (response) {
            team = response.team;
            AppDispatcher.dispatch({
                actionType: ManageConstants.ADD_TEAM,
                team: team
            });
            self.showReloadSpinner();
            UI.changeTeam(team).then(function (response) {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.UPDATE_TEAM,
                    team: UI.selectedTeam
                });
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHOOSE_TEAM,
                    teamId: UI.selectedTeam.id
                });
                AppDispatcher.dispatch({
                    actionType: ManageConstants.RENDER_PROJECTS,
                    projects: response.data,
                    team: team,
                    hideSpinner: false,
                });

            });
        });
    },

    updateTeam: function (team) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.UPDATE_TEAM,
            team: team
        });
    },

    selectTeam: function (team) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.UPDATE_TEAM,
            team: team
        });
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHOOSE_TEAM,
            teamId: team.id
        });
    },

    changeTeam: function (team) {
        this.showReloadSpinner();
        UI.changeTeam(team).then(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_TEAM,
                team: UI.selectedTeam
            });
            AppDispatcher.dispatch({
                actionType: ManageConstants.CHOOSE_TEAM,
                teamId: UI.selectedTeam.id
            });
            AppDispatcher.dispatch({
                actionType: ManageConstants.RENDER_PROJECTS,
                projects: response.data,
                team: team,
                hideSpinner: false,
            });

        });
    },

    addUserToTeam: function (team, userEmail) {
        UI.addUserToTeam(team.toJS(), userEmail).done(function (data) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_TEAM_MEMBERS,
                team: team,
                members: data.members,
                pending_invitations: data.pending_invitations
            });
        });
    },

    removeUserFromTeam: function (team, user) {
        var self = this;
        var userId = user.get('uid');
        UI.removeUserFromTeam(team.toJS(), userId).done(function (data) {
            if (userId === APP.USER.STORE.user.uid ) {
                if ( UI.selectedTeam.id === team.get('id')) {
                    UI.getAllTeams(true).done(function (data) {
                        AppDispatcher.dispatch({
                            actionType: ManageConstants.RENDER_TEAMS,
                            teams: data.teams,
                        });
                        self.changeTeam(data.teams[0]);

                    });
                } else {
                    AppDispatcher.dispatch({
                        actionType: ManageConstants.REMOVE_TEAM,
                        team: team,
                    });
                }
            } else {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.UPDATE_TEAM_MEMBERS,
                    team: team,
                    members: data.members,
                    pending_invitations: data.pending_invitations
                });
                //TODO Refresh current Projects
                UI.removeUserFilter(userId);
                UI.reloadProjects();
            }
        });
    },
    changeTeamName: function(team, newName) {
        UI.changeTeamName(team, newName).done(function (data) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_TEAM_NAME,
                oldTeam: team,
                team: data.team[0],
            });
        });
    },

    changeTeamFromUploadPage: function () {
        $('.reloading-upload-page').show();
        setTimeout(function () {
            $('.reloading-upload-page').hide();
        }, 1000)
    },

    enableDownloadButton: function (id) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.ENABLE_DOWNLOAD_BUTTON,
            idProject: id
        });
    },

    disableDownloadButton: function (id) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.DISABLE_DOWNLOAD_BUTTON,
            idProject: id
        });
    }

};

module.exports = ManageActions;