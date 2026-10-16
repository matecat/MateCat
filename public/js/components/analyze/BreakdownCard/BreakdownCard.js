import React, {useId} from 'react'
import PropTypes from 'prop-types'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../../constants/Constants'
import {BucketTable} from '../shared/BucketTable'
import styles from './BreakdownCard.module.scss'

/**
 * "Where the words come from": the analysis buckets of one job or chunk, once
 * the analysis is complete.
 */
export const BreakdownCard = ({
  matches,
  payableRates,
  workflowType,
  countUnit = UNIT_COUNT.WORDS,
  totals,
  done,
  reloadWhenDone = false,
}) => {
  const titleId = useId()
  return (
    <section className={styles.card} aria-labelledby={titleId}>
      <h2 id={titleId} className={styles.title}>
        {countUnit === UNIT_COUNT.CHARACTERS
          ? 'Where the characters come from'
          : 'Where the words come from'}
      </h2>
      {done && workflowType ? (
        <BucketTable
          matches={matches}
          payableRates={payableRates}
          workflowType={workflowType}
          countUnit={countUnit}
          totals={totals}
        />
      ) : (
        <p className={styles.message}>
          {reloadWhenDone
            ? 'The analysis is still running. Reload the page once it is complete to see the breakdown by bucket.'
            : 'The breakdown by bucket appears when the analysis is complete.'}
        </p>
      )}
    </section>
  )
}

BreakdownCard.propTypes = {
  matches: PropTypes.array.isRequired,
  payableRates: PropTypes.object,
  workflowType: PropTypes.oneOf(Object.values(ANALYSIS_WORKFLOW_TYPES)),
  countUnit: PropTypes.oneOf(Object.values(UNIT_COUNT)),
  totals: PropTypes.shape({
    raw: PropTypes.number,
    equivalent: PropTypes.number,
  }),
  // The analysis is complete
  done: PropTypes.bool,
  // A page that does not refresh itself (the job page): ask for a reload
  reloadWhenDone: PropTypes.bool,
}
