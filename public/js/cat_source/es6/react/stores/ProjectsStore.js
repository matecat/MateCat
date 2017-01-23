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

    teams : [
        {
            name: 'Ebay',
            users: []
        },
        {
            name: 'MSC',
            users: []
        },
        {
            name: 'Translated',
            users: []
        }
    ],

    users : [
        {
            userMail: 'chloe.king@translated.net',
            userFullName: 'Chloe King',
            userShortName: 'CK'

        },{
            userMail: 'owen.james@translated.net',
            userFullName: 'Owen	James',
            userShortName: 'OJ'

        },{
            userMail: 'stephen.powell@translated.net',
            userFullName: 'Stephen Powell',
            userShortName: 'SP'

        },{
            userMail: 'lillian.lambert@translated.net',
            userFullName: 'Lillian	Lambert',
            userShortName: 'LL'

        },{
            userMail: 'joe.watson@translated.net',
            userFullName: 'Joe	Watson',
            userShortName: 'JW'

        },{
            userMail: 'rachel.sharp@translated.net',
            userFullName: 'Rachel	Sharp',
            userShortName: 'RS'

        },{
            userMail: 'dan.marshall@translated.net',
            userFullName: 'Dan	Marshall',
            userShortName: 'DM'

        },{
            userMail: 'vanessa.simpson@translated.net',
            userFullName: 'Vanessa	Simpson',
            userShortName: 'VS'

        },{
            userMail: 'dan.howard@translated.net',
            userFullName: 'Dan	Howard',
            userShortName: 'DH'

        },{
            userMail: 'keith.kelly@translated.net',
            userFullName: 'Keith	Kelly',
            userShortName: 'KC'

        }
    ],

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
            ProjectsStore.emitChange(action.actionType, ProjectsStore.projects, Immutable.fromJS(action.team), action.hideSpinner);
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
        case ManageConstants.SHOW_RELOAD_SPINNER:
            ProjectsStore.emitChange(action.actionType);
            break;
        case ManageConstants.OPEN_CREATE_TEAM_MODAL:
            ProjectsStore.emitChange(action.actionType);
            break;
        case ManageConstants.OPEN_MODIFY_TEAM_MODAL:
            ProjectsStore.emitChange(action.actionType, action.team);
            break;
        case ManageConstants.OPEN_CHANGE_TEAM_MODAL:
            ProjectsStore.emitChange(action.actionType);
            break;
        case ManageConstants.CHANGE_PROJECT_ASSIGNEE:
            ProjectsStore.emitChange(action.actionType, action.idProject, action.user, action.teamName);
            break;

    }
});

module.exports = ProjectsStore;