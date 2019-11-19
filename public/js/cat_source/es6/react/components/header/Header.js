
import TeamSelect from "./TeamsSelect";
import ProjectInfo from "./HeaderProjectInfo";
import FilterProjects from "./manage/FilterProjects"
import TeamConstants from "./../../constants/TeamConstants";
import TeamsStore from "./../../stores/TeamsStore";

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
    }

    componentDidMount () {
        TeamsStore.addListener(TeamConstants.RENDER_TEAMS, this.renderTeams);
        TeamsStore.addListener(TeamConstants.UPDATE_TEAMS, this.updateTeams);
        TeamsStore.addListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams);
    }

    componentWillUnmount() {
        TeamsStore.removeListener(TeamConstants.RENDER_TEAMS, this.renderTeams);
        TeamsStore.removeListener(TeamConstants.UPDATE_TEAMS, this.updateTeams);
        TeamsStore.removeListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams);
    }

    componentDidUpdate() {}

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
        let self = this;
        this.selectedTeam =  this.state.teams.find(function (org) {
            return org.get('id') == id;
        });
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
            if (this.props.user.metadata && this.props.user.metadata.gplus_picture) {
                return <img onClick={this.openPreferencesModal.bind(this)}
                            className="ui mini circular image ui-user-top-image"
                            src={this.props.user.metadata.gplus_picture + "?sz=80"} title="Personal settings" alt="Profile picture"/>
            }
            return <div className="ui user label"
                        onClick={this.openPreferencesModal.bind(this)}>{config.userShortName}</div>
        } else {
            return <div className="ui user-nolog label" onClick={this.openLoginModal.bind(this)} title="Login">
	                    <i className="icon-user22"/>
                    </div>

        }
    }

    getHeaderComponentToShow() {
        if (this.props.showFilterProjects) {
            return <div className="ten wide column">
                <FilterProjects
                    selectedTeam={this.selectedTeam}
                />
            </div>;
        } else if (this.props.showJobInfo) {
            return <div className="header-project-container-info">
                <ProjectInfo/>
            </div>;
        }
    }

    /**
     * Used by plugins to add buttons to the home page
     */
    getMoreLinks() {
        return null;
    }

    render () {
        let self = this;
        let userIcon = this.getUserIcon();

        let containerClass = "user-teams three";

        let componentToShow = this.getHeaderComponentToShow();
        if (this.props.showLinks) {
            containerClass = "user-teams thirteen";
        } else if (this.props.showJobInfo) {
            containerClass = "user-teams one";
        }

        return <section className="nav-mc-bar ui grid">

                    <nav className="sixteen wide column navigation">
                        <div className="ui grid">
                            <div className="three wide column">
                                <a href="/" className="logo"/>
                            </div>

                            {componentToShow}

                            <div className={containerClass + " wide right floated right aligned column" }>
                                {userIcon}

                                <TeamSelect
                                    isManage={this.props.showFilterProjects}
                                    showModals={this.props.showModals}
                                    loggedUser={this.props.loggedUser}
                                    showTeams={this.props.showTeams}
                                    changeTeam={this.props.changeTeam}
                                    teams={this.state.teams}
                                    selectedTeamId={this.state.selectedTeamId}
                                />

                                { (this.props.showLinks ) ? (
                                        <ul id="menu-site">
                                            <li><a href="https://www.matecat.com/benefits/">Benefits</a></li>
                                            <li><a href="https://www.matecat.com/outsourcing/">Outsource</a></li>
                                            <li><a href="https://www.matecat.com/support-plans/">Plans</a></li>
                                            <li><a href="https://www.matecat.com/about/">About</a></li>
                                            <li><a href="https://www.matecat.com/faq/">FAQ</a></li>
                                            <li><a href="https://www.matecat.com/support/">Support</a></li>
                                            { this.getMoreLinks() }
                                        </ul>

                                    ) : ('')}
                            </div>

                        </div>
                    </nav>
                </section>;
    }
}

Header.defaultProps = {
    showFilterProjects: false,
    showJobInfo: false,
    showModals: true,
    showLinks: false,
    loggedUser: true,
    showTeams: true,
    changeTeam: true,
};

export default Header ;
