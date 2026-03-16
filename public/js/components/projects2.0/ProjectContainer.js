import PropTypes from 'prop-types'
import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {ProjectsBulkActionsContext} from '../projects/ProjectsBulkActions/ProjectsBulkActionsContext'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'
import {Controller, useForm} from 'react-hook-form'
import ManageActions from '../../actions/ManageActions'
import {Input} from '../common/Input/Input'
import {
  Button,
  BUTTON_HTML_TYPE,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import IconEdit from '../icons/IconEdit'
import Checkmark from '../../../img/icons/Checkmark'
import IconClose from '../icons/IconClose'
import {JobContainer} from './JobContainer'
import {getLastProjectActivityLogAction} from '../../api/getLastProjectActivityLogAction'
import {isUndefined} from 'lodash'
import {
  DROPDOWN_MENU_ALIGN,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import UserActions from '../../actions/UserActions'
import {UserProjectDropdown} from '../projects/UserProjectDropdown'
import FileLog from '../../../img/icons/FileLog'
import Archive from '../../../img/icons/Archive'
import Trash from '../../../img/icons/Trash'
import FlipBackward from '../icons/FlipBackward'
import DotsHorizontal from '../../../img/icons/DotsHorizontal'
import ModalsActions from '../../actions/ModalsActions'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import IconDown from '../icons/IconDown'
import ManageConstants from '../../constants/ManageConstants'
import UserStore from '../../stores/UserStore'
import ProjectsStore from '../../stores/ProjectsStore'
import {ChunksJobContainer} from './ChunksJobContainer'
import {fromJS} from 'immutable'

export const ProjectContainer = ({
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

  const {handleSubmit, control, reset} = useForm()

  const idTeamProject = project.get('id_team')

  const [shouldShowMoreActions, setShouldShowMoreActions] = useState(false)
  const [isEditingName, setIsEditingName] = useState(false)
  const [lastAction, setLastAction] = useState()
  const [jobsActions, setJobsActions] = useState()
  const [idTeamSelected, setIdTeamSelected] = useState(idTeamProject)

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

  useEffect(() => {
    setIdTeamSelected(idTeamProject)
  }, [idTeamProject])

  const changeNameFormId = `project-change-name-${project.get('id')}`

  const jobsBulkForCurrentProject = project
    .get('jobs')
    .toJS()
    .filter(({id}) => jobsBulk.some((value) => value === id))

  const handleFormSubmit = (formData) => {
    const {name} = formData
    ManageActions.changeProjectName(project, name)
    setIsEditingName(false)
  }

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

  const projectNameElements = (
    <div className="project-container-header-name">
      {isEditingName ? (
        <>
          {changeNameForm}
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
        </>
      ) : (
        <>
          <h6 title="Project name" data-testid="project-name">
            {project.get('name')}
          </h6>
          <Button
            className="project-container-button-edit-name"
            mode={BUTTON_MODE.GHOST}
            size={BUTTON_SIZE.ICON_SMALL}
            onClick={() => setIsEditingName(true)}
          >
            <IconEdit size={16} />
          </Button>
        </>
      )}
    </div>
  )

  const getActivityLogUrl = () => {
    return '/activityLog/' + project.get('id') + '/' + project.get('password')
  }

  const thereIsChunkOutsourced = (idJob) => {
    const outsourceChunk = project.get('jobs').find(function (item) {
      return !!item.get('outsource') && item.get('id') === idJob
    })
    return !isUndefined(outsourceChunk)
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

  const getJobContainer = () => {
    const jobs = project.get('jobs')

    const chunks = jobs.toJS().reduce((acc, job) => {
      const id = job.id
      if (
        acc.some((jobItem) =>
          Array.isArray(jobItem)
            ? jobItem.some((chunkItem) => chunkItem.id === id)
            : jobItem.id === id,
        )
      ) {
        const index = acc.findIndex((jobItem) =>
          Array.isArray(jobItem)
            ? jobItem.some((chunkItem) => chunkItem.id === id)
            : jobItem.id === id,
        )
        if (Array.isArray(acc[index])) {
          acc[index].push(job)
        } else {
          acc[index] = [acc[index], job]
        }

        return acc
      }

      return [...acc, job]
    }, [])

    return chunks.map((item) => {
      const job = fromJS(Array.isArray(item) ? item[0] : item)

      const lastAction = getLastJobAction(job.get('id'))
      const isChunkOutsourced = thereIsChunkOutsourced(job.get('id'))

      if (Array.isArray(item)) {
        return (
          <ChunksJobContainer
            key={job.get('id')}
            jobs={item.map((itemJS) => fromJS(itemJS))}
            project={project}
            jobsLenght={project.get('jobs').size}
            changeStatusFn={changeStatusFn}
            downloadTranslationFn={downloadTranslationFn}
            isChunk={true}
            lastAction={lastAction}
            isChunkOutsourced={isChunkOutsourced}
            activityLogUrl={getActivityLogUrl()}
            isChecked={jobsBulk.some((jobId) => jobId === job.get('id'))}
            onCheckedJob={onCheckedJob}
          />
        )
      }

      return (
        <JobContainer
          key={job.get('id')}
          job={job}
          project={project}
          jobsLenght={project.get('jobs').size}
          changeStatusFn={changeStatusFn}
          downloadTranslationFn={downloadTranslationFn}
          isChunk={false}
          lastAction={lastAction}
          isChunkOutsourced={isChunkOutsourced}
          activityLogUrl={getActivityLogUrl()}
          isChecked={jobsBulk.some((jobId) => jobId === job.get('id'))}
          onCheckedJob={onCheckedJob}
        />
      )
    })

    // return jobs.map((job, index) => {
    //   let isChunk = false
    //   if (tempIdsArray.indexOf(job.get('id')) > -1) {
    //     isChunk = true
    //     index++
    //   } else if (
    //     jobs.get(index + 1) &&
    //     jobs.get(index + 1).get('id') === job.get('id')
    //   ) {
    //     //The first of the Chunk
    //     isChunk = true
    //     tempIdsArray.push(job.get('id'))
    //     index = 1
    //   } else {
    //     index = 0
    //   }

    //   const lastAction = getLastJobAction(job.get('id'))
    //   const isChunkOutsourced = thereIsChunkOutsourced(job.get('id'))

    //   return (
    //     <JobContainer
    //       key={job.get('id') + '-' + index}
    //       job={job}
    //       index={index}
    //       project={project}
    //       jobsLenght={project.get('jobs').size}
    //       changeStatusFn={changeStatusFn}
    //       downloadTranslationFn={downloadTranslationFn}
    //       isChunk={isChunk}
    //       lastAction={lastAction}
    //       isChunkOutsourced={isChunkOutsourced}
    //       activityLogUrl={getActivityLogUrl()}
    //       isChecked={jobsBulk.some((jobId) => jobId === job.get('id'))}
    //       onCheckedJob={onCheckedJob}
    //     />
    //   )
    // })
  }

  const changeTeam = (value) => {
    if (project.get('id_team') !== parseInt(value)) {
      ManageActions.changeProjectTeam(value, project)
      projectTeam.current = teams.find(
        (team) => parseInt(team.get('id')) === parseInt(value),
      )
    }
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
          children: (
            <>
              {teamsCollections.find(({id}) => id === idTeamSelected)?.name}
              <IconDown size={16} />
            </>
          ),
          testId: 'teams-dropdown',
        }}
        items={items}
      />
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

  const openAddMember = () =>
    ManageActions.openAddTeamMemberModal(projectTeam.current.toJS())

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

  const removeProject = () =>
    ManageActions.updateStatusProject(project, 'cancel')

  const archiveProject = () =>
    ManageActions.updateStatusProject(project, 'archive')

  const activateProject = () =>
    ManageActions.updateStatusProject(project, 'active')

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

  const getDropdownProjectMenu = (activityLogUrl) => {
    const isArchived = project.get('is_archived')
    const isCancelled = project.get('is_cancelled')

    const items = [
      {
        label: (
          <>
            <FileLog size={18} />
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
                  <Archive size={18} />
                  Archive project
                </>
              ),
              onClick: archiveProject,
            },
            {
              label: (
                <>
                  <Trash size={18} />
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
                  <FlipBackward size={18} />
                  Unarchive project
                </>
              ),
              onClick: activateProject,
            },
            {
              label: (
                <>
                  <Trash size={18} />
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
                  <FlipBackward size={18} />
                  Resume Project
                </>
              ),
              onClick: activateProject,
            },
            {
              label: (
                <>
                  <Trash size={18} />
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
          size: BUTTON_SIZE.ICON_SMALL,
          children: <DotsHorizontal size={16} />,
          testId: 'project-menu-dropdown',
        }}
        align={DROPDOWN_MENU_ALIGN.RIGHT}
        items={items}
      />
    )
  }

  return (
    <div ref={projectRef} className="project-container">
      <div className="project-container-header">
        <div className="project-container-header-sx">
          <Checkbox
            className="project-checkbox"
            onChange={() => onCheckedProject(project.get('id'))}
            value={
              jobsBulkForCurrentProject.length === 0
                ? CHECKBOX_STATE.UNCHECKED
                : jobsBulkForCurrentProject.length === project.get('jobs').size
                  ? CHECKBOX_STATE.CHECKED
                  : CHECKBOX_STATE.INDETERMINATE
            }
          />
          <div>
            {projectNameElements}
            <span title="Project id">ID: {project.get('id')}</span>
          </div>
        </div>
        <div className="project-container-header-dx">
          {getDropDownTeams()}
          {getDropDownUsers()}
          {getDropdownProjectMenu(getActivityLogUrl())}
        </div>
      </div>
      {getJobContainer()}
      <div className="project-container-footer">Footer</div>
    </div>
  )
}

ProjectContainer.propTypes = {
  project: PropTypes.object,
  teams: PropTypes.object,
  team: PropTypes.object,
  selectedUser: PropTypes.string,
  changeStatusFn: PropTypes.func,
  downloadTranslationFn: PropTypes.func,
}
