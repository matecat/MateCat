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
import {jobsBulkActions} from '../../../api/jobsBulkActions/jobsBulkActions'
import CatToolActions from '../../../actions/CatToolActions'
import ManageActions from '../../../actions/ManageActions'
import {fromJS} from 'immutable'

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

    ProjectsStore.addListener(
      ManageConstants.FILTER_PROJECTS,
      onChangeProjectStatus,
    )

    return () =>
      ProjectsStore.removeListener(
        ManageConstants.FILTER_PROJECTS,
        onChangeProjectStatus,
      )
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
        : BulkMoveToTeam

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
        openModal({
          title: `Bulk ${label}`,
          component: modalComponent,
          jobs: allJobs.filter(({id}) =>
            jobsBulk.some((value) => value === id),
          ),
          ...(JOBS_ACTIONS.ASSIGN_TO_TEAM && {teams}),
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
    const jobs = allJobs
      .filter(({id}) => jobsBulk.some((value) => value === id))
      .map(({id, password}) => ({id, password}))

    jobsBulkActions({jobs, action: id, ...rest})
      .then((data) => onSubmitSuccessful({id, jobs, requestData: rest, data}))
      .catch(() => {
        CatToolActions.addNotification({
          title: 'Error bulk operation',
          type: 'error',
          text: 'There was an error. Please retry!',
          position: 'br',
          allowHtml: true,
          timer: 5000,
        })
      })
  }

  const onSubmitSuccessful = ({id, jobs, requestData, data}) => {
    const generateRevise2Collection = jobs.map((job) => {
      const {id: idProject, password: passwordProject} = projects.find(
        (project) => project.jobs.some((jobItem) => jobItem.id === job.id),
      )

      const {secondPassPassword} =
        data.jobs.find((jobResponse) => jobResponse.id === job.id)?.outcome ??
        {}

      return {idProject, passwordProject, job, secondPassPassword}
    })

    switch (id) {
      case JOBS_ACTIONS.ARCHIVE.id:
      case JOBS_ACTIONS.UNARCHIVE.id:
      case JOBS_ACTIONS.CANCEL.id:
      case JOBS_ACTIONS.RESUME.id:
      case JOBS_ACTIONS.DELETE_PERMANENTLY.id:
        ManageActions.changeJobsStatusBulk(projects, jobs)
        break
      case JOBS_ACTIONS.GENERATE_REVISE_2.id:
        ManageActions.getSecondPassReviewBulk(generateRevise2Collection)
        break
      case JOBS_ACTIONS.CHANGE_PASSWORD.id:
        jobs.forEach((job) => {
          const project = projects.find((project) =>
            project.jobs.some((jobItem) => jobItem.id === job.id),
          )

          ManageActions.changeJobPassword(
            fromJS(project),
            fromJS(job),
            data.jobs.find((jobResponse) => jobResponse.id === job.id)?.outcome
              ?.newPassword,
            job.password,
            requestData.revision_number,
          )
        })
        break
    }
  }

  const buttonProps = {mode: BUTTON_MODE.LINK, size: BUTTON_SIZE.LINK_SMALL}
  const buttonActionsProps = {disabled: !jobsBulk.length}

  const actions = (
    <>
      {ACTIONS_BY_FILTER[filterStatusApplied].map((action, index) => (
        <div key={index}>
          <Button
            {...{
              ...buttonProps,
              ...buttonActionsProps,
              ...((action.id === JOBS_ACTIONS.ASSIGN_TO_TEAM.id ||
                action.id === JOBS_ACTIONS.ASSIGN_TO_MEMBER.id) && {
                disabled: !jobsBulk.length || !isSelectedAllJobsByProjects,
                tooltip: 'we wewe we',
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
