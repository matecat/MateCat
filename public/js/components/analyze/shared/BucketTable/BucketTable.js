import React, {useId, useState} from 'react'
import PropTypes from 'prop-types'
import {
  ANALYSIS_WORKFLOW_TYPES,
  UNIT_COUNT,
} from '../../../../constants/Constants'
import {formatCount, getBuckets} from '../../utils/buckets'
import styles from './BucketTable.module.scss'

const formatRate = (rate) =>
  rate === undefined || rate === null ? '—' : `${rate}%`

/**
 * Where the words of a job, chunk or file come from: a bar sized by raw count,
 * then one row per analysis bucket with its payable rate, raw and weighted
 * counts and a total. Empty buckets are hidden until the user asks for them.
 *
 * `totals` are the server's own totals: buckets arrive rounded one by one, so
 * their sum can differ from them by a few words. Without `totals` the footer
 * shows the sum of the buckets.
 */
export const BucketTable = ({
  matches,
  payableRates,
  workflowType,
  countUnit = UNIT_COUNT.WORDS,
  totals,
}) => {
  const [showEmpty, setShowEmpty] = useState(false)
  const bodyId = useId()

  const buckets = getBuckets({matches, payableRates, workflowType})
  const filled = buckets.filter((bucket) => !bucket.empty)
  const emptyCount = buckets.length - filled.length
  const rows = showEmpty ? buckets : filled

  if (buckets.length === 0) return null

  const bucketsRaw = buckets.reduce((sum, bucket) => sum + bucket.raw, 0)
  const totalRaw = totals?.raw ?? bucketsRaw
  const totalEquivalent =
    totals?.equivalent ??
    buckets.reduce((sum, bucket) => sum + bucket.equivalent, 0)
  const unit = countUnit === UNIT_COUNT.CHARACTERS ? 'characters' : 'words'

  const barLabel = `Where the ${formatCount(totalRaw)} ${unit} come from: ${filled
    .map(
      ({label, raw}) =>
        `${label} ${formatCount(raw)} (${Math.round((raw / bucketsRaw) * 100)}%)`,
    )
    .join(', ')}`

  return (
    <div className={styles.component}>
      {bucketsRaw > 0 && (
        <div className={styles.bar} role="img" aria-label={barLabel}>
          {filled.map(({type, raw}) => (
            <span
              key={type}
              className={styles.segment}
              data-bucket={type}
              style={{flexGrow: raw}}
            />
          ))}
        </div>
      )}
      <table className={styles.table}>
        <caption
          className={styles.caption}
        >{`Analysis by bucket, in ${unit}`}</caption>
        <thead>
          <tr>
            <th scope="col">Analysis bucket</th>
            <th scope="col">Payable rate</th>
            <th scope="col">Raw</th>
            <th scope="col">Weighted</th>
          </tr>
        </thead>
        <tbody id={bodyId}>
          {rows.map(({type, label, rate, raw, equivalent}) => (
            <tr key={type}>
              <th scope="row">
                <span className={styles.bucket}>
                  <span
                    className={styles.swatch}
                    data-bucket={type}
                    aria-hidden="true"
                  />
                  {label}
                </span>
              </th>
              <td>{formatRate(rate)}</td>
              <td>{formatCount(raw)}</td>
              <td>{formatCount(equivalent)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <th scope="row">Total</th>
            <td />
            <td>{formatCount(totalRaw)}</td>
            <td>{formatCount(totalEquivalent)}</td>
          </tr>
        </tfoot>
      </table>
      {emptyCount > 0 && (
        <button
          type="button"
          className={styles.toggle}
          aria-expanded={showEmpty}
          aria-controls={bodyId}
          onClick={() => setShowEmpty((value) => !value)}
        >
          {showEmpty
            ? 'Hide empty buckets'
            : `Show all ${buckets.length} buckets (${emptyCount} empty)`}
        </button>
      )}
    </div>
  )
}

BucketTable.propTypes = {
  matches: PropTypes.arrayOf(
    PropTypes.shape({
      type: PropTypes.string.isRequired,
      raw: PropTypes.number,
      equivalent: PropTypes.number,
    }),
  ).isRequired,
  payableRates: PropTypes.object,
  workflowType: PropTypes.oneOf(Object.values(ANALYSIS_WORKFLOW_TYPES))
    .isRequired,
  countUnit: PropTypes.oneOf(Object.values(UNIT_COUNT)),
  // Server totals (total_raw, total_equivalent); the footer prefers them
  totals: PropTypes.shape({
    raw: PropTypes.number,
    equivalent: PropTypes.number,
  }),
}
