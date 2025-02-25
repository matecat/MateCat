import React from 'react'
import moment from 'moment'
import {isUndefined} from 'lodash'

import ManageConstants from '../../constants/ManageConstants'
import JobContainer from './JobContainer'
import UserActions from '../../actions/UserActions'
import ManageActions from '../../actions/ManageActions'
import ProjectsStore from '../../stores/ProjectsStore'
import {getLastProjectActivityLogAction} from '../../api/getLastProjectActivityLogAction'
import CatToolActions from '../../actions/CatToolActions'
import ModalsActions from '../../actions/ModalsActions'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import UserStore from '../../stores/UserStore'
import {
  DROPDOWN_MENU_ALIGN,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import DotsHorizontal from '../../../../../img/icons/DotsHorizontal'
import {UserProjectDropdown} from './UserProjectDropdown'

class ProjectContainer extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      showAllJobs: true,
      visibleJobs: [],
      showAllJobsBoxes: true,
      lastAction: null,
      jobsActions: null,
      projectName: this.props.project.get('name'),
      idTeamSelected: this.props.project.get('id_team'),
    }
    this.getActivityLogUrl = this.getActivityLogUrl.bind(this)
    this.changeUser = this.changeUser.bind(this)
    this.hideProject = this.hideProject.bind(this)
    this.projectTeam = this.props.teams.find(
      (team) => team.get('id') === this.props.project.get('id_team'),
    )
    this.lastActivityController
  }

  hideProject(project) {
    if (this.props.project.get('id') === project.get('id')) {
      $(this.project).transition('fly right')
    }
  }

  hideProjectAfterChangeAssignee = (project, user) => {
    if (this.props.project.get('id') === project.get('id')) {
      const {selectedUser, team} = this.props
      const uid = user ? user.get('uid') : -1
      if (
        (uid !== selectedUser &&
          selectedUser !== ManageConstants.ALL_MEMBERS_FILTER) ||
        (team.get('type') == 'personal' && uid !== UserStore.getUser().user.uid)
      ) {
        setTimeout(() => {
          $(this.project).transition('fly right')
        }, 500)
        setTimeout(() => {
          ManageActions.removeProject(this.props.project)
        }, 1000)
        let name = user?.toJS
          ? user.get('first_name') + ' ' + user.get('last_name')
          : 'Not assigned'
        let notification = {
          title: 'Assignee changed',
          text:
            'The project ' +
            this.props.project.get('name') +
            ' has been assigned to ' +
            name,
          type: 'success',
          position: 'bl',
          allowHtml: true,
          timer: 3000,
        }
        CatToolActions.addNotification(notification)
      }
    }
  }

  thereIsChunkOutsourced(idJob) {
    let outsourceChunk = this.props.project.get('jobs').find(function (item) {
      return !!item.get('outsource') && item.get('id') === idJob
    })
    return !isUndefined(outsourceChunk)
  }

  removeProject() {
    ManageActions.updateStatusProject(this.props.project, 'cancel')
  }

  archiveProject() {
    ManageActions.updateStatusProject(this.props.project, 'archive')
  }

  activateProject() {
    ManageActions.updateStatusProject(this.props.project, 'active')
  }

  deleteProject() {
    const props = {
      text:
        'You are about to delete this project permanently. This action cannot be undone.' +
        ' Are you sure you want to proceed?',
      successText: 'Yes, delete it',
      successCallback: () => {
        ManageActions.updateStatusProject(this.props.project, 'delete')
      },
      cancelCallback: () => {},
    }
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      props,
      'Confirmation required',
    )
  }

  changeUser(value) {
    let user
    const idUser = parseInt(value)
    let team = this.projectTeam
    if (idUser !== -1) {
      let newUser = team.get('members').find(function (member) {
        let user = member.get('user')
        if (user.get('uid') === idUser) {
          return true
        }
      })
      if (!newUser) {
        return
      }
      user = newUser.get('user')
    }
    if (
      (!this.props.project.get('id_assignee') && idUser !== -1) ||
      (this.props.project.get('id_assignee') &&
        idUser != this.props.project.get('id_assignee'))
    ) {
      ManageActions.changeProjectAssignee(team, this.props.project, user)
    }
  }

  changeTeam(value) {
    if (this.props.project.get('id_team') !== parseInt(value)) {
      ManageActions.changeProjectTeam(value, this.props.project)
      this.projectTeam = this.props.teams.find(
        (team) => parseInt(team.get('id')) === parseInt(value),
      )
      this.forceUpdate()
    }
  }

  getDropdownProjectMenu(activityLogUrl) {
    const isArchived = this.props.project.get('is_archived')
    const isCancelled = this.props.project.get('is_cancelled')

    const items = [
      {
        label: (
          <>
            <i className="icon-download-logs icon" />
            Activity Log
          </>
        ),
        onClick: () => window.open(activityLogUrl, '_blank'),
      },
      ...(!isArchived && !isCancelled
        ? [
            {
              label: (
                <>
                  <i className="icon-drawer icon" />
                  Archive project
                </>
              ),
              onClick: () => this.archiveProject(),
            },
            {
              label: (
                <>
                  <i className="icon-trash-o icon" />
                  Cancel project
                </>
              ),
              onClick: () => this.removeProject(),
            },
          ]
        : []),
      ...(isArchived
        ? [
            {
              label: (
                <>
                  <i className="icon-drawer unarchive-project icon" />
                  Unarchive project
                </>
              ),
              onClick: () => this.activateProject(),
            },
            {
              label: (
                <>
                  <i className="icon-trash-o icon" />
                  Cancel project
                </>
              ),
              onClick: () => this.removeProject(),
            },
          ]
        : []),
      ...(isCancelled
        ? [
            {
              label: (
                <>
                  <i className="icon-drawer unarchive-project icon" />
                  Resume Project
                </>
              ),
              onClick: () => this.activateProject(),
            },
            {
              label: (
                <>
                  <i className="icon-drawer icon-trash-o icon" />
                  Delete project permanently
                </>
              ),
              onClick: () => this.deleteProject(),
            },
          ]
        : []),
    ]

    return (
      <DropdownMenu
        className="project-menu-dropdown"
        toggleButtonProps={{
          children: <DotsHorizontal size={18} />,
          testId: 'project-menu-dropdown',
        }}
        align={DROPDOWN_MENU_ALIGN.RIGHT}
        items={items}
      />
    )
  }

  getLastAction() {
    let self = this
    this.lastActivityController = new AbortController()
    getLastProjectActivityLogAction(
      {
        id: this.props.project.get('id'),
        password: this.props.project.get('password'),
      },
      this.lastActivityController,
    ).then((data) => {
      let lastAction = data.activity[0] ? data.activity[0] : null
      self.setState({
        lastAction: lastAction,
        jobsActions: data.activity,
      })
    })
  }

  getLastJobAction(idJob) {
    //Last Activity Log Action
    let lastAction
    if (this.state.jobsActions && this.state.jobsActions.length > 0) {
      lastAction = this.state.jobsActions.find(function (job) {
        return job.id_job == idJob
      })
    }
    return lastAction
  }

  getActivityLogUrl() {
    return (
      '/activityLog/' +
      this.props.project.get('id') +
      '/' +
      this.props.project.get('password')
    )
  }

  getAnalyzeUrl() {
    return (
      '/analyze/' +
      this.props.project.get('project_slug') +
      '/' +
      this.props.project.get('id') +
      '-' +
      this.props.project.get('password')
    )
  }

  getJobSplitUrl(job) {
    return (
      '/analyze/' +
      this.props.project.get('project_slug') +
      '/' +
      this.props.project.get('id') +
      '-' +
      this.props.project.get('password') +
      '?open=split&jobid=' +
      job.get('id')
    )
  }

  getJobMergeUrl(job) {
    return (
      '/analyze/' +
      this.props.project.get('project_slug') +
      '/' +
      this.props.project.get('id') +
      '-' +
      this.props.project.get('password') +
      '?open=merge&jobid=' +
      job.get('id')
    )
  }

  getJobSplitOrMergeButton(isChunk, mergeUrl) {
    if (isChunk) {
      return (
        <a
          className="merge ui basic button"
          target="_blank"
          href={mergeUrl}
          rel="noreferrer"
        >
          <i className="icon-compress icon" /> Merge
        </a>
      )
    } else {
      return ''
    }
  }

  getLastActionDate() {
    let date = new Date(this.state.lastAction.event_date)
    return date.toDateString()
  }

  getJobsList(targetsLangs, jobsList, jobsLength) {
    let self = this
    let chunks = [],
      index
    let tempIdsArray = []
    let orderedJobs = this.props.project.get('jobs')
    orderedJobs.map(function (job, i) {
      let next_job_id = orderedJobs.get(i + 1)
        ? orderedJobs.get(i + 1).get('id')
        : 0
      //To check if is a chunk (jobs with same id)
      let isChunk = false
      if (tempIdsArray.indexOf(job.get('id')) > -1) {
        isChunk = true
        index++
      } else if (
        orderedJobs.get(i + 1) &&
        orderedJobs.get(i + 1).get('id') === job.get('id')
      ) {
        //The first of the Chunk
        isChunk = true
        tempIdsArray.push(job.get('id'))
        index = 1
      } else {
        index = 0
      }

      //Create the Jobs boxes and, if visibles, the jobs body
      if (
        self.state.showAllJobs ||
        self.state.visibleJobs.indexOf(job.get('id')) > -1 ||
        jobsLength === 1
      ) {
        let lastAction = self.getLastJobAction(job.get('id'))
        let isChunkOutsourced = self.thereIsChunkOutsourced(job.get('id'))
        let item = (
          <JobContainer
            key={job.get('id') + '-' + i}
            job={job}
            index={index}
            project={self.props.project}
            jobsLenght={jobsLength}
            changeStatusFn={self.props.changeStatusFn}
            downloadTranslationFn={self.props.downloadTranslationFn}
            isChunk={isChunk}
            lastAction={lastAction}
            isChunkOutsourced={isChunkOutsourced}
            activityLogUrl={self.getActivityLogUrl()}
          />
        )
        chunks.push(item)
        if (job.get('id') !== next_job_id) {
          let jobList = (
            <div
              className="job ui grid"
              key={i - 1 + '-' + job.get('id')}
              data-testid={job.get('id')}
            >
              <div className="job-body sixteen wide column">
                <div className="ui grid chunks">{chunks}</div>
              </div>
            </div>
          )
          jobsList.push(jobList)
          chunks = []
        }
      }
    })
  }

  openAddMember() {
    ManageActions.openAddTeamMemberModal(this.projectTeam.toJS())
  }

  createUserDropDown = (users) => {
    return (
      <UserProjectDropdown
        {...{
          users: users.toJS(),
          project: this.props.project.toJS(),
          openAddMember: this.openAddMember.bind(this),
          changeUser: this.changeUser.bind(this),
          idAssignee: this.props.project.get('id_assignee'),
        }}
      />
    )
  }

  /**
   * To add informations from the plugins
   * @returns {string}
   */
  moreProjectInfo() {
    return ''
  }

  getDropDownUsers() {
    let result = ''
    var self = this
    if (this.props.team.get('type') == 'personal') {
      if (this.props.teams) {
        if (self.projectTeam && self.projectTeam.get('members')) {
          result = this.createUserDropDown(self.projectTeam.get('members'))
        } else {
          UserActions.getAllTeams()
        }
      }
    } else if (this.props.team.get('members')) {
      result = this.createUserDropDown(this.props.team.get('members'))
    }
    return result
  }

  getDropDownTeams = () => {
    const teams = this.props.teams?.toJS() ?? []
    const {idTeamSelected} = this.state

    const items = teams.map((team) => ({
      label: team.name,
      selected: team.id === idTeamSelected,
      onClick: () => {
        this.changeTeam(team.id)
        this.setState({
          idTeamSelected: team.id,
        })
      },
    }))

    return (
      <DropdownMenu
        className="project-team-dropdown"
        align={DROPDOWN_MENU_ALIGN.RIGHT}
        toggleButtonProps={{
          mode: BUTTON_MODE.BASIC,
          size: BUTTON_SIZE.SMALL,
          children: teams.find(({id}) => id === idTeamSelected)?.name,
          testId: 'teams-dropdown',
        }}
        items={items}
      />
    )
  }

  getDueDate() {
    if (this.props.project.get('due_date')) {
      return (
        <div className="eight wide left aligned column pad-top-0 pad-bottom-0">
          <div className="project-due-date">
            {'Due Date: ' +
              moment(this.props.project.get('due_date')).format('LLLL')}
          </div>
        </div>
      )
    }
    return (
      <div className="eight wide left aligned column pad-top-0 pad-bottom-0"></div>
    )
  }

  componentDidMount() {
    this.getLastAction()

    ProjectsStore.addListener(ManageConstants.HIDE_PROJECT, this.hideProject)
    ProjectsStore.addListener(
      ManageConstants.CHANGE_PROJECT_ASSIGNEE,
      this.hideProjectAfterChangeAssignee,
    )
  }

  componentWillUnmount() {
    ProjectsStore.removeListener(ManageConstants.HIDE_PROJECT, this.hideProject)
    ProjectsStore.removeListener(
      ManageConstants.CHANGE_PROJECT_ASSIGNEE,
      this.hideProjectAfterChangeAssignee,
    )
    this.lastActivityController.abort?.()
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      !nextProps.project.equals(this.props.project) ||
      nextState.lastAction !== this.state.lastAction ||
      !nextProps.team.equals(this.props.team) ||
      !nextProps.teams.equals(this.props.teams) ||
      nextState.idTeamSelected !== this.state.idTeamSelected
    )
  }

  render() {
    let activityLogUrl = this.getActivityLogUrl()
    const dropdownProjectMenu = this.getDropdownProjectMenu(activityLogUrl)
    let jobsLength = this.props.project.get('jobs').size

    let targetsLangs = [],
      jobsList = []
    //The list of jobs
    this.getJobsList(targetsLangs, jobsList, jobsLength)

    let dueDateHtml = this.getDueDate()

    //Last Activity Log Action
    let lastAction
    if (this.state.lastAction) {
      let date = this.getLastActionDate()
      lastAction = (
        <div className="eight wide right aligned column pad-top-0 pad-bottom-0">
          <div className="activity-log">
            <a
              href={activityLogUrl}
              target="_blank"
              className="right activity-log"
              title="Activity log"
              rel="noreferrer"
              data-testid="last-action-activity"
            >
              <i>
                {' '}
                <span>
                  Last action: {this.state.lastAction.action + ' on ' + date}
                </span>
                <span> by {this.state.lastAction.first_name}</span>
              </i>
            </a>
          </div>
        </div>
      )
    } else {
      lastAction = (
        <div className="eight wide right aligned column pad-top-0 pad-bottom-0">
          <div className="activity-log">
            <a
              href={activityLogUrl}
              target="_blank"
              className="right activity-log"
              title="Activity log"
              rel="noreferrer"
            >
              <i>
                {' '}
                <span>
                  Created on:{' '}
                  {this.props.project
                    .get('jobs')
                    .first()
                    .get('formatted_create_date')}
                </span>
              </i>
            </a>
          </div>
        </div>
      )
    }

    // Project State (Archived or Cancelled)
    let state = ''
    if (this.props.project.get('is_archived')) {
      state = <div className="status-filter">(archived)</div>
    } else if (this.props.project.get('is_cancelled')) {
      state = <div className="status-filter">(cancelled)</div>
    }

    // Users dropdown
    const dropDownUsers = this.getDropDownUsers()
    const dropDownTeams = this.getDropDownTeams()

    return (
      <div
        className="project ui column grid shadow-1"
        id={'project-' + this.props.project.get('id')}
        ref={(project) => (this.project = project)}
      >
        <div className="sixteen wide column">
          <div className="project-header ui grid">
            <div className="nine wide column">
              <div className="ui stackable grid">
                <div className="sixteen wide column project-title">
                  <div className="ui ribbon label">
                    <div className="project-id" title="Project id">
                      {'(' + this.props.project.get('id') + ')'}
                    </div>
                    <div
                      className="project-name"
                      title="Project name"
                      data-testid="project-name"
                    >
                      {this.state.projectName}
                    </div>
                  </div>
                  {(state !== '' || this.props.project.get('is_cancelled')) && (
                    <div className="project-header-more">{state}</div>
                  )}
                  {this.moreProjectInfo()}
                </div>
              </div>
            </div>

            <div className="seven wide right floated column pad-top-8">
              <div className="ui mobile reversed stackable grid right aligned">
                <div className="sixteen wide right floated column">
                  <div className="project-activity-icon">
                    {dropDownTeams}
                    {dropDownUsers}
                    {dropdownProjectMenu}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div className="project-body ui grid">
            <div className="jobs sixteen wide column pad-bottom-0">
              {jobsList}
            </div>
          </div>

          <div className="project-footer ui grid">
            {dueDateHtml}
            {lastAction}
          </div>
        </div>
      </div>
    )
  }
}

export default ProjectContainer
