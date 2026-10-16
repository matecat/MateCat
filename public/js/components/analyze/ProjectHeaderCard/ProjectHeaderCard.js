import React from 'react'
import PropTypes from 'prop-types'
import {downloadAnalysisReport} from '../../../api/downloadAnalysisReport'
import {Badge, BADGE_TYPE} from '../../common/Badge'
import Download from '../../../../img/icons/Download'
import {ANALYSIS_PHASE} from '../utils/deriveAnalysisView'
import {SavingCard} from '../shared/SavingCard'
import {getSavedPercent} from '../utils/buckets'
import styles from './ProjectHeaderCard.module.scss'

const WORDS_PER_DAY = 3000

/**
 * "N work days at 3,000 w/day": the weighted words translated at 3,000 a
 * day, in hours (8 a day) or minutes when under a day or an hour.
 */
export const getWorkLabel = (weightedWords = 0) => {
  let time = weightedWords / WORDS_PER_DAY
  let unit = 'day'
  if (time > 0 && time < 1) {
    time = time * 8
    unit = 'hour'
  }
  if (time > 0 && time < 1) {
    time = time * 60
    unit = 'minute'
  }
  const rounded = Math.round(time)
  return `${rounded} work ${unit}${rounded === 1 ? '' : 's'} at ${WORDS_PER_DAY.toLocaleString('en-US')} w/day`
}

const PHASE_STATUS = {
  [ANALYSIS_PHASE.DONE]: {label: 'Complete', type: BADGE_TYPE.GREEN},
  [ANALYSIS_PHASE.COUNTING]: {label: 'Counting words', type: BADGE_TYPE.GREY},
  [ANALYSIS_PHASE.QUEUE]: {label: 'In queue', type: BADGE_TYPE.YELLOW},
  [ANALYSIS_PHASE.ANALYZING]: {label: 'Analyzing', type: BADGE_TYPE.BLUE},
  [ANALYSIS_PHASE.NOT_ANALYZED]: {label: 'Not analyzed', type: BADGE_TYPE.RED},
  [ANALYSIS_PHASE.EMPTY]: {label: 'No text found', type: BADGE_TYPE.RED},
}

const FAILED_STATUS = {label: 'Failed', type: BADGE_TYPE.RED}

// The analysis status pill
export const PhaseBadge = ({phase}) => {
  const status = PHASE_STATUS[phase]
  return status ? <Badge type={status.type}>{status.label}</Badge> : null
}

PhaseBadge.propTypes = {
  phase: PropTypes.oneOf(Object.values(ANALYSIS_PHASE)).isRequired,
}

const SupportContact = () =>
  config.support_mail?.includes('@') ? (
    <a href={`mailto:${config.support_mail}`}>{config.support_mail}</a>
  ) : (
    config.support_mail
  )

/**
 * Project name, language count, analysis status and report download, with the
 * saving card next to them.
 */
export const ProjectHeaderCard = ({
  projectId,
  projectName,
  projectPassword,
  languageCount,
  phase,
  totals,
}) => {
  // The daemon is down: an analysis still waiting for it will never start
  const failed =
    !!config.daemon_warning &&
    [ANALYSIS_PHASE.COUNTING, ANALYSIS_PHASE.QUEUE].includes(phase)
  const status = failed ? FAILED_STATUS : PHASE_STATUS[phase]
  const done = phase === ANALYSIS_PHASE.DONE
  const live = phase === ANALYSIS_PHASE.ANALYZING

  // Once done the server's own discount; while analyzing the live ratio
  const savingPercent =
    done && totals.discount > 0
      ? totals.discount
      : (getSavedPercent(totals.equivalent, totals.raw) ?? 0)

  const handleDownloadReport = () => {
    downloadAnalysisReport({
      idProject: projectId,
      password: projectPassword,
    }).catch((error) => {
      console.error('Error downloading analysis report:', error)
    })
  }

  return (
    <div className={styles.row}>
      <section className={styles.card} aria-label="Project">
        <div className={styles.titleRow}>
          <span className={styles.id}>{`ID ${projectId}`}</span>
          <h2 className={styles.name}>{projectName}</h2>
          {languageCount > 1 && (
            <span className={styles.languages}>
              {`${languageCount} language combinations`}
            </span>
          )}
        </div>
        <div className={styles.statusRow}>
          <span className={styles.statusLabel}>Analysis status:</span>
          {status && <Badge type={status.type}>{status.label}</Badge>}
          {done && (
            <button
              type="button"
              className={styles.report}
              onClick={handleDownloadReport}
            >
              Download analysis report
              <Download size={16} />
            </button>
          )}
        </div>
        {failed && (
          <p className={styles.error}>
            The analysis seems not to be running. Contact <SupportContact />.
          </p>
        )}
      </section>
      <SavingCard
        savingPercent={savingPercent}
        workMinutesLabel={getWorkLabel(totals.equivalent)}
        known={done || live}
        live={live}
        unavailable={[
          ANALYSIS_PHASE.NOT_ANALYZED,
          ANALYSIS_PHASE.EMPTY,
        ].includes(phase)}
      />
    </div>
  )
}

ProjectHeaderCard.propTypes = {
  projectId: PropTypes.oneOfType([PropTypes.number, PropTypes.string])
    .isRequired,
  projectName: PropTypes.string,
  projectPassword: PropTypes.string.isRequired,
  languageCount: PropTypes.number.isRequired,
  phase: PropTypes.oneOf(Object.values(ANALYSIS_PHASE)).isRequired,
  totals: PropTypes.shape({
    raw: PropTypes.number,
    equivalent: PropTypes.number,
    discount: PropTypes.number,
  }).isRequired,
}
