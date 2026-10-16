import {ANALYSIS_STATUS} from '../../../constants/Constants'

export const ANALYSIS_PHASE = {
  COUNTING: 'counting',
  QUEUE: 'queue',
  ANALYZING: 'analyzing',
  DONE: 'done',
  NOT_ANALYZED: 'notAnalyzed',
  EMPTY: 'empty',
}

export const ANALYSIS_LAYOUT = {
  EMPTY: 'empty',
  JOB: 'job',
  SINGLE: 'single',
  TREE: 'tree',
}

const getPhase = (summary = {}) => {
  switch (summary.status) {
    case ANALYSIS_STATUS.DONE:
      return ANALYSIS_PHASE.DONE
    case ANALYSIS_STATUS.NOT_TO_ANALYZE:
      return ANALYSIS_PHASE.NOT_ANALYZED
    case ANALYSIS_STATUS.EMPTY:
      return ANALYSIS_PHASE.EMPTY
  }
  if (summary.in_queue_before > 0) return ANALYSIS_PHASE.QUEUE
  if (summary.status === ANALYSIS_STATUS.FAST_OK) {
    return ANALYSIS_PHASE.ANALYZING
  }
  return ANALYSIS_PHASE.COUNTING
}

const getLayout = (phase, jobAnalysis, jobs) => {
  if (
    phase === ANALYSIS_PHASE.NOT_ANALYZED ||
    phase === ANALYSIS_PHASE.EMPTY ||
    // e.g. no chunk matched the job password: nothing to show on the job page
    (jobAnalysis && jobs.length === 0)
  ) {
    return ANALYSIS_LAYOUT.EMPTY
  }
  if (jobAnalysis) return ANALYSIS_LAYOUT.JOB
  if (jobs.length === 1 && jobs[0].chunks.length === 1) {
    return ANALYSIS_LAYOUT.SINGLE
  }
  return ANALYSIS_LAYOUT.TREE
}

const getTotals = (item) => ({
  raw: item.total_raw ?? 0,
  industry: item.total_industry ?? 0,
  equivalent: item.total_equivalent ?? 0,
})

const normalizeFile = (file) => ({
  id: file.id,
  // `name` is already the readable path; `original_name` is not
  name: file.name,
  // The API sends no total_industry per file
  totals: {...getTotals(file), industry: null},
  matches: file.matches ?? [],
})

const normalizeChunk = (chunk, index, count) => ({
  index,
  count,
  password: chunk.password,
  urls: chunk.urls ?? {},
  totals: getTotals(chunk),
  summary: chunk.summary ?? [],
  files: (chunk.files ?? []).map(normalizeFile),
})

// On the job page the job holds only the translator's chunk: its position
// and the job's chunk count come from the hook (chunkPosition, chunkCount)
const normalizeJob = (job, jobAnalysis) => {
  const rawChunks = job.chunks ?? []
  const chunkCount =
    jobAnalysis && job.chunkCount ? job.chunkCount : rawChunks.length
  const chunks = rawChunks.map((chunk, i) =>
    normalizeChunk(
      chunk,
      jobAnalysis && job.chunkPosition ? job.chunkPosition : i + 1,
      chunkCount,
    ),
  )
  return {
    id: job.id,
    source: job.source,
    target: job.target,
    sourceName: job.source_name,
    targetName: job.target_name,
    countUnit: job.count_unit,
    payableRates: job.payable_rates,
    outsourceAvailable: !!job.outsource_available,
    outsourceInfo: job.outsource_info ?? null,
    totals: getTotals(job),
    isSplit: chunkCount > 1,
    chunkCount,
    chunks,
  }
}

/**
 * Derives what the analysis page shows from the volume analysis response.
 * Pure: no React, no Immutable.
 *
 * @param {object} params
 * @param {object} [params.analysis] get-volume-analysis response
 * @param {boolean} [params.jobAnalysis] job page (translator sees one chunk)
 * @returns {object}
 */
export const deriveAnalysisView = ({analysis, jobAnalysis = false} = {}) => {
  const summary = analysis?.summary ?? {}
  const phase = getPhase(summary)
  const jobs = (analysis?.jobs ?? []).map((job) =>
    normalizeJob(job, jobAnalysis),
  )
  const segmentsAnalyzed = summary.segments_analyzed ?? 0
  const totalSegments = summary.total_segments ?? 0

  return {
    phase,
    layout: getLayout(phase, jobAnalysis, jobs),
    known: {
      raw: [
        ANALYSIS_PHASE.QUEUE,
        ANALYSIS_PHASE.ANALYZING,
        ANALYSIS_PHASE.DONE,
      ].includes(phase),
      weighted: phase === ANALYSIS_PHASE.DONE,
      liveWeighted: phase === ANALYSIS_PHASE.ANALYZING,
    },
    actionsEnabled: phase === ANALYSIS_PHASE.DONE,
    progress: {
      segmentsAnalyzed,
      totalSegments,
      percent:
        totalSegments > 0
          ? Math.min(
              100,
              Math.max(0, Math.floor((segmentsAnalyzed / totalSegments) * 100)),
            )
          : 0,
      inQueueBefore: summary.in_queue_before ?? 0,
    },
    jobs,
    totals: {
      raw: summary.total_raw ?? 0,
      industry: summary.total_industry ?? 0,
      equivalent: summary.total_equivalent ?? 0,
      discount: summary.discount ?? 0,
    },
  }
}

export default deriveAnalysisView
