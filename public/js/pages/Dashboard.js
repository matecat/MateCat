import React from 'react'
import {fromJS} from 'immutable'
import {isEmpty} from 'lodash'
import {debounce} from 'lodash/function'
import ReactDOM, {flushSync} from 'react-dom'
import $ from 'jquery'

import ProjectsContainer from '../components/projects/ProjectsContainer'
import ManageActions from '../actions/ManageActions'
import UserActions from '../actions/UserActions'
import ModalsActions from '../actions/ModalsActions'
import ProjectsStore from '../stores/ProjectsStore'
import UserStore from '../stores/UserStore'
import ManageConstants from '../constants/ManageConstants'
import UserConstants from '../constants/UserConstants'
import DashboardHeader from '../components/projects/Header'
import Header from '../components/header/Header'
import {getProjects} from '../api/getProjects'
import {getUserData} from '../api/getUserData'
import {getTeamMembers} from '../api/getTeamMembers'
import {CookieConsent} from '../components/common/CookieConsent'
import {mountPage} from './mountPage'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import DownloadFileUtils from '../utils/downloadFileUtils'
import SocketListener from '../sse/SocketListener'

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
  static contextType = ApplicationWrapperContext
  getData = () => {
    getUserData().then((data) => {
      UserActions.renderTeams(data.teams)
      const selectedTeam = UserActions.getLastTeamSelected(data.teams)
      const teams = data.teams
      this.setState({
        teams: teams,
        selectedTeam: selectedTeam,
      })
      this.getTeamStructure(selectedTeam).then(() => {
        UserActions.selectTeam(selectedTeam)
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
            this.getProjectsErrorHandler(err)
          })
      })
    })
  }

  getProjectsErrorHandler = (err) => {
    if (err && err.length && err[0].code == 401) {
      // Not Logged or not in the team
      window.location.reload()
      return
    }

    if (err && err.length && err[0].code == 404) {
      this.selectPersonalTeam()
      return
    }
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
          this.getProjectsErrorHandler(err)
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

  scrollDebounceFn = () => debounce(() => this.handleScroll(), 300)

  handleScroll = () => {
    let scrollableHeight =
      document.getElementById('manage-container').scrollHeight -
      document.getElementById('manage-container').clientHeight
    // When the user is [modifier]px from the bottom, fire the event.
    let modifier = 200
    if (
      document.getElementById('manage-container').scrollTop + modifier >
      scrollableHeight
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
          this.getProjectsErrorHandler(err)
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
    getUserData()
      .then((data) => {
        this.setState({teams: data.teams})
        UserActions.updateTeams(data.teams)
      })
      .catch(() => {
        console.log('User not logged')
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
      '?openTab=options'
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
      '?openTab=tm'
    window.open(url, '_blank')
  }

  downloadTranslation = (project, job, urlWarnings) => {
    let continueDownloadFunction
    let callback = ManageActions.enableDownloadButton.bind(null, job.id)

    if (project.remote_file_service == 'gdrive') {
      continueDownloadFunction = function () {
        ModalsActions.onCloseModal()
        ManageActions.disableDownloadButton(job.id)
        DownloadFileUtils.downloadGDriveFile(
          null,
          job.id,
          job.password,
          callback,
        )
      }
    } else {
      continueDownloadFunction = function () {
        ModalsActions.onCloseModal()
        ManageActions.disableDownloadButton(job.id)
        DownloadFileUtils.downloadFile(job.id, job.password, callback)
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
    if (!isEmpty(this.Search.filter)) {
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
    document
      .getElementById('manage-container')
      .addEventListener('scroll', this.scrollDebounceFn())
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
            if (self.pageLeft && self.context.isUserLogged) {
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
    UserStore.addListener(
      ManageConstants.UPDATE_TEAM_MEMBERS,
      this.removeUserFilter,
    )
    UserStore.addListener(
      ManageConstants.OPEN_CREATE_TEAM_MODAL,
      this.openCreateTeamModal,
    )
    UserStore.addListener(
      ManageConstants.OPEN_MODIFY_TEAM_MODAL,
      this.openModifyTeamModal,
    )
    UserStore.addListener(UserConstants.RENDER_TEAMS, this.updateTeams)
    UserStore.addListener(UserConstants.CHOOSE_TEAM, this.updateProjects)
  }

  componentWillUnmount() {
    document
      .getElementById('manage-container')
      .removeEventListener('scroll', this.scrollDebounceFn())

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
    UserStore.removeListener(
      ManageConstants.UPDATE_TEAM_MEMBERS,
      this.removeUserFilter,
    )
    UserStore.removeListener(
      ManageConstants.OPEN_CREATE_TEAM_MODAL,
      this.openCreateTeamModal,
    )
    UserStore.removeListener(
      ManageConstants.OPEN_MODIFY_TEAM_MODAL,
      this.openModifyTeamModal,
    )
    UserStore.removeListener(UserConstants.RENDER_TEAMS, this.updateTeams)
    UserStore.removeListener(UserConstants.CHOOSE_TEAM, this.updateProjects)
  }

  render() {
    const cookieBannerMountPoint = document.getElementsByTagName('footer')[0]
    return (
      <React.Fragment>
        <DashboardHeader>
          <Header
            user={UserStore.getUser()}
            showFilterProjects={true}
            loggedUser={true}
          />
        </DashboardHeader>
        {this.state.selectedTeam && this.state.teams ? (
          <ProjectsContainer
            downloadTranslationFn={this.downloadTranslation}
            teams={fromJS(this.state.teams)}
            team={fromJS(this.state.selectedTeam)}
            selectedUser={this.state.selectedUser}
            fetchingProjects={this.state.fetchingProjects}
          />
        ) : (
          <div className="ui active inverted dimmer">
            <div className="ui massive text loader">Loading Projects</div>
          </div>
        )}
        {ReactDOM.createPortal(<CookieConsent />, cookieBannerMountPoint)}
        <SocketListener
          isAuthenticated={this.context.isUserLogged}
          userId={
            this.context.isUserLogged ? this.context.userInfo.user.uid : null
          }
        />
      </React.Fragment>
    )
  }
}

export default Dashboard

mountPage({
  Component: Dashboard,
  rootElement: document.getElementById('manage-container'),
})
