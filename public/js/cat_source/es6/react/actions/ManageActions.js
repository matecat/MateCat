let AppDispatcher = require('../dispatcher/AppDispatcher');
let ManageConstants = require('../constants/ManageConstants');


let ManageActions = {
    /********* Projects *********/

    /** Render the list of projects
     * @param projects
     * @param organization
     * @param hideSpinner
     * */
    renderProjects: function (projects, organization, hideSpinner) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_PROJECTS,
            projects: projects,
            organization: organization,
            hideSpinner: hideSpinner,
        });
    },

    // renderAllOrganizationsProjects: function (projects, organizations, hideSpinner) {
    //     AppDispatcher.dispatch({
    //         actionType: ManageConstants.RENDER_ALL_ORGANIZATION_PROJECTS,
    //         projects: projects,
    //         organizations: organizations,
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

    filterProjects: function (member, workspace, name, status) {
        this.showReloadSpinner();
        let memberUid = (member && member.toJS) ? member.get('user').get('uid') : member ;
        let workspaceId = (workspace && workspace.toJS) ? workspace.get('id') : workspace ;
        UI.filterProjects(memberUid, workspaceId, name, status).then(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.RENDER_PROJECTS,
                projects: response.data,
                hideSpinner: false,
            });
        });
    },

    changeProjectAssignee: function (organization, project, user) {
        var uid;
        if (user === -1) {
            uid = -1
        } else {
            uid = user.get("uid")
        }
        UI.changeProjectAssignee(organization.get("id"), project.get("id"), uid).done(
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

    changeProjectWorkspace: function (workspaceId, project) {
        UI.changeProjectWorkspace(workspaceId, project.get('id')).done(function () {
            AppDispatcher.dispatch({
                actionType: ManageConstants.CHANGE_PROJECT_WORKSPACE,
                project: project,
                workspaceId: workspaceId
            });
            if (workspaceId !== UI.selectedWorkspace && UI.selectedWorkspace !== ManageConstants.ALL_WORKSPACES_FILTER) {
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

        });

    },

    changeProjectName: function (organization, project, newName) {
        UI.changeProjectName(organization.get("id"), project.get("id"), newName).done(
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

    openCreateOrganizationModal: function () {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_CREATE_ORGANIZATION_MODAL,
        });
    },

    openModifyOrganizationModal: function (organization) {
        UI.getOrganizationMembers(organization).then(function (data) {
            organization.members = data.members;
            organization.pending_invitations = data.pending_invitations;
            AppDispatcher.dispatch({
                actionType: ManageConstants.OPEN_MODIFY_ORGANIZATION_MODAL,
                organization: organization
            });
        });
    },
    openCreateWorkspaceModal: function (organization) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_CREATE_WORKSPACE_MODAL,
            organization: organization
        });
    },

    openAssignToTranslator: function (project, job) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_ASSIGN_TO_TRANSLATOR_MODAL,
            project: project,
            job: job
        });
    },

    /********* Organizations *********/

    renderOrganizations: function (organizations, defaultOrganization) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.RENDER_ORGANIZATIONS,
            organizations: organizations,
            defaultOrganization: defaultOrganization
        });
    },

    createOrganization: function (organizationName, members) {
        let organization;
        let self = this;
        UI.createOrganization(organizationName, members).then(function (response) {
            organization = response.organization;
                // UI.getWorkspaces(organization).then(function (data) {
                //     organization.workspaces = data.workspaces;
                //     AppDispatcher.dispatch({
                //         actionType: ManageConstants.ADD_ORGANIZATION,
                //         organization: organization
                //     });
                // });
            AppDispatcher.dispatch({
                actionType: ManageConstants.ADD_ORGANIZATION,
                organization: organization
            });
            self.showReloadSpinner();
            UI.changeOrganization(organization).then(function (response) {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.UPDATE_ORGANIZATION,
                    organization: UI.selectedOrganization
                });
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHOOSE_ORGANIZATION,
                    organizationId: UI.selectedOrganization.id
                });
                AppDispatcher.dispatch({
                    actionType: ManageConstants.RENDER_PROJECTS,
                    projects: response.data,
                    organization: organization,
                    hideSpinner: false,
                });

            });
        });
    },

    updateOrganization: function (organization) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.UPDATE_ORGANIZATION,
            organization: organization
        });
    },

    selectOrganization: function (organization) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.UPDATE_ORGANIZATION,
            organization: organization
        });
        AppDispatcher.dispatch({
            actionType: ManageConstants.CHOOSE_ORGANIZATION,
            organizationId: organization.id
        });
    },

    changeOrganization: function (organization) {
        this.showReloadSpinner();
        UI.changeOrganization(organization).then(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_ORGANIZATION,
                organization: UI.selectedOrganization
            });
            AppDispatcher.dispatch({
                actionType: ManageConstants.CHOOSE_ORGANIZATION,
                organizationId: UI.selectedOrganization.id
            });
            AppDispatcher.dispatch({
                actionType: ManageConstants.RENDER_PROJECTS,
                projects: response.data,
                organization: organization,
                hideSpinner: false,
            });

        });
    },

    createWorkspace: function (organization, wsName) {
        UI.createWorkspace(organization,wsName).done(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.CREATE_WORKSPACE,
                organization: organization,
                workspace: response.workspace,
            });
        });
    },

    removeWorkspace: function (organization, ws) {
        UI.removeWorkspace(organization.toJS(), ws.toJS()).done(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.REMOVE_WORKSPACE,
                workspace: ws,
            });
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_WORKSPACES,
                organization: organization,
                workspaces: response.workspaces,
            });
        });
    },

    renameWorkspace: function (organization, ws) {
        UI.renameWorkspace(organization.toJS(), ws.toJS()).done(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_WORKSPACE,
                organization: organization,
                workspace: response.workspace,
            });
        });
    },

    addUserToOrganization: function (organization, userEmail) {
        UI.addUserToOrganization(organization.toJS(), userEmail).done(function (data) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_ORGANIZATION_MEMBERS,
                organization: organization,
                members: data.members,
                pending_invitations: data.pending_invitations
            });
        });
    },

    removeUserFromOrganization: function (organization, user) {
        var self = this;
        var userId = user.get('uid');
        UI.removeUserFromOrganization(organization.toJS(), userId).done(function (data) {
            if (userId === APP.USER.STORE.user.uid ) {
                if ( UI.selectedOrganization.id === organization.get('id')) {
                    UI.getAllOrganizations(true).done(function (data) {
                        AppDispatcher.dispatch({
                            actionType: ManageConstants.RENDER_ORGANIZATIONS,
                            organizations: data.organizations,
                        });
                        self.changeOrganization(data.organizations[0]);

                    });
                } else {
                    AppDispatcher.dispatch({
                        actionType: ManageConstants.REMOVE_ORGANIZATION,
                        organization: organization,
                    });
                }
            } else {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.UPDATE_ORGANIZATION_MEMBERS,
                    organization: organization,
                    members: data.members,
                    pending_invitations: data.pending_invitations
                });
                //TODO Refresh current Projects
                UI.removeUserFilter(userId);
                UI.reloadProjects();
            }
        });
    },
    changeOrganizationName: function(organization, newName) {
        UI.changeOrganizationName(organization, newName).done(function (data) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_ORGANIZATION_NAME,
                oldOrganization: organization,
                organization: data.organization[0],
            });
        });
    },

    changeOrganizationFromUploadPage: function () {
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