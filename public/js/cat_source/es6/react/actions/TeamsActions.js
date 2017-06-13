let AppDispatcher = require('../dispatcher/AppDispatcher');
let TeamConstants = require('../constants/TeamConstants');


let TeamsActions = {

    renderTeams: function (teams, defaultTeam) {
        AppDispatcher.dispatch({
            actionType: TeamConstants.RENDER_TEAMS,
            teams: teams,
            defaultTeam: defaultTeam
        });
    },

};

module.exports = TeamsActions;
