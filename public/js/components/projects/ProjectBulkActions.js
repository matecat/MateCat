import React, {useCallback, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {ProjectBulkActionsContext} from './ProjectBulkActionsContext'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import useEvent from '../../hooks/useEvent'

const MAX_JOBS_SELECTABLE = 100

export const ProjectBulkActions = ({projects, children}) => {
  const [jobsBulk, setJobsBulk] = useState([])

  const shiftKeyRef = useRef({
    isPressed: false,
    startJob: undefined,
  })

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
      const jobs = projects.reduce((acc, cur) => [...acc, ...cur.jobs], [])
      const currentJob = jobs.find(({id}) => id === jobId)

      let jobsInvolved = []

      if (!shiftKeyRef.current.isPressed) {
        jobsInvolved = [currentJob]
      } else {
        const startIndexJob = jobs.findIndex(
          ({id}) => id === shiftKeyRef.current.startJob.id,
        )
        const indexCurrentJob = jobs.findIndex(({id}) => id === currentJob.id)

        const start =
          startIndexJob < indexCurrentJob ? startIndexJob : indexCurrentJob
        const end =
          startIndexJob < indexCurrentJob ? indexCurrentJob : startIndexJob

        jobsInvolved = jobs.filter(
          (item, index) => index >= start && index <= end,
        )
      }
      console.log(jobsInvolved)
      console.log(
        'shiftKeyRef.current.isPressed',
        shiftKeyRef.current.isPressed,
        'shiftKeyRef.current.startJob?.id',
        shiftKeyRef.current.startJob?.id,
        'currentJob.id',
        currentJob.id,
      )
      console.log('<--------------<')

      setJobsBulk((prevState) => {
        const isJobsInvolvedChecked = jobsInvolved.every(({id}) =>
          prevState.some((value) => value === id),
        )
        console.log('isJobsInvolvedChecked', isJobsInvolvedChecked)

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
    [projects],
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

  const buttonProps = {mode: BUTTON_MODE.LINK, size: BUTTON_SIZE.LINK_SMALL}
  const buttonActionsProps = {disabled: !jobsBulk.length}

  const clearAll = () => setJobsBulk([])

  const actions = (
    <>
      <div>
        <Button {...{...buttonProps, ...buttonActionsProps}}>Archive</Button>
      </div>
      <div>
        <Button {...{...buttonProps, ...buttonActionsProps}}>Cancel</Button>
      </div>
      <div>
        <Button {...{...buttonProps, ...buttonActionsProps}}>
          Change password
        </Button>
      </div>
      <div>
        <Button {...{...buttonProps, ...buttonActionsProps}}>
          Generate revise 2 link
        </Button>
      </div>
      <div>
        <Button {...{...buttonProps, ...buttonActionsProps}}>
          Move to team
        </Button>
      </div>
    </>
  )

  return (
    <ProjectBulkActionsContext.Provider
      value={{jobsBulk, setJobsBulk, onCheckedProject, onCheckedJob}}
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
    </ProjectBulkActionsContext.Provider>
  )
}

ProjectBulkActions.propTypes = {
  projects: PropTypes.array.isRequired,
  children: PropTypes.node,
}
