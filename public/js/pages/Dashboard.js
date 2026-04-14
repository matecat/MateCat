import React, {
  useCallback,
  useEffect,
  useRef,
  useState,
  useContext,
} from 'react'
import {fromJS} from 'immutable'
import {isEmpty} from 'lodash'
import {debounce} from 'lodash/function'
import ReactDOM, {flushSync} from 'react-dom'

import {ProjectsContainer} from '../components/projects/ProjectsContainer'
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
import {DASHBOARD_REQUEST_PROJECTS_STATUS} from '../constants/Constants'
import {SpinnerLoader} from '../components/common/SpinnerLoader'

const Dashboard = () => {
  const Search = useRef({filter: {}, currentPage: 1})
  const pageLeft = useRef(false)

  const [teams, setTeams] = useState([])
  const [selectedTeam, setSelectedTeam] = useState(undefined)
  const [selectedUser, setSelectedUser] = useState(
    ManageConstants.ALL_MEMBERS_FILTER,
  )
  const [requestProjectsStatus, setRequestProjectsStatus] = useState(undefined)

  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const selectedTeamRef = useRef()
  selectedTeamRef.current = selectedTeam

  const teamsRef = useRef()
  teamsRef.current = teams

  const downloadTranslation = useCallback((project, job, urlWarnings) => {
    let continueDownloadFunction
    const callback = ManageActions.enableDownloadButton.bind(null, job.id)

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
        DownloadFileUtils.downloadFile(job.id, job.password, false, callback)
      }
    }

    const continueDownloadFunctionWithoutErrors = () =>
      continueDownloadFunction({checkErrors: false})

    const openUrl = function () {
      ModalsActions.onCloseModal()
      ManageActions.enableDownloadButton(job.id)
      window.open(urlWarnings, '_blank')
    }

    if (job.warnings_count > 0) {
      ModalsActions.showDownloadWarningsModal(
        continueDownloadFunction,
        continueDownloadFunctionWithoutErrors,
        openUrl,
      )
    } else {
      continueDownloadFunction()
    }
  }, [])

  useEffect(() => {
    const getProjectsErrorHandler = (err) => {
      if (err && err.length && err[0].code == 401) {
        window.location.reload()
        return
      }
      if (err && err.length && err[0].code == 404) {
        const personalTeam = teamsRef.current.find(
          (team) => team.type == 'personal',
        )
        ManageActions.changeTeam(personalTeam)
        return
      }
    }

    const getTeamStructure = (team) => {
      return getTeamMembers(team.id).then((data) => {
        team.members = data.members
        team.pending_invitations = data.pending_invitations
        ManageActions.storeSelectedTeam(team)
      })
    }

    const getData = () => {
      getUserData().then((data) => {
        UserActions.renderTeams(data.teams)
        const selectedTeam = UserActions.getLastTeamSelected(data.teams)
        const teams = data.teams
        setTeams(teams)
        setSelectedTeam(selectedTeam)
        getTeamStructure(selectedTeam).then(() => {
          UserActions.selectTeam(selectedTeam)
          ManageActions.checkPopupInfoTeams()
          getProjects({team: selectedTeam, searchFilter: Search.current})
            .then((res) => {
              setTimeout(() =>
                ManageActions.renderProjects(res.data, selectedTeam, teams),
              )
              ManageActions.storeSelectedTeam(selectedTeam)
            })
            .catch((err) => {
              getProjectsErrorHandler(err)
            })
        })
      })
    }

    const openJobSettings = (job, prName) => {
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

    const openJobTMPanel = (job, prName) => {
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

    const refreshProjects = () => {
      if (Search.current.currentPage === 1) {
        getProjects({
          team: selectedTeamRef.current,
          searchFilter: Search.current,
        })
          .then((res) => {
            const projects = res.data
            ManageActions.updateProjects(projects)
          })
          .catch((err) => {
            getProjectsErrorHandler(err)
          })
      } else {
        let total_projects = []
        const requests = []
        const onDone = (response) => {
          const projects = response.data
          total_projects = [...total_projects, ...projects]
        }
        for (let i = 1; i <= Search.current.currentPage; i++) {
          requests.push(
            getProjects({
              team: selectedTeamRef.current,
              searchFilter: Search.current,
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
          setTeams(data.teams)
          UserActions.updateTeams(data.teams)
        })
        .catch(() => {
          console.log('User not logged')
        })
    }

    const filterProjects = (userUid, name, status) => {
      Search.current.filter = {}
      Search.current.currentPage = 1
      var filter = {}
      if (typeof userUid != 'undefined') {
        if (userUid === ManageConstants.NOT_ASSIGNED_FILTER) {
          filter.no_assignee = true
        } else if (userUid !== ManageConstants.ALL_MEMBERS_FILTER) {
          filter.id_assignee = userUid
        }
        setSelectedUser(userUid)
      }
      if (typeof name !== 'undefined') {
        filter.pn = name
      }
      if (typeof status !== 'undefined') {
        filter.status = status
      }
      Search.current.filter = {...Search.current.filter, ...filter}
      if (!isEmpty(Search.current.filter)) {
        Search.current.currentPage = 1
      }

      getProjects({
        team: selectedTeamRef.current,
        searchFilter: Search.current,
      }).then((res) => {
        const projects = res.data
        ManageActions.renderProjects(
          projects,
          selectedTeamRef.current,
          teamsRef.current,
          false,
          true,
        )
      })
    }

    const removeUserFilter = (uid) => {
      if (Search.current.filter.id_assignee == uid) {
        delete Search.current.filter.id_assignee
      }
    }

    const showProjectsReloadSpinner = () => {
      setRequestProjectsStatus(
        DASHBOARD_REQUEST_PROJECTS_STATUS.RELOAD_IN_PROGRESS,
      )
    }

    const hideProjectsReloadSpinner = () => {
      setRequestProjectsStatus(DASHBOARD_REQUEST_PROJECTS_STATUS.COMPLETED)
    }

    const debounceHideProjectsReloadSpinner = debounce(() => {
      hideProjectsReloadSpinner()
    }, 500)

    const openCreateTeamModal = () => {
      ModalsActions.openCreateTeamModal()
    }

    const openModifyTeamModal = (team, hideChangeName) => {
      ModalsActions.openModifyTeamModal(team, hideChangeName)
    }

    const updateTeams = (teams) => {
      flushSync(() => setTeams(teams.toJS()))
    }

    const updateProjects = (id) => {
      if (id === selectedTeamRef.current?.id) return
      const team = teamsRef.current.find((t) => t.id === id)
      if (team) {
        setSelectedTeam(team)
        setSelectedUser(ManageConstants.ALL_MEMBERS_FILTER)
        Search.current.filter = {}
        Search.current.currentPage = 1

        getProjects({team, searchFilter: Search.current})
          .then((res) => {
            ManageActions.renderProjects(res.data, team, teamsRef.current)
            ManageActions.storeSelectedTeam(team)
          })
          .catch((err) => {
            getProjectsErrorHandler(err)
          })
      }
    }

    const handleScroll = () => {
      const container = document.getElementById('manage-container')
      const scrollableHeight = container.scrollHeight - container.clientHeight
      const modifier = 200
      if (container.scrollTop + modifier > scrollableHeight) {
        Search.current.currentPage = Search.current.currentPage + 1
        setRequestProjectsStatus(
          DASHBOARD_REQUEST_PROJECTS_STATUS.MORE_IN_PROGRESS,
        )
        getProjects({
          team: selectedTeamRef.current,
          searchFilter: Search.current,
        })
          .then((res) => {
            const projects = res.data
            if (projects.length > 0) {
              ManageActions.renderMoreProjects(projects)
            } else {
              ManageActions.noMoreProjects()
            }
          })
          .finally(() =>
            setRequestProjectsStatus(
              DASHBOARD_REQUEST_PROJECTS_STATUS.COMPLETED,
            ),
          )
      }
    }

    getData()

    const container = document.getElementById('manage-container')
    const debouncedScroll = debounce(handleScroll, 300)
    container.addEventListener('scroll', debouncedScroll)

    const handleBlur = () => {
      console.log('leave page')
      pageLeft.current = true
    }

    const handleFocus = () => {
      console.log('Enter page')
      if (pageLeft.current && isUserLogged) {
        refreshProjects()
      }
    }

    window.addEventListener('blur', handleBlur)
    window.addEventListener('focus', handleFocus)

    ProjectsStore.addListener(
      ManageConstants.OPEN_JOB_SETTINGS,
      openJobSettings,
    )
    ProjectsStore.addListener(ManageConstants.OPEN_JOB_TM_PANEL, openJobTMPanel)
    ProjectsStore.addListener(ManageConstants.RELOAD_PROJECTS, refreshProjects)
    ProjectsStore.addListener(ManageConstants.FILTER_PROJECTS, filterProjects)

    UserStore.addListener(ManageConstants.UPDATE_TEAM_MEMBERS, removeUserFilter)
    UserStore.addListener(
      ManageConstants.OPEN_CREATE_TEAM_MODAL,
      openCreateTeamModal,
    )
    UserStore.addListener(
      ManageConstants.OPEN_MODIFY_TEAM_MODAL,
      openModifyTeamModal,
    )
    UserStore.addListener(UserConstants.RENDER_TEAMS, updateTeams)
    UserStore.addListener(UserConstants.CHOOSE_TEAM, updateProjects)

    ProjectsStore.addListener(
      ManageConstants.SHOW_RELOAD_SPINNER,
      showProjectsReloadSpinner,
    )
    ProjectsStore.addListener(
      ManageConstants.RENDER_PROJECTS,
      debounceHideProjectsReloadSpinner,
    )

    return () => {
      container.removeEventListener('scroll', debouncedScroll)

      window.removeEventListener('blur', handleBlur)
      window.removeEventListener('focus', handleFocus)

      ProjectsStore.removeListener(
        ManageConstants.OPEN_JOB_SETTINGS,
        openJobSettings,
      )
      ProjectsStore.removeListener(
        ManageConstants.OPEN_JOB_TM_PANEL,
        openJobTMPanel,
      )
      ProjectsStore.removeListener(
        ManageConstants.RELOAD_PROJECTS,
        refreshProjects,
      )
      ProjectsStore.removeListener(
        ManageConstants.FILTER_PROJECTS,
        filterProjects,
      )

      UserStore.removeListener(
        ManageConstants.UPDATE_TEAM_MEMBERS,
        removeUserFilter,
      )
      UserStore.removeListener(
        ManageConstants.OPEN_CREATE_TEAM_MODAL,
        openCreateTeamModal,
      )
      UserStore.removeListener(
        ManageConstants.OPEN_MODIFY_TEAM_MODAL,
        openModifyTeamModal,
      )
      UserStore.removeListener(UserConstants.RENDER_TEAMS, updateTeams)
      UserStore.removeListener(UserConstants.CHOOSE_TEAM, updateProjects)

      ProjectsStore.removeListener(
        ManageConstants.SHOW_RELOAD_SPINNER,
        showProjectsReloadSpinner,
      )
      ProjectsStore.removeListener(
        ManageConstants.RENDER_PROJECTS,
        debounceHideProjectsReloadSpinner,
      )
    }
  }, [isUserLogged])

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
      {selectedTeam && teams ? (
        <ProjectsContainer
          downloadTranslationFn={downloadTranslation}
          teams={fromJS(teams)}
          team={fromJS(selectedTeam)}
          selectedUser={selectedUser}
          requestProjectsStatus={requestProjectsStatus}
        />
      ) : (
        <SpinnerLoader label="Loading Projects" />
      )}
      {requestProjectsStatus ===
        DASHBOARD_REQUEST_PROJECTS_STATUS.RELOAD_IN_PROGRESS && (
        <SpinnerLoader label="Updating Projects" />
      )}
      {ReactDOM.createPortal(<CookieConsent />, cookieBannerMountPoint)}
      <SocketListener
        isAuthenticated={isUserLogged}
        userId={isUserLogged ? userInfo.user.uid : null}
      />
    </React.Fragment>
  )
}

export default Dashboard

mountPage({
  Component: Dashboard,
  rootElement: document.getElementById('manage-container'),
})
