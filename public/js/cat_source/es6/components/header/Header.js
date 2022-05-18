import React from 'react'

import TeamSelect from './TeamsSelect'
import FilterProjects from './manage/FilterProjects'
import TeamConstants from '../../constants/TeamConstants'
import TeamsStore from '../../stores/TeamsStore'
import QRStore from '../../stores/QualityReportStore'
import QRConstants from '../../constants/QualityReportConstants'
import {ActionMenu} from './ActionMenu'
import {UserMenu} from './UserMenu'

class Header extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      teams: [],
      selectedTeamId: null,
      user: this.props.user,
      loggedUser: this.props.loggedUser,
      jobUrls: undefined,
    }
    this.renderTeams = this.renderTeams.bind(this)
    this.updateTeams = this.updateTeams.bind(this)
    this.chooseTeams = this.chooseTeams.bind(this)
    this.updateUser = this.updateUser.bind(this)
    this.showPopup = true
  }

  componentDidMount = () => {
    TeamsStore.addListener(TeamConstants.RENDER_TEAMS, this.renderTeams)
    TeamsStore.addListener(TeamConstants.UPDATE_TEAM, this.updateTeam)
    TeamsStore.addListener(TeamConstants.UPDATE_TEAMS, this.updateTeams)
    TeamsStore.addListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams)
    TeamsStore.addListener(TeamConstants.UPDATE_USER, this.updateUser)
    // CatToolStore.addListener(
    //   CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
    //   this.initMyProjectsPopup,
    // )
    if (this.props.isQualityReport) {
      QRStore.addListener(QRConstants.RENDER_REPORT, this.storeJobUrls)
    }
    // this.initProfileDropdown()
  }

  componentWillUnmount = () => {
    TeamsStore.removeListener(TeamConstants.RENDER_TEAMS, this.renderTeams)
    TeamsStore.removeListener(TeamConstants.UPDATE_TEAM, this.updateTeam)
    TeamsStore.removeListener(TeamConstants.UPDATE_TEAMS, this.updateTeams)
    TeamsStore.removeListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams)
    TeamsStore.removeListener(TeamConstants.UPDATE_USER, this.updateUser)
    // CatToolStore.removeListener(
    //   CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
    //   this.initMyProjectsPopup,
    // )
    if (this.props.isQualityReport) {
      QRStore.removeListener(QRConstants.RENDER_REPORT, this.storeJobUrls)
    }
  }

  renderTeams = (teams) => {
    this.setState({
      teams: teams,
    })
  }

  updateTeam = (team) => {
    if (!this.state.teams || !this.selectedTeam) return
    if (this.selectedTeam.get('id') === team.get('id')) this.selectedTeam = team
    this.setState({
      teams: this.state.teams.map((teamState) =>
        team.get('id') === teamState.get('id') ? team : teamState,
      ),
    })
  }

  updateTeams = (teams) => {
    this.setState({
      teams: teams,
    })
  }

  chooseTeams = (id) => {
    this.selectedTeam = this.state.teams.find((org) => {
      return org.get('id') == id
    })
    this.setState({
      selectedTeamId: id,
    })
  }

  updateUser = (user) => {
    this.setState({
      user: user,
      loggedUser: true,
    })
    // setTimeout(this.initProfileDropdown)
  }

  // initMyProjectsPopup = () => {
  //   if (this.showPopup) {
  //     var tooltipTex =
  //       "<h4 class='header'>Manage your projects</h4>" +
  //       "<div class='content'>" +
  //       '<p>Click here, then "My projects" to retrieve and manage all the projects you have created in MateCat.</p>' +
  //       "<a class='close-popup-teams'>Got it!</a>" +
  //       '</div>'
  //     $(this.dropdownProfile)
  //       .popup({
  //         on: 'click',
  //         onHidden: () => this.removePopup(),
  //         html: tooltipTex,
  //         closable: false,
  //         onCreate: () => this.onCreatePopup(),
  //         className: {
  //           popup: 'ui popup user-menu-tooltip',
  //         },
  //       })
  //       .popup('show')
  //     this.showPopup = false
  //   }
  // }

  // removePopup = () => {
  //   $(this.dropdownProfile).popup('destroy')
  //   CatToolActions.setPopupUserMenuCookie()
  //   return true
  // }
  //
  // onCreatePopup = () => {
  //   $('.close-popup-teams').on('click', () => {
  //     $(this.dropdownProfile).popup('hide')
  //   })
  // }

  getHeaderComponentToShow = () => {
    if (this.props.showFilterProjects) {
      return (
        <div className="nine wide column">
          <FilterProjects selectedTeam={this.selectedTeam} />
        </div>
      )
    }
  }

  /**
   * Used by plugins to add buttons to the home page
   */
  getMoreLinks() {
    return null
  }

  storeJobUrls = (jobInfo) => {
    this.setState({
      jobUrls: jobInfo.get('urls'),
    })
  }

  render = () => {
    const {getHeaderComponentToShow} = this
    const {
      showLinks,
      showFilterProjects,
      showModals,
      showTeams,
      changeTeam,
      isQualityReport,
    } = this.props
    const {teams, selectedTeamId, loggedUser, jobUrls} = this.state

    let containerClass = 'user-teams four'
    const componentToShow = getHeaderComponentToShow()

    if (showLinks) {
      containerClass = 'user-teams thirteen'
    }

    return (
      <section className="nav-mc-bar ui grid">
        <nav className="sixteen wide column navigation">
          <div className="ui grid">
            <div className="three wide column" data-testid="logo">
              <a href="/" className="logo" />
            </div>
            {componentToShow}

            <div className={containerClass + ' wide column right floated'}>
              {showLinks ? (
                <div>
                  <ul id="menu-site">
                    <li>
                      <a href="https://site.matecat.com/about/">About</a>
                    </li>
                    <li>
                      <a href="https://site.matecat.com/benefits/">Benefits</a>
                    </li>
                    <li>
                      <a href="https://site.matecat.com/outsourcing/">
                        Outsource
                      </a>
                    </li>
                    <li>
                      <a href="https://site.matecat.com/open-source/">
                        Opensource
                      </a>
                    </li>
                    <li>
                      <a href="https://site.matecat.com/contact-us/">
                        Contact us
                      </a>
                    </li>
                    {/*<li><a href="/plugins/aligner/index"  target="_blank" className={"btn btn-primary"}>Aligner</a></li>*/}
                    {this.getMoreLinks()}
                  </ul>
                </div>
              ) : (
                ''
              )}

              {!!showFilterProjects && (
                <TeamSelect
                  isManage={showFilterProjects}
                  showModals={showModals}
                  loggedUser={loggedUser}
                  showTeams={showTeams}
                  changeTeam={changeTeam}
                  teams={teams}
                  selectedTeamId={selectedTeamId}
                />
              )}
              {!!isQualityReport && jobUrls && (
                <ActionMenu jobUrls={this.state.jobUrls.toJS()} />
              )}
              <UserMenu user={this.state.user} userLogged={loggedUser} />
            </div>
          </div>
        </nav>
      </section>
    )
  }
}

Header.defaultProps = {
  showFilterProjects: false,
  showModals: true,
  showLinks: false,
  loggedUser: true,
  showTeams: true,
  changeTeam: true,
  isQualityReport: false,
}

export default Header
