import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
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
import {
  Button,
  BUTTON_HTML_TYPE,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import DotsHorizontal from '../../../img/icons/DotsHorizontal'
import {UserProjectDropdown} from './UserProjectDropdown'
import {Controller, useForm} from 'react-hook-form'
import {Input} from '../common/Input/Input'
import IconEdit from '../icons/IconEdit'
import Checkmark from '../../../img/icons/Checkmark'
import IconClose from '../icons/IconClose'
import {ProjectsBulkActionsContext} from './ProjectsBulkActions/ProjectsBulkActionsContext'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'

const ProjectContainer = ({
  project,
  teams,
  team,
  selectedUser,
  changeStatusFn,
  downloadTranslationFn,
}) => {
  const {jobsBulk, onCheckedProject, onCheckedJob} = useContext(
    ProjectsBulkActionsContext,
  )

  const idTeamProject = project.get('id_team')

  const [lastAction, setLastAction] = useState()
  const [jobsActions, setJobsActions] = useState()
  const [idTeamSelected, setIdTeamSelected] = useState(idTeamProject)
  const [shouldShowEditNameIcon, setShouldShowEditNameIcon] = useState(false)
  const [isEditingName, setIsEditingName] = useState(false)

  const {handleSubmit, control, reset} = useForm()

  const projectRef = useRef()
  const projectTeam = useRef()
  projectTeam.current = teams.find(
    (team) => team.get('id') === project.get('id_team'),
  )

  const hideProjectAfterChangeAssignee = useCallback(
    (projectCompare, user) => {
      if (project.get('id') === projectCompare.get('id')) {
        const uid = user ? user.get('uid') : -1
        if (
          (uid !== selectedUser &&
            selectedUser !== ManageConstants.ALL_MEMBERS_FILTER) ||
          (team.get('type') == 'personal' &&
            uid !== UserStore.getUser().user.uid)
        ) {
          setTimeout(() => {
            projectRef.current.style.transition = 'transform 0.5s ease-in-out'
            projectRef.current.style.transform = 'translateX(-2000px)'
          }, 500)
          setTimeout(() => {
            ManageActions.removeProject(project)
          }, 1000)
        }
      }
    },
    [project, selectedUser, team],
  )

  useEffect(() => {
    setIdTeamSelected(idTeamProject)
  }, [idTeamProject])

  const thereIsChunkOutsourced = (idJob) => {
    const outsourceChunk = project.get('jobs').find(function (item) {
      return !!item.get('outsource') && item.get('id') === idJob
    })
    return !isUndefined(outsourceChunk)
  }

  const removeProject = () => {
    ManageActions.updateStatusProject(project, 'cancel')
  }

  const archiveProject = () => {
    ManageActions.updateStatusProject(project, 'archive')
  }

  const activateProject = () => {
    ManageActions.updateStatusProject(project, 'active')
  }

  const deleteProject = () => {
    const props = {
      text:
        'You are about to delete this project permanently. This action cannot be undone.' +
        ' Are you sure you want to proceed?',
      successText: 'Yes, delete it',
      successCallback: () => {
        ManageActions.updateStatusProject(project, 'delete')
      },
      cancelCallback: () => {},
    }
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      props,
      'Confirmation required',
    )
  }

  const changeUser = (value) => {
    let user
    const idUser = parseInt(value)
    const team = projectTeam.current
    if (idUser !== -1) {
      const newUser = team.get('members').find(function (member) {
        const user = member.get('user')
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
      (!project.get('id_assignee') && idUser !== -1) ||
      (project.get('id_assignee') && idUser != project.get('id_assignee'))
    ) {
      ManageActions.changeProjectAssignee(team, project, user)
    }
  }

  const changeTeam = (value) => {
    if (project.get('id_team') !== parseInt(value)) {
      ManageActions.changeProjectTeam(value, project)
      projectTeam.current = teams.find(
        (team) => parseInt(team.get('id')) === parseInt(value),
      )
    }
  }

  const getDropdownProjectMenu = (activityLogUrl) => {
    const isArchived = project.get('is_archived')
    const isCancelled = project.get('is_cancelled')

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
              onClick: archiveProject,
            },
            {
              label: (
                <>
                  <i className="icon-trash-o icon" />
                  Cancel project
                </>
              ),
              onClick: removeProject,
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
              onClick: activateProject,
            },
            {
              label: (
                <>
                  <i className="icon-trash-o icon" />
                  Cancel project
                </>
              ),
              onClick: removeProject,
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
              onClick: activateProject,
            },
            {
              label: (
                <>
                  <i className="icon-drawer icon-trash-o icon" />
                  Delete project permanently
                </>
              ),
              onClick: deleteProject,
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

  const getLastAction = useRef()
  getLastAction.current = () => {
    getLastProjectActivityLogAction({
      id: project.get('id'),
      password: project.get('password'),
    }).then((data) => {
      const lastAction = data.activity[0] ? data.activity[0] : null
      setLastAction(lastAction)
      setJobsActions(data.activity)
    })
  }

  const getLastJobAction = (idJob) => {
    //Last Activity Log Action
    let lastAction
    if (jobsActions && jobsActions.length > 0) {
      lastAction = jobsActions.find(function (job) {
        return job.id_job == idJob
      })
    }
    return lastAction
  }

  const getActivityLogUrl = () => {
    return '/activityLog/' + project.get('id') + '/' + project.get('password')
  }

  const getLastActionDate = () => {
    let date = new Date(lastAction.event_date)
    return date.toDateString()
  }

  const getJobsList = (jobsLength) => {
    const jobsList = []
    let chunks = [],
      index
    const tempIdsArray = []
    let orderedJobs = project.get('jobs')
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

      const lastAction = getLastJobAction(job.get('id'))
      const isChunkOutsourced = thereIsChunkOutsourced(job.get('id'))
      let item = (
        <JobContainer
          key={job.get('id') + '-' + i}
          job={job}
          index={index}
          project={project}
          jobsLenght={jobsLength}
          changeStatusFn={changeStatusFn}
          downloadTranslationFn={downloadTranslationFn}
          isChunk={isChunk}
          lastAction={lastAction}
          isChunkOutsourced={isChunkOutsourced}
          activityLogUrl={getActivityLogUrl()}
          isChecked={jobsBulk.some((jobId) => jobId === job.get('id'))}
          onCheckedJob={onCheckedJob}
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
    })

    return jobsList
  }

  const openAddMember = () => {
    ManageActions.openAddTeamMemberModal(projectTeam.current.toJS())
  }

  const createUserDropDown = (users) => {
    return (
      <UserProjectDropdown
        {...{
          users: users.toJS(),
          project: project.toJS(),
          openAddMember: openAddMember,
          changeUser: changeUser,
          idAssignee: project.get('id_assignee'),
        }}
      />
    )
  }

  /**
   * To add informations from the plugins
   * @returns {string}
   */
  const moreProjectInfo = () => {
    return ''
  }

  const getDropDownUsers = () => {
    let result = ''
    if (team.get('type') == 'personal') {
      if (teams) {
        if (projectTeam.current && projectTeam.current.get('members')) {
          result = createUserDropDown(projectTeam.current.get('members'))
        } else {
          UserActions.getAllTeams()
        }
      }
    } else if (team.get('members')) {
      result = createUserDropDown(team.get('members'))
    }
    return result
  }

  const getDropDownTeams = () => {
    const teamsCollections = teams?.toJS() ?? []
    const items = teamsCollections.map((team) => ({
      label: team.name,
      selected: team.id === idTeamSelected,
      onClick: () => {
        changeTeam(team.id)
        setIdTeamSelected(team.id)
      },
    }))

    return (
      <DropdownMenu
        className="project-team-dropdown"
        align={DROPDOWN_MENU_ALIGN.RIGHT}
        toggleButtonProps={{
          mode: BUTTON_MODE.BASIC,
          size: BUTTON_SIZE.SMALL,
          children: teamsCollections.find(({id}) => id === idTeamSelected)
            ?.name,
          testId: 'teams-dropdown',
        }}
        items={items}
      />
    )
  }

  const getDueDate = () => {
    if (project.get('due_date')) {
      return (
        <div className="eight wide left aligned column pad-top-0 pad-bottom-0">
          <div className="project-due-date">
            {'Due Date: ' + moment(project.get('due_date')).format('LLLL')}
          </div>
        </div>
      )
    }
    return (
      <div className="eight wide left aligned column pad-top-0 pad-bottom-0"></div>
    )
  }

  useEffect(() => {
    getLastAction.current()

    const hideProject = (projectCompare) => {
      if (project.get('id') === projectCompare.get('id')) {
        projectRef.current.style.transition = 'transform 0.5s ease-in-out'
        projectRef.current.style.transform = 'translateX(-2000px)'
      }
    }

    ProjectsStore.addListener(ManageConstants.HIDE_PROJECT, hideProject)
    ProjectsStore.addListener(
      ManageConstants.CHANGE_PROJECT_ASSIGNEE,
      hideProjectAfterChangeAssignee,
    )

    return () => {
      ProjectsStore.removeListener(ManageConstants.HIDE_PROJECT, hideProject)
      ProjectsStore.removeListener(
        ManageConstants.CHANGE_PROJECT_ASSIGNEE,
        hideProjectAfterChangeAssignee,
      )
    }
  }, [project, hideProjectAfterChangeAssignee])

  const handleFormSubmit = (formData) => {
    const {name} = formData
    ManageActions.changeProjectName(project, name)
    setIsEditingName(false)
  }

  const changeNameFormId = `project-change-name-${project.get('id')}`

  const changeNameForm = (
    <form
      id={changeNameFormId}
      className="project-container-form-edit-name"
      onSubmit={handleSubmit(handleFormSubmit)}
      onReset={() => {
        reset()
        setIsEditingName(false)
      }}
    >
      <fieldset>
        <Controller
          control={control}
          defaultValue={project.get('name')}
          name="name"
          rules={{
            required: true,
          }}
          render={({field: {name, onChange, value}, fieldState: {error}}) => (
            <Input
              autoFocus
              placeholder="Name"
              {...{name, value, onChange, error}}
            />
          )}
        />
      </fieldset>
    </form>
  )

  const activityLogUrl = getActivityLogUrl()
  const dropdownProjectMenu = getDropdownProjectMenu(activityLogUrl)
  const jobsLength = project.get('jobs').size

  //The list of jobs
  const jobsList = getJobsList(jobsLength)

  // Users dropdown
  const dropDownUsers = getDropDownUsers()
  const dropDownTeams = getDropDownTeams()

  const state = project.get('is_archived') ? (
    <div className="status-filter">(archived)</div>
  ) : project.get('is_cancelled') ? (
    <div className="status-filter">(cancelled)</div>
  ) : (
    ''
  )

  const jobsBulkForCurrentProject = project
    .get('jobs')
    .toJS()
    .filter(({id}) => jobsBulk.some((value) => value === id))

  return (
    <div
      className="project ui column grid shadow-1"
      id={'project-' + project.get('id')}
      ref={projectRef}
    >
      <div
        className="sixteen wide column"
        onMouseOver={() => setShouldShowEditNameIcon(true)}
        onMouseLeave={() => setShouldShowEditNameIcon(false)}
      >
        <div className="project-header ui grid">
          <div className="nine wide column">
            <div className="ui stackable grid">
              <div
                className={`sixteen wide column project-title ${isEditingName ? 'project-title-editing-name-mode' : ``}`}
              >
                <div className="ui ribbon label">
                  <Checkbox
                    className="project-checkbox"
                    onChange={() => onCheckedProject(project.get('id'))}
                    value={
                      jobsBulkForCurrentProject.length === 0
                        ? CHECKBOX_STATE.UNCHECKED
                        : jobsBulkForCurrentProject.length ===
                            project.get('jobs').size
                          ? CHECKBOX_STATE.CHECKED
                          : CHECKBOX_STATE.INDETERMINATE
                    }
                  />
                  <div className="project-id" title="Project id">
                    {'(' + project.get('id') + ')'}
                  </div>
                  {isEditingName ? (
                    changeNameForm
                  ) : (
                    <div
                      className="project-name"
                      title="Project name"
                      data-testid="project-name"
                    >
                      {project.get('name')}
                    </div>
                  )}
                </div>
                {shouldShowEditNameIcon && !isEditingName && (
                  <Button
                    className="project-container-button-edit-name"
                    mode={BUTTON_MODE.GHOST}
                    size={BUTTON_SIZE.ICON_SMALL}
                    onClick={() => setIsEditingName(true)}
                  >
                    <IconEdit size={18} />
                  </Button>
                )}
                {isEditingName && (
                  <>
                    <Button
                      type={BUTTON_TYPE.PRIMARY}
                      size={BUTTON_SIZE.SMALL}
                      htmlType={BUTTON_HTML_TYPE.SUBMIT}
                      form={changeNameFormId}
                    >
                      <Checkmark size={14} />
                      Confirm
                    </Button>

                    <Button
                      type={BUTTON_TYPE.WARNING}
                      size={BUTTON_SIZE.SMALL}
                      htmlType={BUTTON_HTML_TYPE.RESET}
                      form={changeNameFormId}
                    >
                      <IconClose size={11} />
                    </Button>
                  </>
                )}
                {(state !== '' || project.get('is_cancelled')) && (
                  <div className="project-header-more">{state}</div>
                )}
                {moreProjectInfo()}
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
          {getDueDate()}
          {lastAction ? (
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
                      Last action:{' '}
                      {lastAction.action + ' on ' + getLastActionDate()}
                    </span>
                    <span> by {lastAction.first_name}</span>
                  </i>
                </a>
              </div>
            </div>
          ) : (
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
                      {project.get('jobs').first().get('formatted_create_date')}
                    </span>
                  </i>
                </a>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default ProjectContainer
