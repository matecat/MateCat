import TeamSelect from "./TeamsSelect";
import ProjectInfo from "./HeaderProjectInfo";
import FilterProjects from "./manage/FilterProjects"
import TeamConstants from "./../../constants/TeamConstants";
import TeamsStore from "./../../stores/TeamsStore";
import IconManage from "../icons/IconManage";
import IconUserLogout from "../icons/IconUserLogout";
import ActionMenu from "./ActionMenu";

class Header extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			teams: [],
			selectedTeamId: null
		};
	}

	componentDidMount = () => {
		TeamsStore.addListener(TeamConstants.RENDER_TEAMS, this.renderTeams);
		TeamsStore.addListener(TeamConstants.UPDATE_TEAMS, this.updateTeams);
		TeamsStore.addListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams);
        TeamsStore.addListener(TeamConstants.UPDATE_USER, this.updateUser);
		this.initProfileDropdown();
	}

	componentWillUnmount = () => {
		TeamsStore.removeListener(TeamConstants.RENDER_TEAMS, this.renderTeams);
		TeamsStore.removeListener(TeamConstants.UPDATE_TEAMS, this.updateTeams);
		TeamsStore.removeListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams);
	}

	componentDidUpdate() {
	}

	initProfileDropdown = () => {
		let dropdownProfile = $(this.dropdownProfile);
		dropdownProfile.dropdown();
	}

	logoutUser() {
		$.post('/api/app/user/logout',function(data){
			if ($('body').hasClass('manage')) {
				location.href = config.hostpath + config.basepath;
			} else {
				window.location.reload();
			}
		});
	}

	renderTeams = (teams) => {
		this.setState({
			teams: teams
		});
	}

	updateTeams = (teams) => {
		this.setState({
			teams: teams,
		});
	}

	chooseTeams = (id) => {
		this.selectedTeam = this.state.teams.find(org => {
			return org.get('id') == id;
		});
		this.setState({
			selectedTeamId: id,
		});
	}

	openPreferencesModal = () => {
		$('#modal').trigger('openpreferences');
	}

	openLoginModal = () => {
		$('#modal').trigger('openlogin');
	}

    updateUser = () => {
        this.setState({
            user : user
        });
    }
	getUserIcon = () => {
		if (this.props.loggedUser) {
			if (this.props.user.metadata && this.props.user.metadata.gplus_picture) {
				return 	<div className={"ui dropdown"} ref={(dropdownProfile) => this.dropdownProfile = dropdownProfile} id={"profile-menu"}>
							<img className="ui mini circular image ui-user-top-image"
								 src={this.props.user.metadata.gplus_picture + "?sz=80"} title="Personal settings"
								 alt="Profile picture"/>
							<div className="menu">
								<div className="item" data-value="profile" id="profile-item" onClick={this.openPreferencesModal.bind(this)}>Profile</div>
								<div className="item" data-value="logout" id="logout-item" onClick={this.logoutUser.bind(this)}>Logout</div>
							</div>
						</div>

			}
			return <div className="ui user label"
						onClick={this.openPreferencesModal.bind(this)}>{config.userShortName}</div>
		} else {
			return <div className="ui user-nolog label" onClick={this.openLoginModal.bind(this)} title="Login">
				{/*<i className="icon-user22"/>*/}
				<IconUserLogout width={40} height={40} color={'#fff'} />
			</div>

		}
	}


	getHeaderComponentToShow = () => {

		if (this.props.showFilterProjects) {
			return <div className="nine wide column">
				<FilterProjects
					selectedTeam={this.selectedTeam}
				/>
			</div>;
		} else if (this.props.showJobInfo) {
			return <div className="nine wide column header-project-container-info">
				<ProjectInfo/>
			</div>;
		}
	}


	render = () => {
		const {getHeaderComponentToShow, getUserIcon} = this;
		const {showLinks, showJobInfo, showFilterProjects, showModals, loggedUser, showTeams, changeTeam, isQualityReport} = this.props;
		const {teams,selectedTeamId} = this.state;

		const userIcon = getUserIcon();
		let containerClass = "user-teams four";
		const componentToShow = getHeaderComponentToShow();

		if (showLinks) {
			containerClass = "user-teams thirteen";
		} else if (showJobInfo) {
			containerClass = "user-teams three";
		}

		return <section className="nav-mc-bar ui grid">

			<nav className="sixteen wide column navigation">
				<div className="ui grid">
					<div className="three wide column">
						<a href="/" className="logo"/>
					</div>
					{componentToShow}

					<div className={containerClass + " wide column right floated"}>
						{(showLinks) ? (
							<div>
								<ul id="menu-site">
									<li><a href="https://www.matecat.com/about/">About</a></li>
									<li><a href="https://www.matecat.com/benefits/">Benefits</a></li>
									<li><a href="https://www.matecat.com/outsourcing/">Outsource</a></li>
									<li><a href="https://www.matecat.com/open-source/">Opensource</a></li>
									<li><a href="https://www.matecat.com/support/">Contact us</a></li>
									{/*<li><a className="bigred" href="https://www.matecat.com/webinar" target="_blank">Webinar</a></li>*/}
									<li><a href="/plugins/aligner/index"  target="_blank" className={"btn btn-primary"}>Aligner</a></li>
								</ul>
							</div>

						) : ('')}

						{!!showFilterProjects && <TeamSelect
							isManage={showFilterProjects}
							showModals={showModals}
							loggedUser={loggedUser}
							showTeams={showTeams}
							changeTeam={changeTeam}
							teams={teams}
							selectedTeamId={selectedTeamId}
						/>}
						{!!isQualityReport && <ActionMenu />}
						<div className={"separator"}></div>
						{!!loggedUser && !showFilterProjects && <div title="Manage" id="action-manage">
																	<a className={"action-submenu"} href={'/manage'}>
																		<IconManage width={'36'} height={'36'} style={{float:'right'}} />
																	</a>
																</div>}
						{userIcon}
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
	isQualityReport:false,

};

export default Header;
