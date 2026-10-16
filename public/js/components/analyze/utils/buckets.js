import {
  ANALYSIS_BUCKETS_LABELS,
  ANALYSIS_WORKFLOW_TYPES,
} from '../../../constants/Constants'

// Bucket type → payable_rates key, in the order the analysis shows them
const STANDARD_BUCKETS = [
  ['new', 'NO_MATCH'],
  ['repetitions', 'REPETITIONS'],
  ['internal', 'INTERNAL'],
  ['tm_50_74', '50%-74%'],
  ['tm_75_84', '75%-84%'],
  ['tm_85_94', '85%-94%'],
  ['tm_95_99', '95%-99%'],
  ['tm_100', '100%'],
  ['tm_100_public', '100%_PUBLIC'],
  ['ice', 'ICE'],
  ['MT', 'MT'],
  ['ice_mt', 'ICE_MT'],
]

// MTQE payable rates are keyed by the bucket type itself
const MTQE_BUCKETS = [
  'ice',
  'tm_100',
  'tm_100_public',
  'repetitions',
  'ice_mt',
  'top_quality_mt',
  'higher_quality_mt',
  'standard_quality_mt',
].map((type) => [type, type])

// Some labels carry padding meant for the old fixed-width table
const getLabel = (type) =>
  (ANALYSIS_BUCKETS_LABELS[type] ?? type).replace(/\s+/g, ' ').trim()

const getCounts = (matches, type) => {
  const match = matches.find((item) => item.type === type)
  return {raw: match?.raw ?? 0, equivalent: match?.equivalent ?? 0}
}

const toBucket = (type, rate, {raw, equivalent}) => ({
  type,
  label: getLabel(type),
  rate,
  raw,
  equivalent,
  empty: !(raw > 0),
})

/**
 * The analysis buckets of a job, chunk or file, in display order.
 *
 * STANDARD: when the ICE_MT rate equals the MT rate (or is missing) the ice_mt
 * bucket is not listed and its counts are added to MT, as the old tables did.
 *
 * @param {object} params
 * @param {Array<{type: string, raw: number, equivalent: number}>} [params.matches]
 * @param {object} [params.payableRates]
 * @param {string} params.workflowType one of ANALYSIS_WORKFLOW_TYPES
 * @returns {Array<{type, label, rate, raw, equivalent, empty}>}
 */
export const getBuckets = ({
  matches = [],
  payableRates = {},
  workflowType,
} = {}) => {
  const rates = payableRates ?? {}

  if (workflowType === ANALYSIS_WORKFLOW_TYPES.MTQE) {
    return MTQE_BUCKETS.map(([type, key]) =>
      toBucket(type, rates[key], getCounts(matches, type)),
    )
  }
  if (workflowType !== ANALYSIS_WORKFLOW_TYPES.STANDARD) return []

  const iceMtMerged = (rates.ICE_MT ?? rates.MT) === rates.MT
  const iceMt = getCounts(matches, 'ice_mt')

  return STANDARD_BUCKETS.filter(
    ([type]) => !(iceMtMerged && type === 'ice_mt'),
  ).map(([type, key]) => {
    const counts = getCounts(matches, type)
    if (type === 'MT' && iceMtMerged) {
      counts.raw += iceMt.raw
      counts.equivalent += iceMt.equivalent
    }
    // The old table showed a missing ICE rate as 0%
    const rate = type === 'ice' ? (rates[key] ?? 0) : rates[key]
    return toBucket(type, rate, counts)
  })
}

export const formatCount = (value) =>
  Math.round(value ?? 0).toLocaleString('en-US')

/**
 * How much of `total` the weighting takes off, in percent. Whole numbers,
 * except near the ends: 99.86% is 99.9, never 100, and 0.3% is 0.3, never 0,
 * so only an exact 0% or 100% reads as one.
 *
 * @param {number} part weighted count
 * @param {number} total raw count
 * @returns {number|null} null without a total
 */
export const getSavedPercent = (part, total) => {
  if (!(total > 0)) return null
  const percent = (1 - (part ?? 0) / total) * 100
  if (percent === 0 || percent === 100) return percent
  const rounded = Math.round(percent)
  if (rounded > 0 && rounded < 100) return rounded
  if (percent > 0 && percent < 100) {
    return Math.min(99.9, Math.max(0.1, Math.round(percent * 10) / 10))
  }
  return rounded
}
