/*
 * Projects Store
 */

import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import ManageConstants from '../constants/ManageConstants'
import TeamConstants from '../constants/TeamConstants'
import assign from 'object-assign'
import Immutable from 'immutable'

EventEmitter.prototype.setMaxListeners(0)

let TeamsStore = assign({}, EventEmitter.prototype, {
  teams: Immutable.fromJS([]),
  selectedTeam: {},
  user: null,

  updateAll: function (teams) {
    this.teams = Immutable.fromJS(teams)
  },

  addTeam: function (team) {
    this.teams = this.teams.concat(Immutable.fromJS([team]))
  },
  updateUser: function (user) {
    this.user = user
  },
  updateTeam: function (team) {
    let teamOld = this.teams.find(function (org) {
      return org.get('id') == team.id
    })
    let index = this.teams.indexOf(teamOld)
    this.teams = this.teams.setIn([index], Immutable.fromJS(team))
    return this.teams.get(index)
  },

  updateTeamName: function (team) {
    let teamOld = this.teams.find(function (org) {
      return org.get('id') == team.id
    })
    let index = this.teams.indexOf(teamOld)
    this.teams = this.teams.setIn([index, 'name'], team.name)
    return this.teams.get(index)
  },

  updateTeamMembers: function (team, members, pendingInvitations) {
    let teamOld = this.teams.find(function (org) {
      return org.get('id') == team.get('id')
    })
    let index = this.teams.indexOf(teamOld)
    this.teams = this.teams.setIn([index, 'members'], Immutable.fromJS(members))
    this.teams = this.teams.setIn(
      [index, 'pending_invitations'],
      Immutable.fromJS(pendingInvitations),
    )
    return this.teams.get(index)
  },

  removeTeam: function (team) {
    let index = this.teams.indexOf(team)
    if (index !== -1) this.teams = this.teams.delete(index)
  },

  getSelectedTeam: function () {
    return this.selectedTeam
  },

  getAllTeams: function () {
    return this.teams.toJS()
  },

  getUser: function () {
    return this.user
  },

  emitChange: function () {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case TeamConstants.RENDER_TEAMS:
      TeamsStore.updateAll(action.teams)
      TeamsStore.emitChange(action.actionType, TeamsStore.teams)
      break
    case ManageConstants.UPDATE_TEAM_NAME:
      TeamsStore.emitChange(
        TeamConstants.UPDATE_TEAM,
        TeamsStore.updateTeamName(action.team),
      )
      TeamsStore.emitChange(TeamConstants.UPDATE_TEAMS, TeamsStore.teams)
      break
    case ManageConstants.UPDATE_TEAM_MEMBERS:
      TeamsStore.emitChange(
        TeamConstants.UPDATE_TEAM,
        TeamsStore.updateTeamMembers(
          action.team,
          action.members,
          action.pending_invitations,
        ),
      )
      TeamsStore.emitChange(TeamConstants.UPDATE_TEAMS, TeamsStore.teams)
      break
    case TeamConstants.UPDATE_TEAM:
      TeamsStore.emitChange(
        TeamConstants.UPDATE_TEAM,
        TeamsStore.updateTeam(action.team),
      )
      TeamsStore.emitChange(TeamConstants.UPDATE_TEAMS, TeamsStore.teams)
      break
    case TeamConstants.UPDATE_TEAMS:
      TeamsStore.updateAll(action.teams)
      TeamsStore.emitChange(TeamConstants.UPDATE_TEAMS, TeamsStore.teams)
      break
    case TeamConstants.CHOOSE_TEAM:
      TeamsStore.emitChange(action.actionType, action.teamId, action.team)
      break
    case ManageConstants.REMOVE_TEAM:
      TeamsStore.removeTeam(action.team)
      TeamsStore.emitChange(TeamConstants.RENDER_TEAMS, TeamsStore.teams)
      break
    case TeamConstants.ADD_TEAM:
      TeamsStore.addTeam(action.team)
      TeamsStore.emitChange(TeamConstants.RENDER_TEAMS, TeamsStore.teams)
      break
    case TeamConstants.UPDATE_USER:
      TeamsStore.updateUser(action.user)
      TeamsStore.emitChange(TeamConstants.UPDATE_USER, TeamsStore.user)
      break
    // Move this actions
    case ManageConstants.OPEN_CREATE_TEAM_MODAL:
      TeamsStore.emitChange(action.actionType)
      break
    case ManageConstants.OPEN_MODIFY_TEAM_MODAL:
      TeamsStore.emitChange(
        action.actionType,
        Immutable.fromJS(action.team),
        action.hideChangeName,
      )
      break
    case ManageConstants.OPEN_INFO_TEAMS_POPUP:
      TeamsStore.emitChange(action.actionType)
      break
    case ManageConstants.SELECTED_TEAM:
      TeamsStore.selectedTeam = action.selectedTeam
  }
})
module.exports = TeamsStore
