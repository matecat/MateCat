/*
 * Projects Store
 */

import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import ManageConstants from '../constants/ManageConstants'
import assign from 'object-assign'
import Immutable from 'immutable'

EventEmitter.prototype.setMaxListeners(0)

let ProjectsStore = assign({}, EventEmitter.prototype, {
  projects: null,

  setProjects: function (projects) {
    this.projects = Immutable.fromJS(projects)
  },
  /**
   * Update all
   */
  updateAll: function (projects) {
    /*this.projects = this.projects.mergeWith((oldProject, newProject, index) => {
            return (newProject.get('id') === newProject.get('id')) ? oldProject : newProject
        },Immutable.fromJS(projects));*/
    // this.projects = this.projects.toSet().union(Immutable.fromJS(projects).toSet()).toList();
    //
    this.projects = Immutable.fromJS(projects)
  },
  /**
   * Add Projects (pagination)
   */
  addProjects: function (projects) {
    this.projects = this.projects.concat(Immutable.fromJS(projects))
  },

  removeProject: function (project) {
    let projectOld = this.projects.find(function (prj) {
      return prj.get('id') == project.get('id')
    })
    let index = this.projects.indexOf(projectOld)
    this.projects = this.projects.delete(index)
  },

  removeJob: function (project, job) {
    let projectOld = this.projects.find(function (prj) {
      return prj.get('id') == project.get('id')
    })
    let indexProject = this.projects.indexOf(projectOld)
    const chunks = project
      .get('jobs')
      .filter((j) => j.get('id') === job.get('id'))
    chunks.forEach((chunk) => {
      //Check jobs length
      if (this.projects.get(indexProject).get('jobs').size === 1) {
        this.removeProject(project)
      } else {
        let indexJob = this.projects
          .get(indexProject)
          .get('jobs')
          .indexOf(chunk)
        this.projects = this.projects.deleteIn([indexProject, 'jobs', indexJob])
      }
    })
  },

  changeJobPass: function (
    projectId,
    jobId,
    password,
    oldPassword,
    revision_number,
    oldTranslator,
  ) {
    let projectOld = this.projects.find(function (prj) {
      return prj.get('id') == projectId
    })
    let indexProject = this.projects.indexOf(projectOld)

    let jobOld = projectOld.get('jobs').find(function (j) {
      return j.get('id') == jobId && j.get('password') === oldPassword
    })

    let indexJob = projectOld.get('jobs').indexOf(jobOld)

    if (!revision_number) {
      this.projects = this.projects.setIn(
        [indexProject, 'jobs', indexJob, 'password'],
        password,
      )
    } else if (revision_number === 1) {
      this.projects = this.projects.setIn(
        [indexProject, 'jobs', indexJob, 'revise_passwords', 0, 'password'],
        password,
      )
    } else if (revision_number === 2) {
      this.projects = this.projects.setIn(
        [indexProject, 'jobs', indexJob, 'revise_passwords', 1, 'password'],
        password,
      )
    }
    this.projects = this.projects.setIn(
      [indexProject, 'jobs', indexJob, 'translator'],
      oldTranslator,
    )
  },

  changeProjectName: function (project, newProject) {
    let projectOld = this.projects.find(function (prj) {
      return prj.get('id') == project.get('id')
    })
    let indexProject = this.projects.indexOf(projectOld)
    this.projects = this.projects.setIn([indexProject, 'name'], newProject.name)
    this.projects = this.projects.setIn(
      [indexProject, 'project_slug'],
      newProject.project_slug,
    )
  },

  changeProjectAssignee: function (project, user) {
    let uid
    if (user) {
      uid = user.get('uid')
    }
    var projectOld = this.projects.find(function (prj) {
      return prj.get('id') == project.get('id')
    })
    var indexProject = this.projects.indexOf(projectOld)
    this.projects = this.projects.setIn([indexProject, 'id_assignee'], uid)
  },

  changeProjectTeam: function (project, teamId) {
    var projectOld = this.projects.find(function (prj) {
      return prj.get('id') == project.get('id')
    })
    var indexProject = this.projects.indexOf(projectOld)
    this.projects = this.projects.setIn(
      [indexProject, 'id_team'],
      parseInt(teamId),
    )
  },

  updateJobOusource: function (project, job, outsource) {
    let projectOld = this.projects.find(function (prj) {
      return prj.get('id') == project.get('id')
    })
    let indexProject = this.projects.indexOf(projectOld)
    if (indexProject != -1) {
      let indexJob = project.get('jobs').indexOf(job)
      this.projects = this.projects.setIn(
        [indexProject, 'jobs', indexJob, 'outsource'],
        Immutable.fromJS(outsource),
      )
    }
  },

  assignTranslator: function (prId, jobId, jobPassword, translator) {
    let project = this.projects.find(function (prj) {
      return prj.get('id') == prId
    })
    let indexProject = this.projects.indexOf(project)
    let job = project.get('jobs').find(function (j) {
      return j.get('id') == jobId && j.get('password') === jobPassword
    })
    let indexJob = project.get('jobs').indexOf(job)
    this.projects = this.projects.setIn(
      [indexProject, 'jobs', indexJob, 'translator'],
      Immutable.fromJS(translator),
    )
  },

  setSecondPassUrl: function (prId, jobId, jobPassword, secondPassPassword) {
    let project = this.projects.find(function (prj) {
      return prj.get('id') == prId
    })
    let indexProject = this.projects.indexOf(project)
    let job = project.get('jobs').find(function (j) {
      return j.get('id') == jobId && j.get('password') === jobPassword
    })
    let indexJob = project.get('jobs').indexOf(job)
    // let url = config.hostpath + '/revise2/' + project.get('name') + '/'+ job.get('source') +'-'+ job.get('target') +'/'+ jobId +'-'+ secondPassPassword;
    this.projects = this.projects.setIn(
      [indexProject, 'jobs', indexJob, 'revise_passwords', 1],
      Immutable.fromJS({revision_number: 2, password: secondPassPassword}),
    )
  },

  unwrapImmutableObject(object) {
    if (object && typeof object.toJS === 'function') {
      return object.toJS()
    } else {
      return object
    }
  },

  emitChange: function () {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case ManageConstants.RENDER_PROJECTS:
      ProjectsStore.setProjects(action.projects)
      ProjectsStore.emitChange(
        action.actionType,
        ProjectsStore.projects,
        Immutable.fromJS(action.team),
        Immutable.fromJS(action.teams),
        action.hideSpinner,
        action.filtering,
      )
      break
    case ManageConstants.RENDER_ALL_TEAM_PROJECTS:
      ProjectsStore.setProjects(action.projects)
      ProjectsStore.emitChange(
        action.actionType,
        ProjectsStore.projects,
        Immutable.fromJS(action.teams),
        action.hideSpinner,
      )
      break
    case ManageConstants.UPDATE_PROJECTS:
      ProjectsStore.updateAll(action.projects)
      ProjectsStore.emitChange(action.actionType, ProjectsStore.projects)
      break
    case ManageConstants.RENDER_MORE_PROJECTS:
      ProjectsStore.addProjects(action.project)
      ProjectsStore.emitChange(
        ManageConstants.RENDER_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.OPEN_JOB_SETTINGS:
      ProjectsStore.emitChange(
        ManageConstants.OPEN_JOB_SETTINGS,
        action.job,
        action.prName,
      )
      break
    case ManageConstants.OPEN_JOB_TM_PANEL:
      ProjectsStore.emitChange(
        ManageConstants.OPEN_JOB_TM_PANEL,
        action.job,
        action.prName,
      )
      break
    case ManageConstants.REMOVE_PROJECT:
      ProjectsStore.removeProject(action.project)
      ProjectsStore.emitChange(
        ManageConstants.RENDER_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.REMOVE_JOB:
      ProjectsStore.removeJob(action.project, action.job)
      ProjectsStore.emitChange(
        ManageConstants.RENDER_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.CHANGE_JOB_PASS:
      ProjectsStore.changeJobPass(
        action.projectId,
        action.jobId,
        action.password,
        action.oldPassword,
        action.revision_number,
        action.oldTranslator,
      )
      ProjectsStore.emitChange(
        ManageConstants.RENDER_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.NO_MORE_PROJECTS:
      ProjectsStore.emitChange(action.actionType)
      break
    case ManageConstants.SHOW_RELOAD_SPINNER:
      ProjectsStore.emitChange(action.actionType)
      break
    case ManageConstants.CHANGE_PROJECT_NAME:
      ProjectsStore.changeProjectName(action.project, action.newProject)
      ProjectsStore.emitChange(
        ManageConstants.UPDATE_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.CHANGE_PROJECT_ASSIGNEE:
      ProjectsStore.changeProjectAssignee(action.project, action.user)
      ProjectsStore.emitChange(action.actionType, action.project, action.user)
      ProjectsStore.emitChange(
        ManageConstants.UPDATE_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.CHANGE_PROJECT_TEAM:
      ProjectsStore.changeProjectTeam(action.project, action.teamId)
      ProjectsStore.emitChange(
        ManageConstants.UPDATE_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.ADD_SECOND_PASS:
      ProjectsStore.setSecondPassUrl(
        action.idProject,
        action.idJob,
        action.passwordJob,
        action.secondPassPassword,
      )
      ProjectsStore.emitChange(
        ManageConstants.UPDATE_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.HIDE_PROJECT:
      ProjectsStore.emitChange(
        action.actionType,
        Immutable.fromJS(action.project),
      )
      break
    case ManageConstants.ENABLE_DOWNLOAD_BUTTON:
    case ManageConstants.DISABLE_DOWNLOAD_BUTTON:
      ProjectsStore.emitChange(action.actionType, action.idProject)
      break
    case ManageConstants.ASSIGN_TRANSLATOR:
      ProjectsStore.assignTranslator(
        action.projectId,
        action.jobId,
        action.jobPassword,
        action.translator,
      )
      ProjectsStore.emitChange(
        ManageConstants.RENDER_PROJECTS,
        ProjectsStore.projects,
      )
      break
    case ManageConstants.RELOAD_PROJECTS:
      ProjectsStore.emitChange(action.actionType)
      break
    case ManageConstants.FILTER_PROJECTS:
      ProjectsStore.emitChange(
        action.actionType,
        action.memberUid,
        action.name,
        action.status,
      )
      break
  }
})

module.exports = ProjectsStore
