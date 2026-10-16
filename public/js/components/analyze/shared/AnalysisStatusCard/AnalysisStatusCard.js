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

// Copy from the analysis mockup (Main.dc.html, PH)
const getMessage = (phase, progress) => {
  if (phase === ANALYSIS_PHASE.COUNTING) {
    return {
      title: 'Counting the words in your files',
      text: 'The language combinations are listed below. Word counts appear as soon as the count is done, usually in a few seconds.',
    }
  }
  if (phase === ANALYSIS_PHASE.QUEUE) {
    const inQueue = progress.inQueueBefore
    return {
      title: 'There are other projects in queue',
      figure:
        inQueue > 0
          ? `${formatCount(inQueue)} ${inQueue === 1 ? 'segment' : 'segments'} in queue`
          : null,
      text: 'Other projects are being analyzed first. Total word counts are ready; weighted counts and the breakdown follow the analysis.',
    }
  }
  return {
    title: 'Searching for TM matches',
    figure: `${formatCount(progress.segmentsAnalyzed)} of ${formatCount(
      progress.totalSegments,
    )} segments · ${progress.percent ?? 0}%`,
    text: 'Weighted word counts and the saving update as segments are analyzed. The breakdown by bucket appears when the analysis is complete.',
  }
}

/**
 * Where the analysis is while it runs: counting, in queue or analyzing.
 * Renders nothing in any other phase.
 */
export const AnalysisStatusCard = ({phase, progress = {}}) => {
  const currentStep = STEPS.findIndex((step) => step.phase === phase)
  if (currentStep < 0 || phase === ANALYSIS_PHASE.DONE) return null

  const {title, figure, text} = getMessage(phase, progress)
  const percent = progress.percent ?? 0

  return (
    <section className={styles.card} role="status" aria-label="Analysis status">
      <div className={styles.header}>
        <h2 className={styles.title}>{title}</h2>
        {figure && <span className={styles.figure}>{figure}</span>}
      </div>
      <p className={styles.text}>{text}</p>
      {phase === ANALYSIS_PHASE.ANALYZING && (
        <div
          className={styles.progress}
          role="progressbar"
          aria-label="Segments analyzed"
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={percent}
        >
          <div
            className={styles.progressValue}
            style={{width: `${Math.max(1, percent)}%`}}
          />
        </div>
      )}
      <ol className={styles.steps}>
        {STEPS.map((step, index) => (
          <li
            key={step.phase}
            className={
              index === currentStep
                ? styles.current
                : index < currentStep
                  ? styles.done
                  : styles.next
            }
            aria-current={index === currentStep ? 'step' : undefined}
          >
            {index < currentStep ? `✓ ${step.label}` : step.label}
          </li>
        ))}
      </ol>
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
