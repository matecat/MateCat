/*
 * Projects Store
 */

let AppDispatcher = require('../dispatcher/AppDispatcher');
let EventEmitter = require('events').EventEmitter;
let ManageConstants = require('../constants/ManageConstants');
let assign = require('object-assign');
let Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);





let ProjectsStore = assign({}, EventEmitter.prototype, {

    projects : null,

    /**
     * Update all
     */
    updateAll: function (projects) {
        this.projects = Immutable.fromJS(projects);
    },
    /**
     * Add Projects (pagination)
     */
    addProjects: function(projects) {
        this.projects = this.projects.concat(Immutable.fromJS(projects));
    },

    removeProject: function (project) {
        let index = this.projects.indexOf(project);
        this.projects = this.projects.delete(index);
    },

    removeJob: function (project, job) {
        let indexProject = this.projects.indexOf(project);
        //Check jobs length
        if (this.projects.get(indexProject).get('jobs').size === 1) {
            this.removeProject(project);
        } else {
            let indexJob = project.get('jobs').indexOf(job);
            this.projects = this.projects.deleteIn([indexProject, 'jobs', indexJob]);
        }

    },

    changeJobPass: function (project, job, password, oldPassword) {
        let indexProject = this.projects.indexOf(project);
        let indexJob = project.get('jobs').indexOf(job);
        
        this.projects = this.projects.setIn([indexProject,'jobs', indexJob, 'password'], password);
        this.projects = this.projects.setIn([indexProject,'jobs', indexJob, 'oldPassword'], oldPassword);
    },

    changeProjectName: function (project, newName) {
        let indexProject = this.projects.indexOf(project);
        this.projects = this.projects.setIn([indexProject,'name'], newName);
    },

    changeProjectAssignee: function (project, user) {
        let indexProject = this.projects.indexOf(project);
        this.projects = this.projects.setIn([indexProject,'user'], user);
    },

    unwrapImmutableObject(object) {
        if (object && typeof object.toJS === "function") {
            return object.toJS();
        } else {
            return object
        }
    },

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case ManageConstants.RENDER_PROJECTS:
            ProjectsStore.updateAll(action.projects);
            ProjectsStore.emitChange(action.actionType, ProjectsStore.projects, Immutable.fromJS(action.organization), action.hideSpinner);
            break;
        case ManageConstants.RENDER_ALL_ORGANIZATION_PROJECTS:
            ProjectsStore.updateAll(action.projects);
            ProjectsStore.emitChange(action.actionType, ProjectsStore.projects, Immutable.fromJS(action.organizations), action.hideSpinner);
            break;
        case ManageConstants.UPDATE_PROJECTS:
            ProjectsStore.updateAll(action.projects);
            ProjectsStore.emitChange(action.actionType, ProjectsStore.projects);
            break;
        case ManageConstants.RENDER_MORE_PROJECTS:
            ProjectsStore.addProjects(action.project);
            ProjectsStore.emitChange(ManageConstants.RENDER_PROJECTS, ProjectsStore.projects);
            break;
        case ManageConstants.FILTER_PROJECTS:
            ProjectsStore.emitChange(ManageConstants.FILTER_PROJECTS, ProjectsStore.unwrapImmutableObject(action.user), ProjectsStore.unwrapImmutableObject(action.workspace), action.name, action.status);
            break;
        case ManageConstants.OPEN_JOB_SETTINGS:
            ProjectsStore.emitChange(ManageConstants.OPEN_JOB_SETTINGS, action.job, action.prName);
            break;
        case ManageConstants.OPEN_JOB_TM_PANEL:
            ProjectsStore.emitChange(ManageConstants.OPEN_JOB_TM_PANEL, action.job, action.prName);
            break;
        case ManageConstants.REMOVE_PROJECT:
            ProjectsStore.removeProject(action.project);
            ProjectsStore.emitChange(ManageConstants.RENDER_PROJECTS, ProjectsStore.projects);
            break;
        case ManageConstants.REMOVE_JOB:
            ProjectsStore.removeJob(action.project, action.job);
            ProjectsStore.emitChange(ManageConstants.RENDER_PROJECTS, ProjectsStore.projects);
            break;
        case ManageConstants.CHANGE_JOB_PASS:
            ProjectsStore.changeJobPass(action.project, action.job, action.password, action.oldPassword);
            ProjectsStore.emitChange(ManageConstants.RENDER_PROJECTS, ProjectsStore.projects);
            break;
        case ManageConstants.NO_MORE_PROJECTS:
            ProjectsStore.emitChange(action.actionType);
            break;
        case ManageConstants.SHOW_RELOAD_SPINNER:
            ProjectsStore.emitChange(action.actionType);
            break;
        case ManageConstants.CHANGE_PROJECT_NAME:
            ProjectsStore.changeProjectName(action.project, action.newName);
            ProjectsStore.emitChange(ManageConstants.UPDATE_PROJECTS, ProjectsStore.projects);
            break;
        case ManageConstants.CHANGE_PROJECT_ASSIGNEE:
            ProjectsStore.changeProjectAssignee(action.project, action.user);
            ProjectsStore.emitChange(ManageConstants.UPDATE_PROJECTS, ProjectsStore.projects);
            break;
        case ManageConstants.CHANGE_PROJECT_WORKSPACE:
            ProjectsStore.emitChange(ManageConstants.UPDATE_PROJECTS, ProjectsStore.projects);
            break;
        // Move this actions
        case ManageConstants.OPEN_CREATE_ORGANIZATION_MODAL:
            ProjectsStore.emitChange(action.actionType);
            break;
        case ManageConstants.OPEN_ASSIGN_TO_TRANSLATOR_MODAL:
            ProjectsStore.emitChange(action.actionType, action.project, action.job);
            break;
        case ManageConstants.OPEN_MODIFY_ORGANIZATION_MODAL:
            ProjectsStore.emitChange(action.actionType, action.organization);
            break;
        case ManageConstants.OPEN_CREATE_WORKSPACE_MODAL:
            ProjectsStore.emitChange(action.actionType);
            break;
    }
});

module.exports = ProjectsStore;