import AppDispatcher from '../stores/AppDispatcher'
import UserConstants from '../constants/UserConstants'
import {getUserData} from '../api/getUserData'
import {getTeamMembers} from '../api/getTeamMembers'

let UserActions = {
  updateUser: function (user) {
    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_USER,
      user: user,
    })
  },
  updateUserName: function (info) {
    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_USER_NAME,
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
    getUserData().then(function (data) {
      AppDispatcher.dispatch({
        actionType: UserConstants.RENDER_TEAMS,
        teams: data.teams,
      })
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
    APP.setTeamInStorage(team.id)
    AppDispatcher.dispatch({
      actionType: UserConstants.CHOOSE_TEAM,
      teamId: team.id,
    })
    setTimeout(function () {
      $('.reloading-upload-page').hide()
    }, 1000)
  },
}

export default UserActions
