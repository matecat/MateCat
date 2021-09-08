import React from 'react'

import TeamSelect from './TeamsSelect'
import ProjectInfo from './HeaderProjectInfo'
import FilterProjects from './manage/FilterProjects'
import TeamConstants from '../../constants/TeamConstants'
import CatToolConstants from '../../constants/CatToolConstants'
import TeamsStore from '../../stores/TeamsStore'
import CatToolStore from '../../stores/CatToolStore'
import IconUserLogout from '../icons/IconUserLogout'
import ActionMenu from './ActionMenu'
import QRStore from '../../stores/QualityReportStore'
import QRConstants from '../../constants/QualityReportConstants'
import CatToolActions from '../../actions/CatToolActions'
import {logoutUser as logoutUserApi} from '../../api/logoutUser'

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
    this.initProfileDropdown = this.initProfileDropdown.bind(this)
    this.showPopup = true
  }

  componentDidMount = () => {
    TeamsStore.addListener(TeamConstants.RENDER_TEAMS, this.renderTeams)
    TeamsStore.addListener(TeamConstants.UPDATE_TEAMS, this.updateTeams)
    TeamsStore.addListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams)
    TeamsStore.addListener(TeamConstants.UPDATE_USER, this.updateUser)
    CatToolStore.addListener(
      CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
      this.initMyProjectsPopup,
    )
    if (this.props.isQualityReport) {
      QRStore.addListener(QRConstants.RENDER_REPORT, this.storeJobUrls)
    }
    this.initProfileDropdown()
  }

  componentWillUnmount = () => {
    TeamsStore.removeListener(TeamConstants.RENDER_TEAMS, this.renderTeams)
    TeamsStore.removeListener(TeamConstants.UPDATE_TEAMS, this.updateTeams)
    TeamsStore.removeListener(TeamConstants.CHOOSE_TEAM, this.chooseTeams)
    TeamsStore.removeListener(TeamConstants.UPDATE_USER, this.updateUser)
    CatToolStore.removeListener(
      CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
      this.initMyProjectsPopup,
    )
    if (this.props.isQualityReport) {
      QRStore.removeListener(QRConstants.RENDER_REPORT, this.storeJobUrls)
    }
  }

  componentDidUpdate() {}

  initProfileDropdown = () => {
    let dropdownProfile = $(this.dropdownProfile)
    dropdownProfile.dropdown()
  }

  logoutUser = () => {
    logoutUserApi().then(() => {
      if ($('body').hasClass('manage')) {
        location.href = config.hostpath + config.basepath
      } else {
        window.location.reload()
      }
    })
  }

  renderTeams = (teams) => {
    this.setState({
      teams: teams,
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

  openPreferencesModal = () => {
    $('#modal').trigger('openpreferences')
  }

  openLoginModal = () => {
    $('#modal').trigger('openlogin')
  }

  updateUser = (user) => {
    this.setState({
      user: user,
      loggedUser: true,
    })
    setTimeout(this.initProfileDropdown)
  }

  openManage = () => {
    document.location.href = '/manage'
  }

  getUserIcon = () => {
    if (this.state.loggedUser) {
      // dom elements attributes declarations
      const dropdownAttributes = {
        className: 'ui dropdown',
        ref: (dropdownProfile) => (this.dropdownProfile = dropdownProfile),
        id: 'profile-menu',
        'data-testid': 'user-menu',
      }
      const profileItemAttributes = {
        className: 'item',
        'data-value': 'profile',
        id: 'profile-item',
        onClick: this.openPreferencesModal,
        'data-testid': 'profile-item',
      }
      const logoutItemAttributes = {
        className: 'item',
        'data-value': 'logout',
        id: 'logout-item',
        onClick: this.logoutUser,
        'data-testid': 'logout-item',
      }
      const myProjectsItemAttributes = {
        className: 'item',
        'data-value': 'Manage',
        id: 'manage-item',
        onClick: this.openManage,
      }

      if (this.state.user?.metadata && this.state.user.metadata.gplus_picture) {
        return (
          <div
            {...{...dropdownAttributes, 'data-testid': 'user-menu-metadata'}}
          >
            <img
              className="ui mini circular image ui-user-top-image"
              src={this.state.user.metadata.gplus_picture + '?sz=80'}
              title="Personal settings"
              alt="Profile picture"
            />
            <div className="menu">
              {!this.props.showFilterProjects && (
                <div {...myProjectsItemAttributes}>My Projects</div>
              )}
              <div {...profileItemAttributes}>Profile</div>
              <div {...logoutItemAttributes}>Logout</div>
            </div>
          </div>
        )
      }

      return (
        <div {...dropdownAttributes}>
          <div
            className="ui user circular image ui-user-top-image"
            title="Personal settings"
          >
            {config.userShortName}
          </div>
          <div className="menu">
            <div {...myProjectsItemAttributes}>My Projects</div>
            <div {...profileItemAttributes}>Profile</div>
            <div {...logoutItemAttributes}>Logout</div>
          </div>
        </div>
      )
      // <div className="ui user label"
      // 		onClick={this.openPreferencesModal.bind(this)}>{config.userShortName}</div>
    } else {
      return (
        <div
          className="ui user-nolog label"
          onClick={this.openLoginModal.bind(this)}
          title="Login"
        >
          {/*<i className="icon-user22"/>*/}
          <IconUserLogout width={40} height={40} color={'#fff'} />
        </div>
      )
    }
  }

  initMyProjectsPopup = () => {
    if (this.showPopup) {
      var tooltipTex =
        "<h4 class='header'>Manage your projects</h4>" +
        "<div class='content'>" +
        '<p>Click here, then "My projects" to retrieve and manage all the projects you have created in MateCat.</p>' +
        "<a class='close-popup-teams'>Got it!</a>" +
        '</div>'
      $(this.dropdownProfile)
        .popup({
          on: 'click',
          onHidden: () => this.removePopup(),
          html: tooltipTex,
          closable: false,
          onCreate: () => this.onCreatePopup(),
          className: {
            popup: 'ui popup user-menu-tooltip',
          },
        })
        .popup('show')
      this.showPopup = false
    }
  }

  removePopup = () => {
    $(this.dropdownProfile).popup('destroy')
    CatToolActions.setPopupUserMenuCookie()
    return true
  }

  onCreatePopup = () => {
    $('.close-popup-teams').on('click', () => {
      $(this.dropdownProfile).popup('hide')
    })
  }

  getHeaderComponentToShow = () => {
    if (this.props.showFilterProjects) {
      return (
        <div className="nine wide column">
          <FilterProjects selectedTeam={this.selectedTeam} />
        </div>
      )
    } else if (this.props.showJobInfo) {
      return (
        <div className="nine wide column header-project-container-info">
          <ProjectInfo />
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
    const {getHeaderComponentToShow, getUserIcon} = this
    const {
      showLinks,
      showJobInfo,
      showFilterProjects,
      showModals,
      showTeams,
      changeTeam,
      isQualityReport,
    } = this.props
    const {teams, selectedTeamId, loggedUser, jobUrls} = this.state

    const userIcon = getUserIcon()
    let containerClass = 'user-teams four'
    const componentToShow = getHeaderComponentToShow()

    if (showLinks) {
      containerClass = 'user-teams thirteen'
    } else if (showJobInfo) {
      containerClass = 'user-teams three'
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
              {userIcon}
            </div>
          </div>
        </nav>
      </section>
    )
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
  isQualityReport: false,
}

export default Header
