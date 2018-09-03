
import TeamConstants from "./../../constants/TeamConstants";
import ManageConstants from "./../../constants/ManageConstants";
import TeamsStore from "./../../stores/TeamsStore";

class TeamsSelect extends React.Component {
    constructor (props) {
        super(props);
        this.state = {
            teams: [],
            selectedTeamId : null
        };
        this.openModifyTeam = this.openModifyTeam.bind(this);
        this.showPopup = true;
    }

    componentDidMount () {
        TeamsStore.addListener(ManageConstants.OPEN_INFO_TEAMS_POPUP, this.initPopup.bind(this));
    }

    componentWillUnmount() {
        TeamsStore.removeListener(ManageConstants.OPEN_INFO_TEAMS_POPUP, this.initPopup);
    }

    componentDidUpdate() {
        this.initDropdown();
    }

    initDropdown() {
        let self = this;
        if (this.props.teams.size > 0 && !_.isUndefined(this.dropdownTeams)){
            if (this.props.teams.size == 1) {
                this.dropdownTeams.classList.add("only-one-team");
            } else {
                this.dropdownTeams.classList.remove("only-one-team");
            }
            let dropdownTeams = $(this.dropdownTeams);
            if (this.props.selectedTeamId ) {
                setTimeout(function () {
                    dropdownTeams.dropdown('set selected', self.props.selectedTeamId);
                });
            } else {
                dropdownTeams.dropdown();
            }

        }
    }

    initPopup() {
        var self = this;
        if (this.props.teams.size == 1 && this.props.showModals && this.showPopup) {
            let tooltipTex = "<h4 class='header'>Add your first team!</h4>" +
                "<div class='content'>" +
                "<p>Create a team and invite your colleagues to share and manage projects.</p>" +
                "<a class='close-popup-teams'>Got it!</a>" +
                "</div>"
            $(this.dropdownTeams).popup({
                on:'click',
                onHidden: self.removePopup.bind(this),
                html : tooltipTex,
                closable:false,
                onCreate: self.onCreatePopup.bind(this),
                className   : {
                    popup: 'ui popup cta-create-team'
                }
            }).popup("show");
            this.showPopup = false;
        }
    }

    removePopup() {
        $(this.dropdownTeams).popup('destroy');
        ManageActions.setPopupTeamsCookie();
        return true;
    }

    onCreatePopup() {
        var self = this;
        $('.close-popup-teams').on('click', function () {
            $(self.dropdownTeams).popup('hide');
        })
    }

    changeTeam(event, team) {
        if (team.get('id')  !== this.props.selectedTeamId) {
            let selectedTeam = this.props.teams.find(function (org) {
                if (org.get("id") === team.get("id")) {
                    return true;
                }
            });
            if (this.props.isManage) {
                window.scrollTo(0, 0);
                ManageActions.changeTeam(selectedTeam.toJS());
            } else {
                TeamsActions.changeTeamFromUploadPage(selectedTeam.toJS());
            }
        }

    }

    openCreateTeams () {
        ModalsActions.openCreateTeamModal();
    }

    openModifyTeam (event, team) {
        event.stopPropagation();
        event.preventDefault();
        $(this.dropdownTeams).dropdown('set selected', '' + this.props.selectedTeamId);
        ManageActions.openModifyTeamModal(team.toJS());
    }

    renderTeams(teams) {
        this.setState({
            teams : teams
        });
    }

    updateTeams(teams) {
        this.setState({
            teams : teams,
        });
    }

    chooseTeams(id) {
        this.setState({
            selectedTeamId : id,
        });
    }

    getTeamsSelect() {
        let result = '';
        var self = this;
        let dropdownIcon = (this.props.teams.size > 1)? <i className="dropdown icon"/> : '';
        let dontShowCursorClass = (this.props.teams.size == 1)? 'disable-dropdown-team' : '';
        let personalTeam='';
        if (this.props.teams.size > 0 && this.props.changeTeam) {
            let items = this.props.teams.map(function(team, i) {
                let iconModal = '';
                if (team.get('type') == 'personal') {
                    personalTeam = <div className="item" data-value={team.get('id')}
                                        data-text={team.get('name')}
                                        key={'team' + team.get('name') + team.get('id')}
                                        onClick={(e) => self.changeTeam(e, team)}>
                        {team.get('name')}
                        {iconModal}
                    </div>;
                    return ;
                }
                if (self.props.showModals && team.get('type') !== 'personal') {
                    iconModal = <a className="team-filter button show right"
                                   onClick={(e) => self.openModifyTeam(e, team)}>
                        <i className="icon-settings icon"/>
                    </a>
                }
                return <div className="item" data-value={team.get('id')}
                            data-text={team.get('name')}
                            key={'team' + team.get('name') + team.get('id')}
                            onClick={(e) => self.changeTeam(e, team)}>
                    {team.get('name')}
                    {iconModal}
                </div>
            });
            let addTeam = '';
            if (self.props.showModals) {
                dontShowCursorClass = '';
                addTeam = <div className="header" onClick={this.openCreateTeams.bind(this)}>Create New Team
                    <a className="team-filter button show">
                        <i className="icon-plus3 icon"/>
                    </a>
                </div>
            }
            result = <div className={"ui top right pointing dropdown select-org " + dontShowCursorClass}
                          ref={(dropdownTeams) => this.dropdownTeams = dropdownTeams}>
                <input type="hidden" name="team" className="team-dd" />
                {dropdownIcon}
                <span className="text">Choose Team</span>
                {/*<i className="dropdown icon"/>*/}
                <div className="menu">
                    {addTeam}
                    { self.props.showModals ? (
                        <div className="divider"></div>
                    ): (
                        ''
                    )}
                    <div className="scrolling menu">
                        {personalTeam}
                        {items}
                    </div>
                </div>
            </div>;
        } else if (this.props.teams.size > 0 && self.props.selectedTeamId) {
            let team = this.props.teams.find(function (team) {
                return team.get('id') === self.props.selectedTeamId;
            });
            return <div className="organization-name">{team.get("name")}</div>;
        }
        return result;
    }

    render () {
        let self = this;
        let teamsSelect = (this.props.loggedUser) ? this.getTeamsSelect() : '';
        return <div>{teamsSelect}</div>;
    }
}

TeamsSelect.defaultProps = {
    isManage: true,
    showModals: true,
    loggedUser: true,
    showTeams: true,
    changeTeam: true,
};

export default TeamsSelect ;