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

        var analyzeMountPoint = $("#analyze-container")[0];
        ReactDOM.render(React.createElement(AnalyzeMain), analyzeMountPoint);

        API.TEAM.getAllTeams().done(function (data) {
            self.teams = data.teams;
            TeamsActions.renderTeams(self.teams);
            self.selectedTeam = APP.getLastTeamSelected(self.teams);
            ManageActions.selectTeam(self.selectedTeam);
        });

    },
};
$(document).ready(function() {
    UI.init();
});

