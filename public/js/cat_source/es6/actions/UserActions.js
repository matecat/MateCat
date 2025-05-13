import $ from 'jquery'
import AppDispatcher from '../stores/AppDispatcher'
import UserConstants from '../constants/UserConstants'
import {getTeamMembers} from '../api/getTeamMembers'
import UserStore from '../stores/UserStore'

let UserActions = {
  teamStorageName: 'defaultTeam',

  updateUser: function (user) {
    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_USER,
      user: user,
    })
  },
  updateUserName: function (info) {
    AppDispatcher.dispatch({
      actionType: TeamConstants.UPDATE_USER_NAME,
      info: info,
    })
  },

  renderTeams: function (teams, defaultTeam) {
    AppDispatcher.dispatch({
      actionType: UserConstants.RENDER_TEAMS,
      teams: teams,
      defaultTeam: defaultTeam,
    })
  },

  updateTeam: function (team) {
    getTeamMembers(team.id).then(function (data) {
      team.members = data.members
      team.pending_invitations = data.pending_invitations
      AppDispatcher.dispatch({
        actionType: UserConstants.UPDATE_TEAM,
        team: team,
      })
    })
  },

  updateTeams: function (teams) {
    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_TEAMS,
      teams: teams,
    })
  },

  getAllTeams: function () {
    const userInfo = UserStore.getUser()
    AppDispatcher.dispatch({
      actionType: UserConstants.RENDER_TEAMS,
      teams: userInfo.teams,
    })
  },

  selectTeam: function (team) {
    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_TEAM,
      team: team,
    })
    AppDispatcher.dispatch({
      actionType: UserConstants.CHOOSE_TEAM,
      teamId: team.id,
    })
  },

  changeTeamFromUploadPage: function (team) {
    $('.reloading-upload-page').show()
    UserActions.setTeamInStorage(team.id)
    AppDispatcher.dispatch({
      actionType: UserConstants.CHOOSE_TEAM,
      teamId: team.id,
    })
    setTimeout(function () {
      $('.reloading-upload-page').hide()
    }, 1000)
  },

  getLastTeamSelected: function (teams) {
    if (localStorage.getItem(this.teamStorageName)) {
      var lastId = localStorage.getItem(this.teamStorageName)
      var team = teams.find(function (t) {
        return parseInt(t.id) === parseInt(lastId)
      })
      if (team) {
        return team
      } else {
        return teams[0]
      }
    } else {
      return teams[0]
    }
  },

  setTeamInStorage(teamId) {
    localStorage.setItem(this.teamStorageName, teamId)
  },
  forceReload: function () {
    AppDispatcher.dispatch({
      actionType: UserConstants.FORCE_RELOAD,
    })
  },
}

export default UserActions
