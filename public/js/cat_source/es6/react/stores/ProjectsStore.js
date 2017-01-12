/*
 * Projects Store
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var EventEmitter = require('events').EventEmitter;
var ManageConstants = require('../constants/ManageConstants');
var assign = require('object-assign');
var Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);





var ProjectsStore = assign({}, EventEmitter.prototype, {

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
        var index = this.projects.indexOf(project);
        this.projects = this.projects.delete(index);
    },

    removeJob: function (project, job) {
        var indexProject = this.projects.indexOf(project);
        //Check jobs length
        if (this.projects.get(indexProject).get('jobs').size === 1) {
            this.removeProject(project);
        } else {
            var indexJob = project.get('jobs').indexOf(job);
            this.projects = this.projects.deleteIn([indexProject, 'jobs', indexJob]);
        }

    },

    changeJobPass: function (project, job, password, oldPassword) {
        var indexProject = this.projects.indexOf(project);
        var indexJob = project.get('jobs').indexOf(job);
        
        this.projects = this.projects.setIn([indexProject,'jobs', indexJob, 'password'], password);
        this.projects = this.projects.setIn([indexProject,'jobs', indexJob, 'oldPassword'], oldPassword);
    },

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case ManageConstants.RENDER_PROJECTS:
            ProjectsStore.updateAll(action.project);
            ProjectsStore.emitChange(action.actionType, ProjectsStore.projects);
            break;
        case ManageConstants.RENDER_MORE_PROJECTS:
            ProjectsStore.addProjects(action.project);
            ProjectsStore.emitChange(ManageConstants.RENDER_PROJECTS, ProjectsStore.projects);
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

    }
});

module.exports = ProjectsStore;