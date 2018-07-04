if (!API) {
    var API = {}
}


API.TEAM = {
    getAllTeams: function (force) {
        if ( APP.USER.STORE.teams && !force) {
            var data = {
                teams: APP.USER.STORE.teams
            };
            var deferred = $.Deferred().resolve(data);
            return deferred.promise();
        } else {
            return APP.USER.loadUserData();
        }

    },
    getTeamMembers: function (teamId) {
        return $.ajax({
            async: true,
            type: "get",
            url : "/api/v2/teams/" + teamId + "/members"
        });
    },
    createTeam: function (teamName, members) {
        var data = {
            type: 'general',
            name: teamName,
            members: members
        };
        return $.ajax({
            async: true,
            data: data,
            type: "POST",
            url : "/api/v2/teams"
        });

    },
    addUserToTeam: function (team, userEmail) {
        var email = (typeof userEmail === "string") ? [userEmail] : userEmail;
        var data = {
            members: email
        };
        return $.ajax({
            data: data,
            type: "post",
            url : "/api/v2/teams/"+ team.id +"/members",
        });
    },
    removeUserFromTeam: function (team, userId) {
        return $.ajax({id_team: team.id,
            type: "delete",
            url : "/api/v2/teams/"+ team.id +"/members/" + userId,
        });
    },
    changeTeamName: function (team, newName) {
        var data = {
            name: newName
        };
        return $.ajax({
            data: JSON.stringify(data),
            type: "PUT",
            url : "/api/v2/teams/" + team.id,
        });
    },

};