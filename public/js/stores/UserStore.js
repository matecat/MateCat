/*
 * Projects Store
 */

import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import ManageConstants from '../constants/ManageConstants'
import UserConstants from '../constants/UserConstants'
import assign from 'object-assign'
import {fromJS} from 'immutable'

EventEmitter.prototype.setMaxListeners(0)

const UserStore = assign({}, EventEmitter.prototype, {
  teams: fromJS([]),
  selectedTeam: {},
  userInfo: null,

  updateTeams: function (teams) {
    this.teams = fromJS(teams)
  },

  addTeam: function (team) {
    this.teams = this.teams.concat(fromJS([team]))
  },
  updateUser: function (user) {
    this.userInfo = user
    user?.teams && this.updateTeams(user.teams)
  },
  updateUserName: function ({firstName, lastName}) {
    this.userInfo.user.first_name = firstName
    this.userInfo.user.last_name = lastName
  },
  updateTeam: function (team) {
    let teamOld = this.teams.find(function (org) {
      return org.get('id') == team.id
    })
    let index = this.teams.indexOf(teamOld)
    this.teams = this.teams.setIn([index], fromJS(team))
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
    this.teams = this.teams.setIn([index, 'members'], fromJS(members))
    this.teams = this.teams.setIn(
      [index, 'pending_invitations'],
      fromJS(pendingInvitations),
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
    return this.userInfo
  },
  getUserName: function () {
    return this.userInfo
      ? `${this.userInfo.user.first_name} ${this.userInfo.user.last_name}`
      : 'Anonymous'
  },
  getUserMetadata: function () {
    return this.userInfo ? this.userInfo.metadata : undefined
  },
  isUserLogged: function () {
    return !!this.userInfo
  },
  getDefaultConnectedService: function () {
    if (this.userInfo.connected_services.length) {
      const selectable = this.userInfo.connected_services.filter((item) => {
        return !item.expired_at && !item.disabled_at
      })
      const defaultService = selectable.find((item) => {
        return item.is_default
      })
      return defaultService || selectable[0]
    }
  },
  updateConnectedService: function (input_service) {
    this.userInfo.connected_services = this.userInfo.connected_services.map(
      (service) => {
        if (service.id === input_service.id) {
          return input_service
        }
        return service
      },
    )
    return this.userInfo.connected_services
  },
  emitChange: function () {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case UserConstants.RENDER_TEAMS:
      UserStore.updateTeams(action.teams)
      UserStore.emitChange(action.actionType, UserStore.teams)
      break
    case ManageConstants.UPDATE_TEAM_NAME:
      UserStore.emitChange(
        UserConstants.UPDATE_TEAM,
        UserStore.updateTeamName(action.team),
      )
      UserStore.emitChange(UserConstants.UPDATE_TEAMS, UserStore.teams)
      break
    case ManageConstants.UPDATE_TEAM_MEMBERS:
      UserStore.emitChange(
        UserConstants.UPDATE_TEAM,
        UserStore.updateTeamMembers(
          action.team,
          action.members,
          action.pending_invitations,
        ),
      )
      UserStore.emitChange(UserConstants.UPDATE_TEAMS, UserStore.teams)
      break
    case UserConstants.UPDATE_TEAM:
      UserStore.emitChange(
        UserConstants.UPDATE_TEAM,
        UserStore.updateTeam(action.team),
      )
      UserStore.emitChange(UserConstants.UPDATE_TEAMS, UserStore.teams)
      break
    case UserConstants.UPDATE_TEAMS:
      UserStore.updateTeams(action.teams)
      UserStore.emitChange(UserConstants.UPDATE_TEAMS, UserStore.teams)
      break
    case UserConstants.CHOOSE_TEAM:
      UserStore.emitChange(action.actionType, action.teamId, action.team)
      break
    case ManageConstants.REMOVE_TEAM:
      UserStore.removeTeam(action.team)
      UserStore.emitChange(UserConstants.RENDER_TEAMS, UserStore.teams)
      break
    case UserConstants.ADD_TEAM:
      UserStore.addTeam(action.team)
      UserStore.emitChange(UserConstants.RENDER_TEAMS, UserStore.teams)
      break
    case UserConstants.UPDATE_USER:
      UserStore.updateUser(action.user)
      UserStore.emitChange(UserConstants.UPDATE_USER, UserStore.userInfo)
      break
    // Move this actions
    case ManageConstants.OPEN_CREATE_TEAM_MODAL:
      UserStore.emitChange(action.actionType)
      break
    case ManageConstants.OPEN_MODIFY_TEAM_MODAL:
      UserStore.emitChange(
        action.actionType,
        fromJS(action.team),
        action.hideChangeName,
      )
      break
    case ManageConstants.OPEN_INFO_TEAMS_POPUP:
      UserStore.emitChange(action.actionType)
      break
    case ManageConstants.SELECTED_TEAM:
      UserStore.selectedTeam = action.selectedTeam
      break
    case UserConstants.FORCE_RELOAD:
      UserStore.emitChange(UserConstants.FORCE_RELOAD)
      break
  }
})
export default UserStore
