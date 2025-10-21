import React, {useCallback, useState} from 'react'
import PropTypes from 'prop-types'
import {ProjectBulkActionsContext} from './ProjectBulkActionsContext'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'

const MAX_JOBS_TO_SELECT = 100

export const ProjectBulkActions = ({projects, children}) => {
  const [jobsBulk, setJobsBulk] = useState([])

  const onCheckedJob = useCallback(
    (jobId) =>
      setJobsBulk((prevState) =>
        prevState.some((currentJobId) => currentJobId === jobId)
          ? prevState.filter((currentJobId) => currentJobId !== jobId)
          : [...prevState, jobId],
      ),
    [],
  )

  const selectAll = () => {
    const all = projects.reduce(
      (acc, {jobs}) => [
        ...acc,
        ...(acc.length < MAX_JOBS_TO_SELECT ? jobs.map(({id}) => id) : []),
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
      value={{jobsBulk, setJobsBulk, onCheckedJob}}
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
