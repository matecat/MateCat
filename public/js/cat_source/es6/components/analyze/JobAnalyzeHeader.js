import React from 'react'
import {UNIT_COUNT} from '../../constants/Constants'
import ChevronRight from '../../../../../img/icons/ChevronRight'

const JobAnalyzeHeader = ({jobInfo}) => {
  const totalWeighted = jobInfo.total_equivalent
  return (
    <div className="job-analyze-header">
      <div className="job-analyze-header_left">
        <div>
          <span>ID: {jobInfo.id}</span>
        </div>
        <div className="job-analyze-languages">
          <span>{jobInfo.source_name}</span>
          <ChevronRight size={16} />
          <span>{jobInfo.target_name}</span>
        </div>
      </div>
      <div className="job-analyze-header_right">
        <span>{parseInt(totalWeighted)}</span>
        <span>
          {jobInfo.count_unit === UNIT_COUNT.WORDS
            ? ' Matecat Weighted words'
            : ' Matecat weighted characters'}
        </span>
      </div>
    </div>
  )
}

export default JobAnalyzeHeader
