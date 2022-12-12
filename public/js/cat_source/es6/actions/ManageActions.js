import AppDispatcher from '../stores/AppDispatcher'
import ManageConstants from '../constants/ManageConstants'
import TeamConstants from '../constants/TeamConstants'
import TeamsStore from '../stores/TeamsStore'
import {changeProjectName} from '../api/changeProjectName'
import {changeProjectAssignee} from '../api/changeProjectAssignee'
import {changeProjectTeam} from '../api/changeProjectTeam'
import {getSecondPassReview} from '../api/getSecondPassReview'
import {getUserData} from '../api/getUserData'
import {getTeamMembers} from '../api/getTeamMembers'
import {createTeam} from '../api/createTeam'
import {addUserTeam} from '../api/addUserTeam'
import {removeTeamUser} from '../api/removeTeamUser'
import {updateTeamName} from '../api/updateTeamName'
import CatToolActions from './CatToolActions'
import {changeProjectStatus} from '../api/changeProjectStatus'
import {changeJobStatus} from '../api/changeJobStatus'

let ManageActions = {
  /********* Projects *********/

  /** Render the list of projects
   * @param projects
   * @param team
   * @param teams
   * @param hideSpinner
   * */
  renderProjects: function (
    projects,
    team,
    teams,
    hideSpinner,
    filtering = false,
  ) {
    this.popupInfoTeamsStorageName = 'infoTeamPopup-' + config.userMail
    AppDispatcher.dispatch({
      actionType: ManageConstants.RENDER_PROJECTS,
      projects,
      team,
      teams,
      hideSpinner,
      filtering,
    })
  },

  updateProjects: function (projects) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.UPDATE_PROJECTS,
      projects: projects,
    })
  },

  /** Render the more projects
   * @param projects
   */
  renderMoreProjects: function (projects) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.RENDER_MORE_PROJECTS,
      project: projects,
    })
  },
  /** Open the translate page with the options tab open
   * @param job
   * @param prName
   */
  openJobSettings: function (job, prName) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.OPEN_JOB_SETTINGS,
      job: job,
      prName: prName,
    })
  },

  /** Open the translate page with the TM tab open
   * @param job
   * @param prName
   */
  openJobTMPanel: function (job, prName) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.OPEN_JOB_TM_PANEL,
      job: job,
      prName: prName,
    })
  },

  updateStatusProject: function (project, status) {
    changeProjectStatus(
      project.get('id'),
      project.get('password'),
      status,
    ).then(() => {
      AppDispatcher.dispatch({
        actionType: ManageConstants.HIDE_PROJECT,
        project: project,
      })
      setTimeout(function () {
        ManageActions.removeProject(project)
      }, 1000)
    })
  },

  changeJobStatus: function (project, job, status) {
    changeJobStatus(job.get('id'), job.get('password'), status).then(() => {
      AppDispatcher.dispatch({
        actionType: ManageConstants.REMOVE_JOB,
        project: project,
        job: job,
      })
    })
  },

  changeJobPassword: function (
    project,
    job,
    password,
    oldPassword,
    revision_number,
    translator,
  ) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.CHANGE_JOB_PASS,
      projectId: project.get('id'),
      jobId: job.get('id'),
      password: password,
      oldPassword: oldPassword,
      revision_number,
      oldTranslator: translator,
    })
  },
  //TODO: remove
  changeJobPasswordFromOutsource: function (
    project,
    job,
    password,
    oldPassword,
  ) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.CHANGE_JOB_PASS,
      projectId: project.id,
      jobId: job.id,
      password: password,
      oldPassword: oldPassword,
    })
  },

  noMoreProjects: function () {
    AppDispatcher.dispatch({
      actionType: ManageConstants.NO_MORE_PROJECTS,
    })
  },

  showReloadSpinner: function () {
    AppDispatcher.dispatch({
      actionType: ManageConstants.SHOW_RELOAD_SPINNER,
    })
  },

  filterProjects: function (member, name, status) {
    this.showReloadSpinner()
    const memberUid =
      member && member.toJS ? member.get('user').get('uid') : member
    AppDispatcher.dispatch({
      actionType: ManageConstants.FILTER_PROJECTS,
      memberUid,
      name,
      status,
    })
  },

  removeProject: function (project) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.REMOVE_PROJECT,
      project: project,
    })
  },

  showNotificationProjectsChanged: function () {
    var notification = {
      title: 'Ooops...',
      text: 'Something went wrong, the project has been assigned to another member or moved to another team.',
      type: 'warning',
      position: 'bl',
      allowHtml: true,
      autoDismiss: false,
    }
    CatToolActions.addNotification(notification)
  },

  changeProjectAssignee: function (team, project, user) {
    const uid = user ? user.get('uid') : null
    changeProjectAssignee(team.get('id'), project.get('id'), uid)
      .then(() => {
        AppDispatcher.dispatch({
          actionType: ManageConstants.CHANGE_PROJECT_ASSIGNEE,
          project: project,
          user: user,
        })
        getTeamMembers(team.get('id')).then(function (data) {
          team = team.set('members', data.members)
          team = team.set('pending_invitations', data.pending_invitations)
          AppDispatcher.dispatch({
            actionType: TeamConstants.UPDATE_TEAM,
            team: team.toJS(),
          })
        })
      })
      .catch(() => {
        ManageActions.showNotificationProjectsChanged()
        AppDispatcher.dispatch({
          actionType: ManageConstants.RELOAD_PROJECTS,
        })
      })
  },

  changeProjectName: function (team, project, newName) {
    changeProjectName(team.get('id'), project.get('id'), newName).then(
      (response) => {
        AppDispatcher.dispatch({
          actionType: ManageConstants.CHANGE_PROJECT_NAME,
          project: project,
          newProject: response.project,
        })
      },
    )
  },

  changeProjectTeam: function (teamId, project) {
    changeProjectTeam(teamId, project.toJS())
      .then(() => {
        var team = TeamsStore.teams.find(function (team) {
          return team.get('id') == teamId
        })
        team = team.toJS()
        const selectedTeam = TeamsStore.getSelectedTeam()
        if (selectedTeam.type == 'personal' && team.type !== 'personal') {
          getTeamMembers(teamId).then(function (data) {
            team.members = data.members
            team.pending_invitations = data.pending_invitations
            AppDispatcher.dispatch({
              actionType: TeamConstants.UPDATE_TEAM,
              team: team,
            })
            setTimeout(function () {
              AppDispatcher.dispatch({
                actionType: ManageConstants.CHANGE_PROJECT_TEAM,
                project: project,
                teamId: teamId,
              })
            })
          })
        } else if (
          teamId !== selectedTeam.id &&
          selectedTeam.type !== 'personal'
        ) {
          setTimeout(function () {
            AppDispatcher.dispatch({
              actionType: ManageConstants.HIDE_PROJECT,
              project: project,
            })
          }, 500)
          setTimeout(function () {
            AppDispatcher.dispatch({
              actionType: ManageConstants.REMOVE_PROJECT,
              project: project,
            })
          }, 1000)
          let notification = {
            title: 'Project Moved',
            text:
              'The project ' +
              project.get('name') +
              ' has been moved to the ' +
              team.name +
              ' Team',
            type: 'success',
            position: 'bl',
            allowHtml: true,
            timer: 3000,
          }
          CatToolActions.addNotification(notification)
          getTeamMembers(selectedTeam.id).then(function (data) {
            selectedTeam.members = data.members
            selectedTeam.pending_invitations = data.pending_invitations
            AppDispatcher.dispatch({
              actionType: TeamConstants.UPDATE_TEAM,
              team: selectedTeam,
            })
            setTimeout(function () {
              AppDispatcher.dispatch({
                actionType: ManageConstants.CHANGE_PROJECT_TEAM,
                project: project,
                teamId: selectedTeam.id,
              })
            })
          })
        }
      })
      .catch(() => {
        ManageActions.showNotificationProjectsChanged()
        AppDispatcher.dispatch({
          actionType: ManageConstants.RELOAD_PROJECTS,
        })
      })
  },

  assignTranslator: function (projectId, jobId, jobPassword, translator) {
    if ($('body').hasClass('manage')) {
      AppDispatcher.dispatch({
        actionType: ManageConstants.ASSIGN_TRANSLATOR,
        projectId: projectId,
        jobId: jobId,
        jobPassword: jobPassword,
        translator: translator,
      })
    } else {
      //TODO Delete this function in the new analysis version
      UI.updateOutsourceInfo(translator)
    }
  },

  enableDownloadButton: function (id) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.ENABLE_DOWNLOAD_BUTTON,
      idProject: id,
    })
  },

  disableDownloadButton: function (id) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.DISABLE_DOWNLOAD_BUTTON,
      idProject: id,
    })
  },

  checkPopupInfoTeams: function () {
    var openPopup = localStorage.getItem(this.popupInfoTeamsStorageName)
    if (!openPopup) {
      ManageActions.openPopupTeams()
    }
  },

  setPopupTeamsCookie: function () {
    localStorage.setItem(this.popupInfoTeamsStorageName, true)
  },

  getSecondPassReview: function (
    idProject,
    passwordProject,
    idJob,
    passwordJob,
  ) {
    return getSecondPassReview(
      idProject,
      passwordProject,
      idJob,
      passwordJob,
    ).then((data) => {
      AppDispatcher.dispatch({
        actionType: ManageConstants.ADD_SECOND_PASS,
        idProject: idProject,
        passwordProject: passwordProject,
        idJob: idJob,
        passwordJob: passwordJob,
        secondPassPassword: data.chunk_review.review_password,
      })
    })
  },

  /********* Modals *********/

  openModifyTeamModal: function (team) {
    getTeamMembers(team.id).then(function (data) {
      team.members = data.members
      team.pending_invitations = data.pending_invitations
      AppDispatcher.dispatch({
        actionType: ManageConstants.OPEN_MODIFY_TEAM_MODAL,
        team: team,
        hideChangeName: false,
      })
    })
  },

  openAddTeamMemberModal: function (team) {
    getTeamMembers(team.id).then(function (data) {
      team.members = data.members
      team.pending_invitations = data.pending_invitations
      AppDispatcher.dispatch({
        actionType: ManageConstants.OPEN_MODIFY_TEAM_MODAL,
        team: team,
        hideChangeName: true,
      })
    })
  },

  openPopupTeams: function () {
    AppDispatcher.dispatch({
      actionType: ManageConstants.OPEN_INFO_TEAMS_POPUP,
    })
  },

  /********* Teams: actions from modals *********/

  /**
   * Called from manage modal
   * @param teamName
   * @param members
   */
  createTeam: function (teamName, members) {
    createTeam(teamName, members).then((response) => {
      let team = response.team
      this.showReloadSpinner()
      APP.setTeamInStorage(team.id)
      AppDispatcher.dispatch({
        actionType: TeamConstants.ADD_TEAM,
        team: team,
      })
      AppDispatcher.dispatch({
        actionType: TeamConstants.CHOOSE_TEAM,
        teamId: team.id,
      })
    })
  },

  changeTeam: function (team) {
    this.showReloadSpinner()
    APP.setTeamInStorage(team.id)
    getTeamMembers(team.id).then(function (data) {
      let selectedTeam = team
      selectedTeam.members = data.members
      selectedTeam.pending_invitations = data.pending_invitations
      AppDispatcher.dispatch({
        actionType: TeamConstants.UPDATE_TEAM,
        team: selectedTeam,
      })
      AppDispatcher.dispatch({
        actionType: TeamConstants.CHOOSE_TEAM,
        teamId: selectedTeam.id,
      })
    })
  },

  addUserToTeam: function (team, userEmail) {
    addUserTeam(team.toJS(), userEmail).then(function (data) {
      AppDispatcher.dispatch({
        actionType: ManageConstants.UPDATE_TEAM_MEMBERS,
        team: team,
        members: data.members,
        pending_invitations: data.pending_invitations,
      })
    })
  },

  removeUserFromTeam: function (team, user) {
    var self = this
    var userId = user.get('uid')
    removeTeamUser(team.toJS(), userId).then(function (data) {
      if (userId === APP.USER.STORE.user.uid) {
        const selectedTeam = TeamsStore.getSelectedTeam()

        if (selectedTeam.id === team.get('id')) {
          getUserData().then(function (data) {
            AppDispatcher.dispatch({
              actionType: TeamConstants.RENDER_TEAMS,
              teams: data.teams,
            })
            self.changeTeam(data.teams[0])
          })
        } else {
          AppDispatcher.dispatch({
            actionType: ManageConstants.REMOVE_TEAM,
            team: team,
          })
        }
      } else {
        AppDispatcher.dispatch({
          actionType: ManageConstants.UPDATE_TEAM_MEMBERS,
          team: team,
          members: data.members,
          pending_invitations: data.pending_invitations,
        })
      }
      AppDispatcher.dispatch({
        actionType: ManageConstants.RELOAD_PROJECTS,
      })
    })
  },

  changeTeamName: function (team, newName) {
    updateTeamName(team, newName).then(function (data) {
      AppDispatcher.dispatch({
        actionType: ManageConstants.UPDATE_TEAM_NAME,
        oldTeam: team,
        team: data.team[0],
      })
    })
  },
  storeSelectedTeam: function (selectedTeam) {
    AppDispatcher.dispatch({
      actionType: ManageConstants.SELECTED_TEAM,
      selectedTeam,
    })
  },
  reloadProjects: function () {
    AppDispatcher.dispatch({
      actionType: ManageConstants.RELOAD_PROJECTS,
    })
  },
}

export default ManageActions
