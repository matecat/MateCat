import React from 'react'
import PropTypes from 'prop-types'
import {ANALYSIS_PHASE} from '../../utils/deriveAnalysisView'
import {formatCount} from '../../utils/buckets'
import styles from './AnalysisStatusCard.module.scss'

const STEPS = [
  {phase: ANALYSIS_PHASE.COUNTING, label: 'Word count'},
  {phase: ANALYSIS_PHASE.QUEUE, label: 'Queue'},
  {phase: ANALYSIS_PHASE.ANALYZING, label: 'Analysis'},
  {phase: ANALYSIS_PHASE.DONE, label: 'Complete'},
]

const getMessage = (phase, progress) => {
  if (phase === ANALYSIS_PHASE.COUNTING) {
    return {title: 'Fast word counting…'}
  }
  if (phase === ANALYSIS_PHASE.QUEUE) {
    const inQueue = progress.inQueueBefore
    return {
      title: 'There are other projects in queue',
      detail:
        inQueue > 0
          ? `${formatCount(inQueue)} ${inQueue === 1 ? 'segment' : 'segments'} in queue`
          : null,
    }
  }
  return {
    title: 'Searching for TM matches',
    detail: `${formatCount(progress.segmentsAnalyzed)} of ${formatCount(
      progress.totalSegments,
    )} segments · ${progress.percent ?? 0}%`,
  }
}

/**
 * Where the analysis is while it runs: counting, in queue or analyzing.
 * Renders nothing in any other phase.
 */
export const AnalysisStatusCard = ({phase, progress = {}}) => {
  const currentStep = STEPS.findIndex((step) => step.phase === phase)
  if (currentStep < 0 || phase === ANALYSIS_PHASE.DONE) return null

  const {title, detail} = getMessage(phase, progress)

  return (
    <section className={styles.card} aria-label="Analysis status">
      <ol className={styles.steps}>
        {STEPS.map((step, index) => (
          <li
            key={step.phase}
            className={
              index === currentStep
                ? styles.current
                : index < currentStep
                  ? styles.done
                  : undefined
            }
            aria-current={index === currentStep ? 'step' : undefined}
          >
            {step.label}
          </li>
        ))}
      </ol>
      <p className={styles.title} role="status">
        {title}
      </p>
      {detail && <p className={styles.detail}>{detail}</p>}
      {phase === ANALYSIS_PHASE.ANALYZING && (
        <div
          className={styles.progress}
          role="progressbar"
          aria-label="Analysis progress"
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={progress.percent ?? 0}
        >
          <div
            className={styles.progressValue}
            style={{width: `${progress.percent ?? 0}%`}}
          />
        </div>
      )}
    </section>
  )
}

AnalysisStatusCard.propTypes = {
  phase: PropTypes.oneOf(Object.values(ANALYSIS_PHASE)).isRequired,
  progress: PropTypes.shape({
    segmentsAnalyzed: PropTypes.number,
    totalSegments: PropTypes.number,
    percent: PropTypes.number,
    inQueueBefore: PropTypes.number,
  }),
}
