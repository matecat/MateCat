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

    }
});

module.exports = ProjectsStore;