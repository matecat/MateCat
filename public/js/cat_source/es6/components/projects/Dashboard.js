import React from 'react'
import {createRoot} from 'react-dom/client'
import Immutable from 'immutable'
import _ from 'lodash'
import ReactDOM, {flushSync} from 'react-dom'

import ProjectsContainer from './ProjectsContainer'
import ManageActions from '../../actions/ManageActions'
import TeamsActions from '../../actions/TeamsActions'
import ModalsActions from '../../actions/ModalsActions'
import CatToolActions from '../../actions/CatToolActions'
import ProjectsStore from '../../stores/ProjectsStore'
import TeamsStore from '../../stores/TeamsStore'
import ManageConstants from '../../constants/ManageConstants'
import TeamConstants from '../../constants/TeamConstants'
import DashboardHeader from './Header'
import Header from '../header/Header'
import {getProjects} from '../../api/getProjects'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import {getUserData} from '../../api/getUserData'
import {getTeamMembers} from '../../api/getTeamMembers'
import NotificationBox from '../notificationsComponent/NotificationBox'
import {CookieConsent} from '../common/CookieConsent'

class Dashboard extends React.Component {
  constructor() {
    super()
    //Search ??
    this.Search = {}
    this.Search.filter = {}
    this.Search.currentPage = 1
    //Update manage
    this.pageLeft = false
    this.state = {
      teams: [],
      selectedTeam: undefined,
      fetchingProjects: true,
      selectedUser: ManageConstants.ALL_MEMBERS_FILTER,
    }
  }

  getData = () => {
    getUserData().then((data) => {
      TeamsActions.renderTeams(data.teams)
      const selectedTeam = APP.getLastTeamSelected(data.teams)
      const teams = data.teams
      this.setState({
        teams: teams,
        selectedTeam: selectedTeam,
      })
      this.getTeamStructure(selectedTeam).then(() => {
        TeamsActions.selectTeam(selectedTeam)
        ManageActions.checkPopupInfoTeams()
        this.setState({fetchingProjects: true})
        getProjects({team: selectedTeam, searchFilter: this.Search})
          .then((res) => {
            this.setState({fetchingProjects: false})
            setTimeout(() =>
              ManageActions.renderProjects(res.data, selectedTeam, teams),
            )
            ManageActions.storeSelectedTeam(selectedTeam)
          })
          .catch((err) => {
            if (err[0].code == 401) {
              // Not Logged or not in the team
              window.location.reload()
              return
            }

            if (err[0].code == 404) {
              this.selectPersonalTeam()
              return
            }

            window.location = '/'
          })
      })
      setTimeout(function () {
        CatToolActions.showHeaderTooltip()
      }, 2000)
    })
  }

  updateTeams = (teams) => {
    flushSync(() =>
      this.setState({
        teams: teams.toJS(),
      }),
    )
  }

  updateProjects = (id) => {
    if (id === this.state.selectedTeam.id) return
    const {teams} = this.state
    const selectedTeam = teams.find((t) => t.id === id)
    if (selectedTeam) {
      this.setState({
        selectedTeam: selectedTeam,
        selectedUser: ManageConstants.ALL_MEMBERS_FILTER,
      })
      this.Search.filter = {}
      this.Search.currentPage = 1

      getProjects({team: selectedTeam, searchFilter: this.Search})
        .then((res) => {
          ManageActions.renderProjects(res.data, selectedTeam, teams)
          ManageActions.storeSelectedTeam(selectedTeam)
        })
        .catch((err) => {
          if (err[0].code == 401) {
            // Not Logged or not in the team
            window.location.reload()
            return
          }

          if (err[0].code == 404) {
            // Not Logged or not in the team
            this.selectPersonalTeam()
            return
          }

          window.location = '/'
        })
    }
  }

  getTeamStructure = (team) => {
    return getTeamMembers(team.id).then((data) => {
      team.members = data.members
      team.pending_invitations = data.pending_invitations
      this.setState({team})
      ManageActions.storeSelectedTeam(team)
    })
  }

  scrollDebounceFn = () => _.debounce(() => this.handleScroll(), 300)

  handleScroll = () => {
    if (
      $(window).scrollTop() + $(window).height() >
      $(document).height() - 200
    ) {
      console.log('Scroll end')
      this.renderMoreProjects()
    }
  }

  renderMoreProjects = () => {
    this.Search.currentPage = this.Search.currentPage + 1

    getProjects({
      team: this.state.selectedTeam,
      searchFilter: this.Search,
    }).then((res) => {
      const projects = res.data

      if (projects.length > 0) {
        ManageActions.renderMoreProjects(projects)
      } else {
        ManageActions.noMoreProjects()
      }
    })
  }

  refreshProjects = () => {
    if (this.Search.currentPage === 1) {
      const {selectedTeam} = this.state
      getProjects({
        team: this.state.selectedTeam,
        searchFilter: this.Search,
      })
        .then((res) => {
          if (selectedTeam.id === this.state.selectedTeam.id) {
            const projects = res.data
            ManageActions.updateProjects(projects)
          }
        })
        .catch((err) => {
          if (err[0].code == 401) {
            //Not Logged or not in the team
            window.location.reload()
            return
          }

          if (err[0].code == 404) {
            this.selectPersonalTeam()
            return
          }

          window.location = '/'
        })
    } else {
      let total_projects = []
      let requests = []
      let onDone = (response) => {
        const projects = response.data
        $.merge(total_projects, projects)
      }
      for (let i = 1; i <= this.Search.currentPage; i++) {
        requests.push(
          getProjects({
            team: this.state.selectedTeam,
            searchFilter: this.Search,
            page: i,
          }),
        )
      }
      Promise.all(requests).then((responses) => {
        responses.forEach(onDone)
        ManageActions.updateProjects(total_projects)
      })
    }
    getUserData().then((data) => {
      this.setState({teams: data.teams})
      TeamsActions.updateTeams(data.teams)
    })
  }

  selectPersonalTeam = () => {
    const personalTeam = this.state.teams.find(function (team) {
      return team.type == 'personal'
    })
    ManageActions.changeTeam(personalTeam)
  }

  /**
   * Open the settings for the job
   */
  openJobSettings = (job, prName) => {
    const url =
      '/translate/' +
      prName +
      '/' +
      job.source +
      '-' +
      job.target +
      '/' +
      job.id +
      '-' +
      job.password +
      '&openTab=options'
    window.open(url, '_blank')
  }

  /**
   * Open the tm panel for the job
   */
  openJobTMPanel = (job, prName) => {
    const url =
      '/translate/' +
      prName +
      '/' +
      job.source +
      '-' +
      job.target +
      '/' +
      job.id +
      '-' +
      job.password +
      '&openTab=tm'
    window.open(url, '_blank')
  }

  downloadTranslation = (project, job, urlWarnings) => {
    let continueDownloadFunction
    let callback = ManageActions.enableDownloadButton.bind(null, job.id)

    if (project.remote_file_service == 'gdrive') {
      continueDownloadFunction = function () {
        ModalsActions.onCloseModal()
        ManageActions.disableDownloadButton(job.id)
        APP.downloadGDriveFile(null, job.id, job.password, callback)
      }
    } else {
      continueDownloadFunction = function () {
        ModalsActions.onCloseModal()
        ManageActions.disableDownloadButton(job.id)
        APP.downloadFile(job.id, job.password, callback)
      }
    }

    const openUrl = function () {
      ModalsActions.onCloseModal()
      ManageActions.enableDownloadButton(job.id)
      window.open(urlWarnings, '_blank')
    }

    //the translation mismatches are not a server Error, but only a warn, so don't display Error Popup
    if (job.warnings_count > 0) {
      ModalsActions.showDownloadWarningsModal(continueDownloadFunction, openUrl)
    } else {
      continueDownloadFunction()
    }
  }
  filterProjects = (userUid, name, status) => {
    this.Search.filter = {}
    this.Search.currentPage = 1
    var filter = {}
    if (typeof userUid != 'undefined') {
      if (userUid === ManageConstants.NOT_ASSIGNED_FILTER) {
        filter.no_assignee = true
      } else if (userUid !== ManageConstants.ALL_MEMBERS_FILTER) {
        filter.id_assignee = userUid
      }
      this.setState({
        selectedUser: userUid,
      })
    }
    if (typeof name !== 'undefined') {
      filter.pn = name
    }
    if (typeof status !== 'undefined') {
      filter.status = status
    }
    this.Search.filter = $.extend(this.Search.filter, filter)
    if (!_.isEmpty(this.Search.filter)) {
      this.Search.currentPage = 1
    }

    getProjects({
      team: this.state.selectedTeam,
      searchFilter: this.Search,
    }).then((res) => {
      const projects = res.data

      ManageActions.renderProjects(
        projects,
        this.state.selectedTeam,
        this.state.teams,
        false,
        true,
      )
    })
  }

  //********* Modals **************//

  openCreateTeamModal = () => {
    ModalsActions.openCreateTeamModal()
  }

  openModifyTeamModal = (team, hideChangeName) => {
    ModalsActions.openModifyTeamModal(team, hideChangeName)
  }

  openChangeTeamModal = (teams, project) => {
    ModalsActions.openChangeTeamModal(
      teams,
      project,
      this.state.selectedTeam.id,
    )
  }

  removeUserFilter = (uid) => {
    if (this.Search.filter.id_assignee == uid) {
      delete this.Search.filter.id_assignee
    }
  }
  /*********************************/

  componentDidMount() {
    this.getData()
    window.addEventListener('scroll', this.scrollDebounceFn())
    let self = this
    $(window).on('blur focus', function (e) {
      const prevType = $(this).data('prevType')

      if (prevType != e.type) {
        //  reduce double fire issues
        switch (e.type) {
          case 'blur':
            console.log('leave page')
            self.pageLeft = true
            break
          case 'focus':
            console.log('Enter page')
            if (self.pageLeft) {
              self.refreshProjects()
            }
            break
        }
      }

      $(this).data('prevType', e.type)
    })

    //Job Actions
    ProjectsStore.addListener(
      ManageConstants.OPEN_JOB_SETTINGS,
      this.openJobSettings,
    )
    ProjectsStore.addListener(
      ManageConstants.OPEN_JOB_TM_PANEL,
      this.openJobTMPanel,
    )
    ProjectsStore.addListener(
      ManageConstants.RELOAD_PROJECTS,
      this.refreshProjects,
    )
    ProjectsStore.addListener(
      ManageConstants.FILTER_PROJECTS,
      this.filterProjects,
    )

    //Modals
    TeamsStore.addListener(
      ManageConstants.UPDATE_TEAM_MEMBERS,
      this.removeUserFilter,
    )
    TeamsStore.addListener(
      ManageConstants.OPEN_CREATE_TEAM_MODAL,
      this.openCreateTeamModal,
    )
    TeamsStore.addListener(
      ManageConstants.OPEN_MODIFY_TEAM_MODAL,
      this.openModifyTeamModal,
    )
    TeamsStore.addListener(TeamConstants.RENDER_TEAMS, this.updateTeams)
    TeamsStore.addListener(TeamConstants.CHOOSE_TEAM, this.updateProjects)
  }

  componentWillUnmount() {
    window.removeEventListener('scroll', this.scrollDebounceFn())

    //Job Actions
    ProjectsStore.removeListener(
      ManageConstants.OPEN_JOB_SETTINGS,
      this.openJobSettings,
    )
    ProjectsStore.removeListener(
      ManageConstants.OPEN_JOB_TM_PANEL,
      this.openJobTMPanel,
    )
    ProjectsStore.removeListener(
      ManageConstants.RELOAD_PROJECTS,
      this.refreshProjects,
    )
    ProjectsStore.removeListener(
      ManageConstants.FILTER_PROJECTS,
      this.filterProjects,
    )

    //Modals
    TeamsStore.removeListener(
      ManageConstants.UPDATE_TEAM_MEMBERS,
      this.removeUserFilter,
    )
    TeamsStore.removeListener(
      ManageConstants.OPEN_CREATE_TEAM_MODAL,
      this.openCreateTeamModal,
    )
    TeamsStore.removeListener(
      ManageConstants.OPEN_MODIFY_TEAM_MODAL,
      this.openModifyTeamModal,
    )
    TeamsStore.removeListener(TeamConstants.RENDER_TEAMS, this.updateTeams)
    TeamsStore.removeListener(TeamConstants.CHOOSE_TEAM, this.updateProjects)
  }

  render() {
    const cookieBannerMountPoint = document.getElementsByTagName('footer')[0]
    return (
      <React.Fragment>
        <DashboardHeader>
          <Header
            user={APP.USER.STORE}
            showFilterProjects={true}
            loggedUser={true}
          />
        </DashboardHeader>
        {this.state.selectedTeam && this.state.teams ? (
          <ProjectsContainer
            downloadTranslationFn={this.downloadTranslation}
            teams={Immutable.fromJS(this.state.teams)}
            team={Immutable.fromJS(this.state.selectedTeam)}
            selectedUser={this.state.selectedUser}
            fetchingProjects={this.state.fetchingProjects}
          />
        ) : (
          <div className="ui active inverted dimmer">
            <div className="ui massive text loader">Loading Projects</div>
          </div>
        )}
        {ReactDOM.createPortal(<CookieConsent />, cookieBannerMountPoint)}
      </React.Fragment>
    )
  }
}

export default Dashboard

document.addEventListener('DOMContentLoaded', () => {
  const mountPoint = createRoot(document.getElementById('manage-container'))
  mountPoint.render(React.createElement(Dashboard, {}))

  //Toast Notifications
  const mountPointNotifications = createRoot(
    document.getElementsByClassName('notifications-wrapper')[0],
  )
  mountPointNotifications.render(<NotificationBox />)
})
