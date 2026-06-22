import React, {useRef} from 'react'
import Tooltip from './Tooltip'
import {isUndefined} from 'lodash'
import styles from './JobProgressBar.module.scss'

const JobProgressBar = ({stats = {}, showPercent = true}) => {
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
    <div className={styles.container} data-testid="progress-bar">
      <Tooltip
        content={
          (unconfirmedPerc > 0 ||
            translatedPerc > 0 ||
            approvedPerc > 0 ||
            approved2Perc > 0) && (
            <div className={styles.tooltip}>
              {unconfirmedPerc > 0 && (
                <div>
                  <span>
                    <span className={`${styles.quad} ${styles['unconfirmed-quad']}`} />
                    Unconfirmed
                  </span>
                  <span>{unconfirmedPerc.toFixed(1)}%</span>
                </div>
              )}
              {translatedPerc > 0 && (
                <div>
                  <span>
                    <span className={`${styles.quad} ${styles['translated-quad']}`} />
                    Translated
                  </span>
                  <span>{translatedPerc.toFixed(1)}%</span>
                </div>
              )}
              {approvedPerc > 0 && (
                <div>
                  <span>
                    <span className={`${styles.quad} ${styles['approved-quad']}`} />
                    Revise
                  </span>
                  <span>{approvedPerc.toFixed(1)}%</span>
                </div>
              )}
              {approved2Perc > 0 && (
                <div>
                  <span>
                    <span className={`${styles.quad} ${styles['approved2-quad']}`} />
                    Revise 2
                  </span>
                  <span>{approved2Perc.toFixed(1)}%</span>
                </div>
              )}
            </div>
          )
        }
      >
        <div className={styles['bar-wrapper']} ref={progressTooltipRef}>
          {(stats || !analysisComplete) && (
            <>
              <span
                className={`${styles.bar} ${styles['translated-bar']}`}
                style={{
                  width: translatedPercBar + '%',
                }}
              />
              <span
                className={`${styles.bar} ${styles['approved-bar']}`}
                style={{width: approvedPercBar + '%'}}
              />
              <span
                className={`${styles.bar} ${styles['approved-bar-2nd-pass']}`}
                style={{width: approved2PercBar + '%'}}
              />
            </>
          )}
        </div>
      </Tooltip>

      {showPercent && (
        <span data-testid="progress-bar-amount">
          {!isNaN(totalPerc) && isFinite(totalPerc) ? `${totalPerc}%` : '-'}
        </span>
      )}
    </div>
  )
}

export default JobProgressBar
