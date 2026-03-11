import PropTypes from 'prop-types'
import React from 'react'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'

export const JobContainer = ({
  jobsLength,
  job,
  project,
  isChunk,
  isChecked,
  onCheckedJob,
  index,
}) => {
  const idJobLabel = !isChunk ? job.get('id') : job.get('id') + '-' + index

  return (
    <div className="job-container">
      <Checkbox
        onChange={() => onCheckedJob(job.get('id'))}
        value={isChecked ? CHECKBOX_STATE.CHECKED : CHECKBOX_STATE.UNCHECKED}
      />
      <div>
        <div className="job-id" title="Job Id">
          <span>source - target</span>
          ID: {idJobLabel}
        </div>
      </div>
      <div>---------progressbar</div>
      <div>Words:</div>
      <div>Icons</div>
      <div>Assign</div>
      <div>Buy translation</div>
      <div>Open</div>
      <div>|</div>
    </div>
  )
}

JobContainer.propTypes = {
  jobsLength: PropTypes.number.isRequired,
  job: PropTypes.object.isRequired,
  project: PropTypes.object.isRequired,
  isChunk: PropTypes.bool.isRequired,
  isChecked: PropTypes.bool.isRequired,
  onCheckedJob: PropTypes.func.isRequired,
  index: PropTypes.number.isRequired,
}
