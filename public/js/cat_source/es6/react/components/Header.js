
var SubHeader = require("./SubHeader").default;
var TeamConstants = require('../constants/TeamConstants');

class Header extends React.Component {
    constructor (props) {
        super(props);
        this.state = {
            teams: [],
            selectedTeamId : null
        };
        this.renderTeams = this.renderTeams.bind(this);
        this.updateTeams = this.updateTeams.bind(this);
        this.chooseTeams = this.chooseTeams.bind(this);
        this.openModifyTeam = this.openModifyTeam.bind(this);
        this.showPopup = true;
    }

    componentDidMount () {

        TeamsStore.addListener(TeamConstants.RENDER_TEAMS, this.renderTeams);
        TeamsStore.addListener(TeamConstants.UPDATE_TEAMS, this.updateTeams);
        TeamsStore.addListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams);
        TeamsStore.addListener(ManageConstants.OPEN_INFO_TEAMS_POPUP, this.initPopup.bind(this));
    }

    componentWillUnmount() {
        TeamsStore.removeListener(TeamConstants.RENDER_TEAMS, this.renderTeams);
        TeamsStore.removeListener(TeamConstants.UPDATE_TEAMS, this.updateTeams);
        TeamsStore.removeListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams);
        TeamsStore.removeListener(ManageConstants.OPEN_INFO_TEAMS_POPUP, this.initPopup);
    }

    componentDidUpdate() {
        this.initDropdown();
    }

    initDropdown() {
        let self = this;
        if (this.state.teams.size > 0 && !_.isUndefined(this.dropdownTeams)){
            if (this.state.teams.size == 1) {
                this.dropdownTeams.classList.add("only-one-team");
            } else {
                this.dropdownTeams.classList.remove("only-one-team");
            }
            let dropdownTeams = $(this.dropdownTeams);
            if (this.state.selectedTeamId ) {
                setTimeout(function () {
                    dropdownTeams.dropdown('set selected', self.state.selectedTeamId);
                });
            } else {
                dropdownTeams.dropdown();
            }

        }
    }

    initPopup() {
        var self = this;
        if (this.state.teams.size == 1 && this.props.showModals && this.showPopup) {
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
        if (team.get('id')  !== this.state.selectedTeamId) {
            let selectedTeam = this.state.teams.find(function (org) {
                if (org.get("id") === team.get("id")) {
                    return true;
                }
            });
            if (this.props.showSubHeader) {
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
        $(this.dropdownTeams).dropdown('set selected', '' + this.state.selectedTeamId);
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

    openPreferencesModal() {
        $('#modal').trigger('openpreferences');
    }

    openLoginModal() {
        $('#modal').trigger('openlogin');
    }

    getUserIcon() {
        if (this.props.loggedUser ) {
            if (APP.USER.STORE.metadata) {
                return <img onClick={this.openPreferencesModal.bind(this)}
                            className="ui mini circular image ui-user-top-image"
                            src={APP.USER.STORE.metadata.gplus_picture + "?sz=80"} title="Personal settings" alt="Profile picture"/>
            }
            return <div className="ui user label"
                        onClick={this.openPreferencesModal.bind(this)}>{config.userShortName}</div>
        } else {
            return <div className="ui user-nolog label" onClick={this.openLoginModal.bind(this)} title="Login">
	                    <i className="icon-user22"/>
                    </div>

        }
    }

    getTeamsSelect() {
        let result = '';
        var self = this;
        let dropdownIcon = (this.state.teams.size > 1)? <i className="dropdown icon"/> : '';
        let dontShowCursorClass = (this.state.teams.size == 1)? 'disable-dropdown-team' : '';
        let personalTeam='';
        if (this.state.teams.size > 0 && this.props.changeTeam) {
            let items = this.state.teams.map(function(team, i) {
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
        } else if (this.state.teams.size > 0 && self.state.selectedTeamId) {
            let team = this.state.teams.find(function (team) {
                return team.get('id') === self.state.selectedTeamId;
            });
            return <div className="organization-name">{team.get("name")}</div>;
        }
        return result;
    }

    render () {
        let self = this;
        let teamsSelect = (this.props.loggedUser) ? this.getTeamsSelect() : '';
        let userIcon = this.getUserIcon();
        let selectedTeam =  this.state.teams.find(function (org) {
            return org.get('id') == self.state.selectedTeamId;
        });
        let subHeader = '';
        let containerClass = "user-teams thirteen";
        if (this.props.showSubHeader) {
            subHeader = <div className="ten wide column">
                <SubHeader
                selectedTeam={selectedTeam}
            />
            </div>;
            containerClass = "user-teams three";
        }

        return <section className="nav-mc-bar ui grid">

                    <nav className="sixteen wide column navigation">
                        <div className="ui grid">
                            <div className="three wide column">
                                <a href="/" className="logo"/>
                            </div>

                                {subHeader}

                            <div className={containerClass + " wide right floated right aligned column" }>
                                {userIcon}

                                {teamsSelect}

                                { (this.props.showLinks ) ? (
                                        <ul id="menu-site">
                                            <li><a href="https://www.matecat.com/benefits/">Benefits</a></li>
                                            <li><a href="https://www.matecat.com/outsourcing/">Outsource</a></li>
                                            <li><a href="https://www.matecat.com/support-plans/">Plans</a></li>
                                            <li><a href="https://www.matecat.com/about/">About</a></li>
                                            <li><a href="https://www.matecat.com/faq/">FAQ</a></li>
                                            <li><a href="https://www.matecat.com/support/">Support</a></li>
                                            <li><a className="bigred" href="https://www.matecat.com/webinar" target="_blank">Webinar</a></li>
                                        </ul>

                                    ) : ('')}
                            </div>

                        </div>
                    </nav>
                </section>;
    }
}

Header.defaultProps = {
    showSubHeader: true,
    showModals: true,
    showLinks: false,
    loggedUser: true,
    changeTeam: true
};

export default Header ;