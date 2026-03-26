import React, {useRef} from 'react'
import Tooltip from './Tooltip'
import {isUndefined} from 'lodash'

const JobProgressBar = ({stats = {}}) => {
  const approved2ndPassTooltip = useRef()
  const approvedTooltip = useRef()
  const translatedTooltip = useRef()

  const {raw} = stats

  const newWords = raw ? raw.new : undefined

  const {total, draft, translated, approved, approved2} = raw || {}

  const translatedPerc = (translated * 100) / total
  const approvedPerc = (approved * 100) / total
  const approved2Perc = (approved2 * 100) / total

  const translatedPercBar = (translated * 100) / total
  const approvedPercBar = ((translated + approved) * 100) / total
  const approved2PercBar = ((translated + approved + approved2) * 100) / total

  const totalPerc = Math.round(((total - draft - newWords) * 100) / total)

  const analysisComplete = !isUndefined(stats.analysis_complete)
    ? stats.analysis_complete
    : true

  return (
    <div className="job-progress-container" data-testid="progress-bar">
      <div className="job-progress-bar">
        {(stats || !analysisComplete) && (
          <>
            <Tooltip content={'Translated ' + translatedPerc.toFixed(1) + '%'}>
              <span
                className="bar translated-bar"
                style={{
                  width: translatedPercBar + '%',
                }}
                ref={translatedTooltip}
              />
            </Tooltip>
            <Tooltip content={'Approved ' + approvedPerc.toFixed(1) + '%'}>
              <span
                className="bar approved-bar"
                style={{width: approvedPercBar + '%'}}
                ref={approvedTooltip}
              />
            </Tooltip>
            <Tooltip content={'Approved ' + approved2Perc.toFixed(1) + '%'}>
              <span
                className="bar approved-bar-2nd-pass"
                style={{width: approved2PercBar + '%'}}
                ref={approved2ndPassTooltip}
              />
            </Tooltip>
          </>
        )}
      </div>
      <span data-testid="progress-bar-amount">
        {!isNaN(totalPerc) && isFinite(totalPerc) ? `${totalPerc}%` : '-'}
      </span>
    </div>
  )
}

export default JobProgressBar
