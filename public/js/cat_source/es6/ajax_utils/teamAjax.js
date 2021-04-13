if (!API) {
  var API = {}
}

API.TEAM = {
  getAllTeams: function (force) {
    try {
      if (APP && APP.USER.STORE.teams && !force) {
        var data = {
          teams: APP.USER.STORE.teams,
        }
        var deferred = $.Deferred().resolve(data)
        return deferred.promise()
      } else {
        return APP.USER.loadUserData()
      }
    } catch (e) {}
  },
  getTeamMembers: function (teamId) {
    return $.ajax({
      type: 'get',
      async: true,
      xhrFields: {withCredentials: true},
      url: APP.getRandomUrl() + 'api/v2/teams/' + teamId + '/members',
    })
  },
  createTeam: function (teamName, members) {
    var data = {
      type: 'general',
      name: teamName,
      members: members,
    }
    return $.ajax({
      async: true,
      data: data,
      type: 'POST',
      xhrFields: {withCredentials: true},
      url: APP.getRandomUrl() + 'api/v2/teams',
    })
  },
  addUserToTeam: function (team, userEmail) {
    var email = typeof userEmail === 'string' ? [userEmail] : userEmail
    var data = {
      members: email,
    }
    return $.ajax({
      data: data,
      type: 'post',
      xhrFields: {withCredentials: true},
      url: APP.getRandomUrl() + 'api/v2/teams/' + team.id + '/members',
    })
  },
  removeUserFromTeam: function (team, userId) {
    return $.ajax({
      id_team: team.id,
      type: 'delete',
      xhrFields: {withCredentials: true},
      url:
        APP.getRandomUrl() + 'api/v2/teams/' + team.id + '/members/' + userId,
    })
  },
  changeTeamName: function (team, newName) {
    var data = {
      name: newName,
    }
    return $.ajax({
      data: JSON.stringify(data),
      type: 'PUT',
      xhrFields: {withCredentials: true},
      url: APP.getRandomUrl() + 'api/v2/teams/' + team.id,
    })
  },
}
