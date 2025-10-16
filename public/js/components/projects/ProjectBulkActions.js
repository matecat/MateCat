import React, {useCallback, useState} from 'react'
import PropTypes from 'prop-types'
import {ProjectBulkActionsContext} from './ProjectBulkActionsContext'

export const ProjectBulkActions = ({children}) => {
  const [jobsBulk, setJobsBulk] = useState([])
  console.log('jobsBulk', jobsBulk)
  const onCheckedJob = useCallback(
    (jobId) =>
      setJobsBulk((prevState) =>
        prevState.some((currentJobId) => currentJobId === jobId)
          ? prevState.filter((currentJobId) => currentJobId !== jobId)
          : [...prevState, jobId],
      ),
    [],
  )

  return (
    <ProjectBulkActionsContext.Provider value={{jobsBulk, onCheckedJob}}>
      <div>ProjectBulkActions</div>
      {children}
    </ProjectBulkActionsContext.Provider>
  )
}

ProjectBulkActions.propTypes = {
  children: PropTypes.node,
}
