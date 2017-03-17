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

    updateTeam: function (team) {
        let teamOld = this.teams.find(function (org) {
            return org.get('id') == team.id;
        });
        let index = this.teams.indexOf(teamOld);
        this.teams = this.teams.setIn([index], Immutable.fromJS(team));
        return this.teams.get(index);
    },

    updateTeamName: function (team) {
        let teamOld = this.teams.find(function (org) {
            return org.get('id') == team.id;
        });
        let index = this.teams.indexOf(teamOld);
        this.teams = this.teams.setIn([index, 'name'], team.name);
    },

    updateTeamMembers: function (team, members, pendingInvitations) {
        let index = this.teams.indexOf(team);
        this.teams = this.teams.setIn([index,'members'], Immutable.fromJS(members));
        this.teams = this.teams.setIn([index,'pending_invitations'], Immutable.fromJS(pendingInvitations));
        return this.teams.get(index);
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
        case ManageConstants.UPDATE_TEAM_NAME:
            TeamsStore.updateTeamName(action.team);
            TeamsStore.emitChange(ManageConstants.UPDATE_TEAMS, TeamsStore.teams);
            break;
        case ManageConstants.UPDATE_TEAM_MEMBERS:
            let org = TeamsStore.updateTeamMembers(action.team, action.members, action.pending_invitations);
            TeamsStore.emitChange(ManageConstants.UPDATE_TEAM, org);
            TeamsStore.emitChange(ManageConstants.UPDATE_TEAMS, TeamsStore.teams);
            break;
        case ManageConstants.UPDATE_TEAM:
            let updated = TeamsStore.updateTeam(action.team);
            TeamsStore.emitChange(ManageConstants.UPDATE_TEAM, updated);
            TeamsStore.emitChange(ManageConstants.UPDATE_TEAMS, TeamsStore.teams);
            break;
        case ManageConstants.CHOOSE_TEAM:
            TeamsStore.emitChange(action.actionType, action.teamId);
            break;
        case ManageConstants.REMOVE_TEAM:
            TeamsStore.removeTeam(action.team);
            TeamsStore.emitChange(ManageConstants.RENDER_TEAMS, TeamsStore.teams);
            break;
        case ManageConstants.ADD_TEAM:
            TeamsStore.addTeam(action.team);
            TeamsStore.emitChange(ManageConstants.RENDER_TEAMS, TeamsStore.teams);
            break;
    }
});
module.exports = TeamsStore;


