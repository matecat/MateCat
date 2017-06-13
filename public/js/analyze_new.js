UI = null;

UI = {
    init: function () {
        UI.render();
    },
    render: function () {
        var self = this;
        var headerMountPoint = $("header")[0];
        ReactDOM.render(React.createElement(Header,{
                showSubHeader: false,
                showModals: false
        }), headerMountPoint);

        this.getAllTeams().done(function (data) {
            self.teams = data.teams;
            TeamsActions.renderTeams(self.teams);
            self.selectedTeam = APP.getLastTeamSelected(self.teams);
            ManageActions.selectTeam(self.selectedTeam);
        });

    },

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
};
$(document).ready(function() {
    UI.init();
});

