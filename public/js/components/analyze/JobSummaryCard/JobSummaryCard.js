import React, {useId} from 'react'
import PropTypes from 'prop-types'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../../constants/Constants'
import {formatCount, getSavedPercent} from '../utils/buckets'
import {Pending} from '../shared/Pending'
import {useValueFlash, VALUE_FLASH_CLASS} from '../shared/useValueFlash'
import styles from './JobSummaryCard.module.scss'

// "28% less than the total": how much the weighting takes off the raw count
export const getSavedLabel = (weighted, raw) => {
  const percent = getSavedPercent(weighted, raw)
  return percent === null ? '' : `${percent}% less than the total`
}

const Stat = ({label, value, note, known, live}) => {
  const flashing = useValueFlash(value, {enabled: live && known})
  return (
    <div
      className={[
        styles.stat,
        VALUE_FLASH_CLASS.base,
        flashing && VALUE_FLASH_CLASS.active,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <dt>{label}</dt>
      <dd className={styles.value}>
        <Pending known={known}>{formatCount(value)}</Pending>
      </dd>
      {known && note && <dd className={styles.note}>{note}</dd>}
    </div>
  )
}

Stat.propTypes = {
  label: PropTypes.string.isRequired,
  value: PropTypes.number,
  note: PropTypes.string,
  known: PropTypes.bool.isRequired,
  live: PropTypes.bool,
}

/**
 * The hero of a one-job page: the language pair, what it is, its actions and
 * the three totals with how much the weighting saves.
 */
export const JobSummaryCard = ({
  title,
  badge,
  subtitle,
  status,
  actions,
  totals,
  known,
  workflowType,
  countUnit = UNIT_COUNT.WORDS,
}) => {
  const titleId = useId()
  const characters = countUnit === UNIT_COUNT.CHARACTERS
  const weightedKnown = known.weighted || known.liveWeighted
  const live = known.liveWeighted

  return (
    <section className={styles.card} aria-labelledby={titleId}>
      <div className={styles.header}>
        <div className={styles.heading}>
          <div className={styles.titleRow}>
            <h2 id={titleId} className={styles.title}>
              {title}
            </h2>
            {badge && <span className={styles.badge}>{badge}</span>}
          </div>
          {subtitle && <p className={styles.subtitle}>{subtitle}</p>}
          {status}
        </div>
        {actions && <div className={styles.actions}>{actions}</div>}
      </div>
      <dl className={styles.stats}>
        <Stat
          label={characters ? 'Total character count' : 'Total word count'}
          value={totals.raw}
          note={characters ? 'Characters in the source' : 'Words in the source'}
          known={known.raw}
        />
        {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
          <Stat
            label="Industry weighted"
            value={totals.industry}
            note={getSavedLabel(totals.industry, totals.raw)}
            known={weightedKnown}
            live={live}
          />
        )}
        <Stat
          label="Matecat weighted"
          value={totals.equivalent}
          note={getSavedLabel(totals.equivalent, totals.raw)}
          known={weightedKnown}
          live={live}
        />
      </dl>
    </section>
  )
}

JobSummaryCard.propTypes = {
  // The language pair, e.g. "en-US › fr-FR"
  title: PropTypes.string.isRequired,
  // e.g. "Chunk 2 of 6"
  badge: PropTypes.string,
  subtitle: PropTypes.string,
  // Analysis status row, when the page has no project header
  status: PropTypes.node,
  actions: PropTypes.node,
  totals: PropTypes.shape({
    raw: PropTypes.number,
    industry: PropTypes.number,
    equivalent: PropTypes.number,
  }).isRequired,
  known: PropTypes.shape({
    raw: PropTypes.bool,
    weighted: PropTypes.bool,
    liveWeighted: PropTypes.bool,
  }).isRequired,
  workflowType: PropTypes.oneOf(Object.values(ANALYSIS_WORKFLOW_TYPES)),
  countUnit: PropTypes.oneOf(Object.values(UNIT_COUNT)),
}
