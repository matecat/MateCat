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
        UI.changeJobsOrProjectStatus('prj', project.toJS(), status).done(
            function () {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.REMOVE_PROJECT,
                    project: project
                });
            }
        );
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

    filterProjects: function (user, workspace, name, status) {
        user = (user) ? user.toJS() : user ;
        workspace = (workspace) ? workspace.toJS() : workspace ;
        UI.filterProjects(user, workspace, name, status).then(function (response) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.RENDER_PROJECTS,
                projects: response.data,
                organization: UI.selectedOrganization,
                hideSpinner: false,
            });
        });
    },

    changeProjectAssignee: function (organization, project, user) {
        UI.changeProjectAssignee(organization.get("id"), project.get("id"), user.get("uid")).done(
            function (response) {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHANGE_PROJECT_ASSIGNEE,
                    project: project,
                    user: user
                });
            }
        );

    },

    changeProjectWorkspace: function () {
        UI.changeProjectWorkspace(oldWorkspace, newWorkspace, projectId).done(function () {
            AppDispatcher.dispatch({
                actionType: ManageConstants.CHANGE_PROJECT_WORKSPACE,
                oldOrganization: oldWorkspace,
                workspace: newWorkspace,
                projectId: projectId
            });
        });

    },

    changeProjectName: function (organization, project, newName) {
        UI.changeProjectName(organization.get("id"), project.get("id"), newName).done(
            function () {
                AppDispatcher.dispatch({
                    actionType: ManageConstants.CHANGE_PROJECT_NAME,
                    project: project,
                    newName: newName
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

    openChangeProjectWorkspace: function (organization, projectId) {
        AppDispatcher.dispatch({
            actionType: ManageConstants.OPEN_CHANGE_ORGANIZATION_MODAL,
            organization: organization,
            projectId: projectId
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
        UI.createOrganization(organizationName, members).then(function (response) {
                organization = response.organization[0];
                UI.getWorkspaces(organization).then(function (data) {
                    organization.workspaces = data.workspaces;
                    AppDispatcher.dispatch({
                        actionType: ManageConstants.ADD_ORGANIZATION,
                        organization: response.organization[0]
                    });
                });
            }
        );
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
        UI.removeWorkspace(organization,ws).done(function (response) {
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
                members: data.members
            });
        });
    },

    removeUserFromOrganization: function (organization, userId) {
        UI.removeUserFromOrganization(organization.toJS(), userId).done(function (data) {
            AppDispatcher.dispatch({
                actionType: ManageConstants.UPDATE_ORGANIZATION_MEMBERS,
                organization: organization,
                members: data.members
            });
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
    }

};

module.exports = ManageActions;