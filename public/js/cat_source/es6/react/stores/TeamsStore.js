/*
 * Projects Store
 */

let AppDispatcher = require('../dispatcher/AppDispatcher');
let EventEmitter = require('events').EventEmitter;
let ManageConstants = require('../constants/ManageConstants');
let assign = require('object-assign');
let Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);

let TeamsStore = assign({}, EventEmitter.prototype, {
    
    teams : [],

    users : [],

    updateAll: function (teams) {
        this.teams = Immutable.fromJS(teams);
    },


    addTeam: function(team) {
        this.teams = this.teams.concat(Immutable.fromJS([team]));
    },

    removeTeam: function (team) {
        let index = this.teams.indexOf(team);
        this.teams = this.teams.delete(index);
    },

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case ManageConstants.RENDER_TEAMS:
            TeamsStore.updateAll(action.teams);
            TeamsStore.emitChange(action.actionType, TeamsStore.teams);
            break;
        case ManageConstants.REMOVE_TEAM:
            TeamsStore.removeTeam(action.team);
            TeamsStore.emitChange(ManageConstants.RENDER_TEAMS, TeamsStore.teams);
            break;
        case ManageConstants.CREATE_TEAM:
            TeamsStore.emitChange(ManageConstants.CREATE_TEAM, action.teamName);
            break;
        case ManageConstants.CHANGE_TEAM:
            TeamsStore.emitChange(ManageConstants.CHANGE_TEAM, action.teamName);
            break;
        case ManageConstants.ADD_TEAM:
            TeamsStore.addTeam(action.team);
            TeamsStore.emitChange(ManageConstants.RENDER_TEAMS, TeamsStore.teams, Immutable.fromJS(action.team));
            break;
        case ManageConstants.CHANGE_USER:
            TeamsStore.emitChange(ManageConstants.CHANGE_USER, action.user);
            break;
        case ManageConstants.OPEN_CHANGE_TEAM_MODAL:
            ProjectsStore.emitChange(action.actionType, action.team, action.projectId, TeamsStore.teams);
            break;
    }
});

module.exports = TeamsStore;
