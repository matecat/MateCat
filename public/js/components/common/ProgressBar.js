import React, {useRef, useState, useEffect} from 'react'

import PropTypes from 'prop-types'
import styles from './ProgressBar.module.scss'

export const PROGRESS_BAR_TYPE = {
  DEFAULT: 'default',
}

export const PROGRESS_BAR_SIZE = {
  SMALL: 'small',
  MEDIUM: 'medium',
  BIG: 'big',
}

export const PROGRESS_BAR_PROGRESS = {
  PERCENT: 'percent',
  DETAILED: 'detailed',
}

export const ProgressBar = ({
  type = PROGRESS_BAR_TYPE.DEFAULT,
  size = PROGRESS_BAR_SIZE.SMALL,
  total,
  progress = 0,
  showProgress = false,
  progressType = PROGRESS_BAR_PROGRESS.PERCENT,
  showWarning = false,
  label,
  className = '',
  tooltip,
}) => {
  const progressBar = useRef()

  const [internalProgress, setInternalProgress] = useState(progress)
  const progressWidth = Math.min((internalProgress * 100) / total, 100)
  const barClassName = [styles['progress-bar-container'], styles[type], styles[size], label && styles.withLabel, showWarning && styles.warning, progressWidth === 100 && styles.complete, className].filter(Boolean).join(' ')

  // EFFECTS
  useEffect(() => {
    setInternalProgress(progress)
  }, [progress])

  // RENDER
  return (
    <div
      className={barClassName}
      ref={progressBar}
      aria-label={tooltip || null}
      // eslint-disable-next-line react/no-unknown-property
      tooltip-position="bottom"
      data-testid="progress-bar"
    >
      {label && (
        <div className={styles.label}>
          {label}
          {showProgress && (
            <div className={styles.labelProgress}>
              {progressType === PROGRESS_BAR_PROGRESS.PERCENT ? (
                `${Math.floor(progressWidth)}%`
              ) : progress === 0 ? (
                <>{total}</>
              ) : progress === total ? (
                <span>{progress}</span>
              ) : (
                <>
                  <span>{progress}</span> / {total}
                </>
              )}
            </div>
          )}
        </div>
      )}
      {label &&
      showProgress &&
      progressType !== PROGRESS_BAR_PROGRESS.PERCENT &&
      progress === 0 ? null : (
        <div className={[styles['progress-wrapper'], styles[size]].join(' ')}>
          <div
            className={[styles.progress, styles[size]].join(' ')}
            style={{width: `${progressWidth}%`}}
          ></div>
        </div>
      )}
      {!label && showProgress && (
        <div className={styles.labelProgress}>
          {progressType === PROGRESS_BAR_PROGRESS.PERCENT ? (
            `${Math.floor(progressWidth)}%`
          ) : progress === 0 ? (
            <>{total}</>
          ) : progress === total ? (
            <span>{progress}</span>
          ) : (
            <>
              <span>{progress}</span> / {total}
            </>
          )}
        </div>
      )}
    </div>
  )
}

ProgressBar.propTypes = {
  type: PropTypes.oneOf([...Object.values(PROGRESS_BAR_TYPE)]),
  size: PropTypes.oneOf([...Object.values(PROGRESS_BAR_SIZE)]),
  total: PropTypes.number.isRequired,
  progress: PropTypes.number,
  showProgress: PropTypes.bool,
  progressType: PropTypes.oneOf([...Object.values(PROGRESS_BAR_PROGRESS)]),
  showWarning: PropTypes.bool,
  label: PropTypes.string,
  className: PropTypes.string,
  tooltip: PropTypes.string,
  onDragStart: PropTypes.func,
  onDragEnd: PropTypes.func,
  onChange: PropTypes.func,
}
