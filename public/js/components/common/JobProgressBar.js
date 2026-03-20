import React, {useRef} from 'react'
import Tooltip from './Tooltip'
import {isUndefined} from 'lodash'

const JobProgressBar = ({stats = {}}) => {
  const progressTooltipRef = useRef()

  const {raw} = stats

  const newWords = raw ? raw.new : undefined

  const {total, draft, new: newRaw, translated, approved, approved2} = raw || {}

  const unconfirmedPerc = ((draft + newRaw) * 100) / total
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
    <div className="job-progress-container">
      <Tooltip
        content={
          <div className="job-progress-bar-tooltip">
            <div>
              <span>
                <span className="job-progress-bar-unconfirmed-quad" />
                Unconfirmed
              </span>
              <span>{unconfirmedPerc.toFixed(1)}%</span>
            </div>
            <div>
              <span>
                <span className="job-progress-bar-translated-quad" />
                Translated
              </span>
              <span>{translatedPerc.toFixed(1)}%</span>
            </div>
            <div>
              <span>
                <span className="job-progress-bar-approved-quad" />
                Revise
              </span>
              <span>{approvedPerc.toFixed(1)}%</span>
            </div>
            <div>
              <span>
                <span className="job-progress-bar-approved2-quad" />
                Revise 2
              </span>
              <span>{approved2Perc.toFixed(1)}%</span>
            </div>
          </div>
        }
      >
        <div className="job-progress-bar" ref={progressTooltipRef}>
          {(stats || !analysisComplete) && (
            <>
              <span
                className="bar translated-bar"
                style={{
                  width: translatedPercBar + '%',
                }}
              />
              <span
                className="bar approved-bar"
                style={{width: approvedPercBar + '%'}}
              />
              <span
                className="bar approved-bar-2nd-pass"
                style={{width: approved2PercBar + '%'}}
              />
            </>
          )}
        </div>
      </Tooltip>

      {!isNaN(totalPerc) && isFinite(totalPerc) && `${totalPerc}%`}
    </div>
  )
}

export default JobProgressBar
