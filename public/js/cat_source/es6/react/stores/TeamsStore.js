/*
 * Projects Store
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var EventEmitter = require('events').EventEmitter;
var ManageConstants = require('../constants/ManageConstants');
var assign = require('object-assign');
var Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);

var TeamsStore = assign({}, EventEmitter.prototype, {
    
    teams : [],

    users : [],

    updateAll: function (teams) {
        this.teams = Immutable.fromJS(teams);
    },


    addTeam: function(team) {
        this.teams = this.teams.concat(Immutable.fromJS([team]));
    },

    removeTeam: function (team) {
        var index = this.teams.indexOf(team);
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
        case ManageConstants.ADD_TEAM:
            TeamsStore.addTeam(action.team);
            TeamsStore.emitChange(ManageConstants.RENDER_TEAMS, TeamsStore.teams, action.team.name);
            break;


    }
});

module.exports = TeamsStore;
