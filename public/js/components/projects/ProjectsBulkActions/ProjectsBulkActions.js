import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {ProjectsBulkActionsContext} from './ProjectsBulkActionsContext'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../common/Button/Button'
import useEvent from '../../../hooks/useEvent'
import ModalsActions from '../../../actions/ModalsActions'
import ProjectsStore from '../../../stores/ProjectsStore'
import ManageConstants from '../../../constants/ManageConstants'
import {BulkChangePassword} from './BulkChangePassword'
import ConfirmMessageModal from '../../modals/ConfirmMessageModal'
import BulkMoveToTeam from './BulkMoveToTeam'
import CatToolActions from '../../../actions/CatToolActions'
import ManageActions from '../../../actions/ManageActions'
import {fromJS} from 'immutable'
import UserStore from '../../../stores/UserStore'
import UserConstants from '../../../constants/UserConstants'
import {changeJobPassword} from '../../../api/changeJobPassword'
import {BulkAssignToMember} from './BulkAssignToMember'

const MAX_JOBS_SELECTABLE = 100

const JOBS_ACTIONS = {
  ACTIVE: {id: 'active', label: 'Active'},
  CANCEL: {id: 'cancel', label: 'Cancel'},
  DELETE: {id: 'delete', label: 'Delete'},
  DELETE_PERMANENTLY: {id: 'delete', label: 'Delete permanently'},
  ARCHIVE: {id: 'archive', label: 'Archive'},
  UNARCHIVE: {id: 'unarchive', label: 'Unarchive'},
  RESUME: {id: 'resume', label: 'Resume'},
  CHANGE_PASSWORD: {id: 'change_password', label: 'Change password'},
  GENERATE_REVISE_2: {
    id: 'generate_second_pass',
    label: 'Generate revise 2 link',
  },
  ASSIGN_TO_MEMBER: {id: 'assign_to_member', label: 'Assign to member'},
  ASSIGN_TO_TEAM: {id: 'assign_to_team', label: 'Move to team'},
}

const ACTIONS_BY_FILTER = {
  active: [
    JOBS_ACTIONS.ARCHIVE,
    JOBS_ACTIONS.CANCEL,
    JOBS_ACTIONS.CHANGE_PASSWORD,
    JOBS_ACTIONS.GENERATE_REVISE_2,
    JOBS_ACTIONS.ASSIGN_TO_TEAM,
    JOBS_ACTIONS.ASSIGN_TO_MEMBER,
  ],
  archived: [
    JOBS_ACTIONS.UNARCHIVE,
    JOBS_ACTIONS.CANCEL,
    JOBS_ACTIONS.ASSIGN_TO_TEAM,
    JOBS_ACTIONS.ASSIGN_TO_MEMBER,
  ],
  cancelled: [
    JOBS_ACTIONS.RESUME,
    JOBS_ACTIONS.DELETE_PERMANENTLY,
    JOBS_ACTIONS.ARCHIVE,
    JOBS_ACTIONS.ASSIGN_TO_TEAM,
    JOBS_ACTIONS.ASSIGN_TO_MEMBER,
  ],
}

export const ProjectsBulkActions = ({projects, teams, children}) => {
  const [jobsBulk, setJobsBulk] = useState([])
  const [filterStatusApplied, setFilterStatusApplied] = useState('active')

  const shiftKeyRef = useRef({
    isPressed: false,
    startJob: undefined,
  })

  useEffect(() => {
    const onChangeProjectStatus = (userUid, name, status) => {
      setFilterStatusApplied(status)
      setJobsBulk([])
    }

    const onTeamChange = () => setJobsBulk([])

    ProjectsStore.addListener(
      ManageConstants.FILTER_PROJECTS,
      onChangeProjectStatus,
    )

    UserStore.addListener(UserConstants.CHOOSE_TEAM, onTeamChange)

    return () => {
      ProjectsStore.removeListener(
        ManageConstants.FILTER_PROJECTS,
        onChangeProjectStatus,
      )

      UserStore.removeListener(UserConstants.CHOOSE_TEAM, onTeamChange)
    }
  }, [])

  const allJobs = useMemo(
    () => projects.reduce((acc, cur) => [...acc, ...cur.jobs], []),
    [projects],
  )

  const isSelectedAllJobsByProjects = useMemo(() => {
    const projectsInvolved = projects.filter((project) =>
      project.jobs.some((job) => jobsBulk.some((value) => value === job.id)),
    )

    return (
      projectsInvolved.length > 0 &&
      projectsInvolved.every((project) => {
        const filteredLength = project.jobs.filter((job) =>
          jobsBulk.some((value) => value === job.id),
        ).length

        return filteredLength === project.jobs.length
      })
    )
  }, [jobsBulk, projects])

  const projectsSelected = useMemo(() => {
    const jobsSelected = allJobs.filter(({id}) =>
      jobsBulk.some((value) => value === id),
    )

    return projects.filter((project) =>
      project.jobs.some((job) =>
        jobsSelected.some((jobSelected) => jobSelected.id === job.id),
      ),
    )
  }, [projects, allJobs, jobsBulk])

  const onCheckedJob = useCallback(
    (jobId) => {
      const currentJob = allJobs.find(({id}) => id === jobId)

      let jobsInvolved = []

      if (!shiftKeyRef.current.isPressed) {
        jobsInvolved = [currentJob]
      } else {
        const startIndexJob = allJobs.findIndex(
          ({id}) => id === shiftKeyRef.current.startJob.id,
        )
        const indexCurrentJob = allJobs.findIndex(
          ({id}) => id === currentJob.id,
        )

        const start =
          startIndexJob < indexCurrentJob ? startIndexJob : indexCurrentJob
        const end =
          startIndexJob < indexCurrentJob ? indexCurrentJob : startIndexJob

        jobsInvolved = allJobs.filter(
          (item, index) => index >= start && index <= end,
        )
      }

      setJobsBulk((prevState) => {
        const isJobsInvolvedChecked = jobsInvolved.every(({id}) =>
          prevState.some((value) => value === id),
        )

        return jobsInvolved.reduce((acc, cur) => {
          if (shiftKeyRef.current.isPressed) {
            return isJobsInvolvedChecked
              ? acc.filter((value) => value !== cur.id)
              : !acc.some((value) => value === cur.id)
                ? [...acc, cur.id]
                : acc
          }
          return acc.some((value) => value === cur.id)
            ? acc.filter((value) => value !== cur.id)
            : [...acc, cur.id]
        }, prevState)
      })

      shiftKeyRef.current.startJob = currentJob
    },
    [allJobs],
  )

  const onCheckedProject = useCallback(
    (projectId) => {
      const currentProject = projects.find(({id}) => id === projectId)

      if (shiftKeyRef.current.isPressed) {
        const lastJobIdProject =
          currentProject.jobs[currentProject.jobs.length - 1].id

        onCheckedJob(
          shiftKeyRef.current.startJob.id > lastJobIdProject
            ? lastJobIdProject
            : currentProject.jobs[0].id,
        )
      } else {
        setJobsBulk((prevState) => {
          const jobsBulkForCurrentProject = currentProject.jobs.filter(({id}) =>
            prevState.some((value) => value === id),
          )

          const isCheckedAllJobs =
            jobsBulkForCurrentProject.length === currentProject.jobs.length

          return isCheckedAllJobs
            ? prevState.filter(
                (value) =>
                  !jobsBulkForCurrentProject.some(({id}) => id === value),
              )
            : [
                ...prevState.filter(
                  (value) =>
                    !jobsBulkForCurrentProject.some(({id}) => id === value),
                ),
                ...currentProject.jobs.map(({id}) => id),
              ]
        })
      }

      shiftKeyRef.current.startJob = currentProject.jobs[0]
    },
    [projects, onCheckedJob],
  )

  useEvent(document, 'keydown', ({key}) => {
    shiftKeyRef.current.isPressed = key === 'Shift'
  })
  useEvent(document, 'keyup', () => {
    shiftKeyRef.current.isPressed = false
  })

  const selectAll = () => {
    const all = projects.reduce(
      (acc, {jobs}) => [
        ...acc,
        ...(acc.length < MAX_JOBS_SELECTABLE ? jobs.map(({id}) => id) : []),
      ],
      [],
    )
    setJobsBulk(all)
  }

  const clearAll = () => setJobsBulk([])

  const openModal = (props) =>
    ModalsActions.showModalComponent(props.component, props, props.title)

  const openConfirmModal = (props) =>
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      {
        text: props.text,
        successText: 'Continue',
        cancelText: 'Cancel',
        successCallback: props.successCallback,
      },
      'Confirmation required',
    )

  const onClickAction = ({id, label}) => {
    const modalComponent =
      id === JOBS_ACTIONS.CHANGE_PASSWORD.id
        ? BulkChangePassword
        : id == JOBS_ACTIONS.ASSIGN_TO_TEAM.id
          ? BulkMoveToTeam
          : BulkAssignToMember

    const jobsSelected = allJobs.filter(({id}) =>
      jobsBulk.some((value) => value === id),
    )

    switch (id) {
      case JOBS_ACTIONS.DELETE_PERMANENTLY.id:
        openConfirmModal({
          action: id,
          text: `You are about to delete ${jobsBulk.length} jobs permanently, this action cannot be undone. Are you sure you want to proceed?`,
          successCallback: () => submit({id}),
        })
        break
      case JOBS_ACTIONS.CHANGE_PASSWORD.id:
      case JOBS_ACTIONS.ASSIGN_TO_TEAM.id:
      case JOBS_ACTIONS.ASSIGN_TO_MEMBER.id:
        openModal({
          title: label,
          component: modalComponent,
          jobs: jobsSelected,
          projects: projectsSelected,
          ...((JOBS_ACTIONS.ASSIGN_TO_TEAM ||
            JOBS_ACTIONS.ASSIGN_TO_MEMBER) && {teams}),
          successCallback: (props) => {
            submit({id, ...props})
            ModalsActions.onCloseModal()
          },
        })
        break
      default:
        submit({id})
    }
  }

  const submit = ({id, ...rest}) => {
    const jobs = allJobs.filter(({id}) =>
      jobsBulk.some((value) => value === id),
    )

    let promises

    switch (id) {
      case JOBS_ACTIONS.ARCHIVE.id:
      case JOBS_ACTIONS.UNARCHIVE.id:
      case JOBS_ACTIONS.CANCEL.id:
      case JOBS_ACTIONS.RESUME.id:
      case JOBS_ACTIONS.DELETE_PERMANENTLY.id:
        jobs.forEach((job) => {
          const project = projects.find((project) =>
            project.jobs.some((jobItem) => jobItem.id === job.id),
          )
          ManageActions.changeJobStatus(
            fromJS(project),
            fromJS(job),
            id === JOBS_ACTIONS.UNARCHIVE.id || id === JOBS_ACTIONS.RESUME.id
              ? JOBS_ACTIONS.ACTIVE.id
              : id,
          )
        })
        break
      case JOBS_ACTIONS.GENERATE_REVISE_2.id:
        jobs.forEach((job) => {
          const {id: idProject, password: passwordProject} = projects.find(
            (project) => project.jobs.some((jobItem) => jobItem.id === job.id),
          )
          const wasAlreadyGenerated2Pass =
            job.revise_passwords && job.revise_passwords.length > 1

          if (!wasAlreadyGenerated2Pass)
            ManageActions.getSecondPassReview(
              idProject,
              passwordProject,
              job.id,
              job.password,
            )
        })
        break
      case JOBS_ACTIONS.CHANGE_PASSWORD.id:
        promises = jobs.map((job) => {
          return changeJobPassword(job, job.password, rest.revision_number)
        })

        Promise.allSettled(promises).then((result) => {
          const fulfilledPromises = result
            .filter(({status}) => status === 'fulfilled')
            .map(({value}) => value)

          fulfilledPromises.forEach((value) => {
            const job = allJobs.find(({id}) => id === parseInt(value.id))

            const project = projects.find((project) =>
              project.jobs.some((jobItem) => jobItem.id === job.id),
            )

            ManageActions.changeJobPassword(
              fromJS(project),
              fromJS(job),
              value.new_pwd,
              value.old_pwd,
              rest.revision_number,
            )
          })

          if (fulfilledPromises.length) {
            const notification = {
              title: 'Change jobs password',
              text: 'Selected jobs password has been changed',
              type: 'warning',
              position: 'bl',
              allowHtml: true,
              timer: 10000,
            }
            CatToolActions.addNotification(notification)
          } else if (fulfilledPromises.length < result.length) {
            const errorNotification = {
              title: 'Error change jobs password',
              text: 'Some jobs failed',
              type: 'error',
              position: 'bl',
              allowHtml: true,
              timer: 10000,
            }
            CatToolActions.addNotification(errorNotification)
          }
        })
        break
      case JOBS_ACTIONS.ASSIGN_TO_TEAM.id:
        ManageActions.changeProjectsTeamBulk(rest.id_team, projectsSelected)
        break

      case JOBS_ACTIONS.ASSIGN_TO_MEMBER.id:
        ManageActions.changeProjectAssigneeBulk(
          rest.id_assignee,
          projectsSelected,
          teams,
        )
        break
    }
  }

  const buttonProps = {
    mode: BUTTON_MODE.LINK,
    size: BUTTON_SIZE.LINK_SMALL,
    tooltip: 'Tooltip info',
  }
  const buttonActionsProps = {disabled: !jobsBulk.length}

  const actions = (
    <>
      {ACTIONS_BY_FILTER[filterStatusApplied].map((action, index) => (
        <div key={index}>
          <Button
            {...{
              ...buttonProps,
              ...buttonActionsProps,
              ...(action.id === JOBS_ACTIONS.ASSIGN_TO_TEAM.id && {
                disabled: !jobsBulk.length || !isSelectedAllJobsByProjects,
              }),
              ...(action.id === JOBS_ACTIONS.ASSIGN_TO_MEMBER.id && {
                disabled:
                  !jobsBulk.length ||
                  !isSelectedAllJobsByProjects ||
                  projectsSelected.some(
                    (project) =>
                      teams.find(({id}) => id === project.id_team).type ===
                      'personal',
                  ) ||
                  !projectsSelected.every(
                    ({id_team}) => id_team === projectsSelected[0].id_team,
                  ),
              }),
              onClick: () => onClickAction(action),
            }}
          >
            {action.label}
          </Button>
        </div>
      ))}
    </>
  )

  return (
    <ProjectsBulkActionsContext.Provider
      value={{jobsBulk, onCheckedProject, onCheckedJob}}
    >
      <div className="project-bulk-actions-background">
        <div className="project-bulk-actions-container">
          <div className="project-bulk-actions-buttons">
            <span>
              <span>{jobsBulk.length}</span> entries selected
            </span>
            {actions}
          </div>
          <div>
            <Button
              {...buttonProps}
              onClick={selectAll}
              disabled={!projects.length}
            >
              Select all projects
            </Button>
            <Button
              {...buttonProps}
              onClick={clearAll}
              disabled={!projects.length}
            >
              Clear all
            </Button>
          </div>
        </div>
      </div>
      {children}
    </ProjectsBulkActionsContext.Provider>
  )
}

ProjectsBulkActions.propTypes = {
  projects: PropTypes.array.isRequired,
  teams: PropTypes.array.isRequired,
  children: PropTypes.node,
}
