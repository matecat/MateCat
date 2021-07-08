import AppDispatcher from '../stores/AppDispatcher'
import ManageConstants from '../constants/ManageConstants'
import TeamConstants from '../constants/TeamConstants'
import TeamsStore from '../stores/TeamsStore'
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
    API.PROJECTS.changeJobsOrProjectStatus('prj', project.toJS(), status).done(
      function () {
        AppDispatcher.dispatch({
          actionType: ManageConstants.HIDE_PROJECT,
          project: project,
        })
        setTimeout(function () {
          ManageActions.removeProject(project)
        }, 1000)
      },
    )
  },

  changeJobStatus: function (project, job, status) {
    API.PROJECTS.changeJobsOrProjectStatus('job', job.toJS(), status).done(
      function () {
        AppDispatcher.dispatch({
          actionType: ManageConstants.REMOVE_JOB,
          project: project,
          job: job,
        })
      },
    )
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
    APP.addNotification(notification)
  },

  changeProjectAssignee: function (team, project, user) {
    const uid = user ? user.get('uid') : -1

    API.PROJECTS.changeProjectAssignee(team.get('id'), project.get('id'), uid)
      .done(function () {
        AppDispatcher.dispatch({
          actionType: ManageConstants.CHANGE_PROJECT_ASSIGNEE,
          project: project,
          user: user,
        })
        API.TEAM.getTeamMembers(team.get('id')).done(function (data) {
          team = team.set('members', data.members)
          team = team.set('pending_invitations', data.pending_invitations)
          AppDispatcher.dispatch({
            actionType: TeamConstants.UPDATE_TEAM,
            team: team.toJS(),
          })
        })
      })
      .fail(function () {
        ManageActions.showNotificationProjectsChanged()
        AppDispatcher.dispatch({
          actionType: ManageConstants.RELOAD_PROJECTS,
        })
      })
  },

  changeProjectName: function (team, project, newName) {
    API.PROJECTS.changeProjectName(
      team.get('id'),
      project.get('id'),
      newName,
    ).done(function (response) {
      AppDispatcher.dispatch({
        actionType: ManageConstants.CHANGE_PROJECT_NAME,
        project: project,
        newProject: response.project,
      })
    })
  },

  changeProjectTeam: function (teamId, project) {
    API.PROJECTS.changeProjectTeam(teamId, project.toJS())
      .done(function () {
        var team = TeamsStore.teams.find(function (team) {
          return team.get('id') == teamId
        })
        team = team.toJS()
        const selectedTeam = TeamsStore.getSelectedTeam()
        if (selectedTeam.type == 'personal' && team.type !== 'personal') {
          API.TEAM.getTeamMembers(teamId).then(function (data) {
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
          APP.addNotification(notification)
          API.TEAM.getTeamMembers(selectedTeam.id).then(function (data) {
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
      .fail(function () {
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
    return API.PROJECTS.getSecondPassReview(
      idProject,
      passwordProject,
      idJob,
      passwordJob,
    ).then(function (data) {
      AppDispatcher.dispatch({
        actionType: ManageConstants.ADD_SECOND_PASS,
        idProject: idProject,
        passwordProject: passwordProject,
        idJob: idJob,
        passwordJob: passwordJob,
        secondPAssPassword: data.chunk_review.review_password,
      })
    })
  },

  /********* Modals *********/

  openModifyTeamModal: function (team) {
    API.TEAM.getTeamMembers(team.id).then(function (data) {
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
    API.TEAM.getTeamMembers(team.id).then(function (data) {
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
    API.TEAM.createTeam(teamName, members).done((response) => {
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
    API.TEAM.getTeamMembers(team.id).then(function (data) {
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
    API.TEAM.addUserToTeam(team.toJS(), userEmail).done(function (data) {
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
    API.TEAM.removeUserFromTeam(team.toJS(), userId).done(function (data) {
      if (userId === APP.USER.STORE.user.uid) {
        const selectedTeam = TeamsStore.getSelectedTeam()

        if (selectedTeam.id === team.get('id')) {
          API.TEAM.getAllTeams(true).done(function (data) {
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
    API.TEAM.changeTeamName(team, newName).done(function (data) {
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

module.exports = ManageActions
