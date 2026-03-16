import PropTypes from 'prop-types'
import React from 'react'
import {JobContainer} from './JobContainer'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'

export const ChunksJobContainer = ({jobs, ...props}) => {
  return (
    <div className="chunks-job-container">
      <div className="chunks-job-container-line">
        <Checkbox
          onChange={() => props.onCheckedJob(jobs[0].get('id'))}
          value={
            props.isChecked ? CHECKBOX_STATE.CHECKED : CHECKBOX_STATE.UNCHECKED
          }
        />
        <span>source - target</span>
      </div>
      <div className="chunks-job-container-list">
        {jobs.map((job, index) => (
          <JobContainer
            key={`${job.get('id')}-${index + 1}`}
            job={job}
            index={index + 1}
            {...props}
          />
        ))}
      </div>
    </div>
  )
}

ChunksJobContainer.propTypes = {
  jobs: PropTypes.object.isRequired,
}
