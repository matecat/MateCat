import AppDispatcher from '../stores/AppDispatcher';
import ManageConstants from '../constants/ManageConstants';
import TeamConstants from '../constants/TeamConstants';
import Immutable from 'immutable';


let ManageActions = {
    /********* Projects *********/
    initialRender: function(teams, selectedTeam) {
        var mountPoint = $("#manage-container")[0];
        return ReactDOM.render(React.createElement(ProjectsContainer, {
            getLastActivity: API.PROJECTS.getLastProjectActivityLogAction,
            changeJobPasswordFn: API.JOB.changeJobPassword,
            downloadTranslationFn : UI.downloadTranslation,
            teams: Immutable.fromJS(teams),
            team: Immutable.fromJS(selectedTeam)
        }), mountPoint);
    },
    /** Render the list of projects
     * @param projects
     * @param team
     * @param teams
     * @param hideSpinner
     * */
    renderProjects: function (projects, team, teams, hideSpinner) {

        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_PROJECTS,
            projects: projects,
            team: team,
            teams: teams,
            hideSpinner: hideSpinner,
            filtering: false
        });
        if (teams) {
            AppDispatcher.dispatch({
                actionType: TeamConstants.RENDER_TEAMS,
                teams: teams,
            });
        }

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

    updateStatusProject: function (project, status) {
        API.PROJECTS.changeJobsOrProjectStatus('prj', project.toJS(), status).done(function () {
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
        API.PROJECTS.changeJobsOrProjectStatus('job', job.toJS(), status).done(
            function () {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.REMOVE_JOB,
                    project: project,
                    job: job
                });
            }
        );

    },

    changeJobPassword: function (project, job, password, oldPassword, translator) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHANGE_JOB_PASS,
            projectId: project.get('id'),
            jobId: job.get('id'),
            password: password,
            oldPassword: oldPassword,
            oldTranslator: translator
        });
    },

    changeJobPasswordFromOutsource: function (projectId, jobId, oldPassword, password) {
        if ($('body').hasClass('manage')) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.CHANGE_JOB_PASS,
                projectId: projectId,
                jobId: jobId,
                password: password,
                oldPassword: oldPassword
            });
        } else {
            UI.updateJobPassword(password);
        }
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
                filtering: true
            });
        });
    },

    changeProjectAssignee: function (team, project, user) {
        var uid;
        if (user === -1) {
            uid = -1
        } else {
            uid = user.get("uid")
        }
        API.PROJECTS.changeProjectAssignee(team.get("id"), project.get("id"), uid).done(
            function (response) {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHANGE_PROJECT_ASSIGNEE,
                    project: project,
                    user: user
                });
                if ( (uid !== UI.selectedUser && UI.selectedUser !== ManageConstants.ALL_MEMBERS_FILTER) ||
                    ( UI.selectedTeam.type == 'personal' && uid !== APP.USER.STORE.user.uid) ) {
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
                    let name = (user.toJS)? user.get('first_name') + ' ' + user.get('last_name') : "Not assigned";
                    let notification = {
                        title: 'Assignee changed',
                        text: 'The project ' + project.get('name') + ' has been assigned to ' + name,
                        type: 'success',
                        position: 'bl',
                        allowHtml: true,
                        timer: 3000
                    };
                    let boxUndo = APP.addNotification(notification);
                }
                API.TEAM.getTeamMembers(team.get("id")).done(function (data) {
                    team = team.set('members', data.members);
                    team = team.set('pending_invitations', data.pending_invitations);
                    AppDispatcher.dispatch({
                        actionType: TeamConstants.UPDATE_TEAM,
                        team: team.toJS()
                    });
                });
            }
        ).fail(function (response) {
            console.log("Error change assignee", response);
            UI.showNotificationProjectsChanged();
            UI.reloadProjects();
        });


    },

    changeProjectName: function (team, project, newName) {
        API.PROJECTS.changeProjectName(team.get("id"), project.get("id"), newName).done(
            function (response) {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHANGE_PROJECT_NAME,
                    project: project,
                    newProject: response.project
                });
            }
        );

    },

    changeProjectTeam: function (teamId, project) {
        API.PROJECTS.changeProjectTeam(teamId, project.toJS()).done(function () {
            var team =  TeamsStore.teams.find(function (team) {
                return team.get('id') == teamId
            });
            team = team.toJS();
            if (UI.selectedTeam.type == 'personal' && team.type !== 'personal') {

                API.TEAM.getTeamMembers(teamId).then(function (data) {
                    team.members = data.members;
                    team.pending_invitations = data.pending_invitations;
                    AppDispatcher.dispatch({
                        actionType: TeamConstants.UPDATE_TEAM,
                        team: team
                    });
                    setTimeout(function () {
                        AppDispatcher.dispatch({
                            actionType: ManageConstants.CHANGE_PROJECT_TEAM,
                            project: project,
                            teamId: teamId
                        });
                    });
                });
            } else if (teamId !== UI.selectedTeam.id && UI.selectedTeam.type !== 'personal') {
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
                let notification = {
                    title: 'Project Moved',
                    text: 'The project ' + project.get('name') + ' has been moved to the ' + team.name + ' Team',
                    type: 'success',
                    position: 'bl',
                    allowHtml: true,
                    timer: 3000
                };
                let boxUndo = APP.addNotification(notification);
                API.TEAM.getTeamMembers(UI.selectedTeam.id).then(function (data) {
                    UI.selectedTeam.members = data.members;
                    UI.selectedTeam.pending_invitations = data.pending_invitations;
                    AppDispatcher.dispatch({
                        actionType: TeamConstants.UPDATE_TEAM,
                        team: UI.selectedTeam
                    });
                    setTimeout(function () {
                        AppDispatcher.dispatch({
                            actionType: ManageConstants.CHANGE_PROJECT_TEAM,
                            project: project,
                            teamId: UI.selectedTeam.id
                        });
                    });
                });

            }
            // else {
            //     AppDispatcher.dispatch({
            //         actionType: ManageConstants.CHANGE_PROJECT_TEAM,
            //         project: project,
            //         teamId: teamId
            //     });
            // }

        }).fail(function (response) {
            console.log("Error change assignee", response);
            UI.showNotificationProjectsChanged();
            UI.reloadProjects();
        });
    },

    assignTranslator: function (projectId, jobId, jobPassword, translator) {
        if ($('body').hasClass('manage')) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.ASSIGN_TRANSLATOR,
                projectId: projectId,
                jobId: jobId,
                jobPassword: jobPassword,
                translator: translator
            });
        } else {
            //TODO Delete this function in the new analysis version
            UI.updateOutsourceInfo(translator);
        }
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
    },

    setPopupTeamsCookie: function () {
        UI.setPopupTeamsCookie();
    },

    getSecondPassReview: function(idProject, passwordProject, idJob, passwordJob) {
        return API.PROJECTS.getSecondPassReview(idProject, passwordProject, idJob, passwordJob).then( function ( data ) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.ADD_SECOND_PASS,
                idProject: idProject,
                passwordProject: passwordProject,
                idJob: idJob,
                passwordJob: passwordJob,
                secondPAssPassword: data.chunk_review.review_password
            });
        });


    },

    /********* Modals *********/

    openModifyTeamModal: function (team) {
        API.TEAM.getTeamMembers(team.id).then(function (data) {
            team.members = data.members;
            team.pending_invitations = data.pending_invitations;
            AppDispatcher.dispatch({
                actionType: ManageConstants.OPEN_MODIFY_TEAM_MODAL,
                team: team,
                hideChangeName: false
            });
        });
    },

    openAddTeamMemberModal: function (team) {
        API.TEAM.getTeamMembers(team.id).then(function (data) {
            team.members = data.members;
            team.pending_invitations = data.pending_invitations;
            AppDispatcher.dispatch({
                actionType: ManageConstants.OPEN_MODIFY_TEAM_MODAL,
                team: team,
                hideChangeName: true
            });
        });
    },

    openPopupTeams: function () {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_INFO_TEAMS_POPUP,
        });
    },

    /********* Teams: actions from modals *********/

    /**
     * Called from manage modal
     * @param teamName
     * @param members
     */
    createTeam: function (teamName, members) {
        let team;
        let self = this;
        TeamsActions.createTeam(teamName, members).then(function (response) {
            UI.teams.push(response.team);
            self.showReloadSpinner();
            UI.changeTeam(response.team).then(function (response) {
                AppDispatcher.dispatch({
                    actionType: TeamConstants.UPDATE_TEAM,
                    team: UI.selectedTeam
                });
                AppDispatcher.dispatch({
                    actionType: TeamConstants.CHOOSE_TEAM,
                    teamId: UI.selectedTeam.id
                });
                AppDispatcher.dispatch({
                    actionType: ManageConstants.RENDER_PROJECTS,
                    projects: response.data,
                    team: UI.selectedTeam,
                    hideSpinner: false,
                    filtering: false
                });

            });
        });
    },

    changeTeam: function (team) {
        this.showReloadSpinner();
        UI.changeTeam(team).then(function (response) {
            AppDispatcher.dispatch({
                actionType: TeamConstants.UPDATE_TEAM,
                team: UI.selectedTeam
            });
            AppDispatcher.dispatch({
                actionType: TeamConstants.CHOOSE_TEAM,
                teamId: UI.selectedTeam.id
            });
            AppDispatcher.dispatch({
                actionType: ManageConstants.RENDER_PROJECTS,
                projects: response.data,
                team: team,
                hideSpinner: false,
                filtering: false
            });
        });
    },

    addUserToTeam: function (team, userEmail) {
        API.TEAM.addUserToTeam(team.toJS(), userEmail).done(function (data) {
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
        API.TEAM.removeUserFromTeam(team.toJS(), userId).done(function (data) {
            if (userId === APP.USER.STORE.user.uid ) {
                if ( UI.selectedTeam.id === team.get('id')) {
                    API.TEAM.getAllTeams(true).done(function (data) {
                        AppDispatcher.dispatch({
                            actionType: TeamConstants.RENDER_TEAMS,
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
                if (UI.selectedTeam.type === 'personal') {
                    UI.reloadProjects();
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
        API.TEAM.changeTeamName(team, newName).done(function (data) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_TEAM_NAME,
                oldTeam: team,
                team: data.team[0],
            });
        });
    }

};

module.exports = ManageActions;