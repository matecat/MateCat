import React from 'react'
import {UNIT_COUNT} from '../../constants/Constants'
import ChevronRight from '../../../img/icons/ChevronRight'

const JobAnalyzeHeader = ({jobInfo}) => {
  const totalWeighted = jobInfo.total_equivalent
  return (
    <div className="job-analyze-header">
      <div className="job-analyze-header__id">ID: {jobInfo.id}</div>
      <div className="job-analyze-header__languages">
        <span>{jobInfo.source}</span>
        <ChevronRight size={16} />
        <span>{jobInfo.target}</span>
      </div>
      <div className="job-analyze-header__words">
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
