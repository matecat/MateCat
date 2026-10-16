import React, {useId, useState} from 'react'
import PropTypes from 'prop-types'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../../constants/Constants'
import {formatCount, getSavedPercent} from '../utils/buckets'
import {BucketTable} from '../shared/BucketTable'
import {FileList} from '../shared/FileList'
import {Pending} from '../shared/Pending'
import {useValueFlash, VALUE_FLASH_CLASS} from '../shared/useValueFlash'
import {OutsourceOfferCard} from '../OutsourceOfferCard'
import {getLanguagePair} from '../LanguageTree'
import {JobActions} from '../JobActions'
import styles from './DetailsPanel.module.scss'

// Adds up per-bucket counts: a whole split job shows the sum of its chunks
const sumMatches = (lists) => {
  const byType = new Map()
  lists.flat().forEach(({type, raw = 0, equivalent = 0}) => {
    const current = byType.get(type) ?? {type, raw: 0, equivalent: 0}
    byType.set(type, {
      type,
      raw: current.raw + raw,
      equivalent: current.equivalent + equivalent,
    })
  })
  return [...byType.values()]
}

// A file cut across chunks appears in each of them: merge it back
const mergeFiles = (chunks) => {
  const byId = new Map()
  chunks
    .flatMap((chunk) => chunk.files)
    .forEach((file) => {
      const current = byId.get(file.id)
      if (!current) {
        byId.set(file.id, file)
        return
      }
      byId.set(file.id, {
        ...current,
        totals: {
          raw: current.totals.raw + file.totals.raw,
          industry: null,
          equivalent: current.totals.equivalent + file.totals.equivalent,
        },
        matches: sumMatches([current.matches, file.matches]),
      })
    })
  return [...byId.values()]
}

export const getSubtitle = (job, chunk) => {
  if (!job.isSplit) return `ID ${job.id}`
  if (!chunk) return `Whole job · ${job.chunkCount} chunks · ID ${job.id}`
  return `Chunk ${chunk.index} of ${chunk.count} · job ${job.id}`
}

const TotalValue = ({label, value, known, live, strong = false}) => {
  const flashing = useValueFlash(value, {enabled: live && known})
  return (
    <div
      className={[
        styles.total,
        strong && styles.strong,
        VALUE_FLASH_CLASS.base,
        flashing && VALUE_FLASH_CLASS.active,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <dt>{label}</dt>
      <dd>
        <Pending known={known}>{formatCount(value)}</Pending>
      </dd>
    </div>
  )
}

TotalValue.propTypes = {
  label: PropTypes.string.isRequired,
  value: PropTypes.number,
  known: PropTypes.bool.isRequired,
  live: PropTypes.bool,
  // The Matecat weighted value is the bold one
  strong: PropTypes.bool,
}

/**
 * Everything about the job or chunk selected in the tree: split or merge,
 * downloads, totals, buckets, files and the translation offer. `chunk` null
 * on a split job means the whole job.
 */
export const DetailsPanel = ({
  project,
  job,
  chunk,
  workflowType,
  known,
  actionsEnabled,
  onBuy,
}) => {
  const titleId = useId()
  const [filesShown, setFilesShown] = useState(false)
  const isWholeSplitJob = job.isSplit && !chunk
  // An unsplit job is its only chunk
  const item = chunk ?? (job.isSplit ? null : job.chunks[0])

  const projectJob = item
    ? (project.jobs ?? []).find(
        (current) =>
          String(current.id) === String(job.id) &&
          current.password === item.password,
      )
    : undefined

  const totals = item ? item.totals : job.totals
  const matches = item
    ? item.summary
    : sumMatches(job.chunks.map((c) => c.summary))
  const files = item ? item.files : mergeFiles(job.chunks)
  const weightedKnown = known.weighted || known.liveWeighted
  const live = known.liveWeighted
  const countUnit = job.countUnit ?? UNIT_COUNT.WORDS
  const characters = countUnit === UNIT_COUNT.CHARACTERS

  const savedPercent = getSavedPercent(totals.equivalent, totals.raw)
  const manyFiles = files.length > 1

  return (
    <section className={styles.panel} aria-labelledby={titleId}>
      <div className={styles.header}>
        <div className={styles.heading}>
          <h2 id={titleId} className={styles.title}>
            {getLanguagePair(job)}
          </h2>
          <p className={styles.subtitle}>{getSubtitle(job, chunk)}</p>
        </div>
        <JobActions
          project={project}
          job={job}
          chunk={chunk}
          actionsEnabled={actionsEnabled}
        />
      </div>

      <dl className={styles.totals}>
        <TotalValue
          label={characters ? 'Total character count' : 'Total word count'}
          value={totals.raw}
          known={known.raw}
        />
        {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
          <TotalValue
            label="Industry weighted"
            value={totals.industry}
            known={weightedKnown}
            live={live}
          />
        )}
        <TotalValue
          label="Matecat weighted"
          value={totals.equivalent}
          known={weightedKnown}
          live={live}
          strong
        />
      </dl>

      {known.weighted && workflowType ? (
        <>
          <div className={styles.breakdown}>
            <div className={styles.breakdownHeader}>
              <span>
                {characters
                  ? 'Where the characters come from'
                  : 'Where the words come from'}
              </span>
              {savedPercent !== null && <span>{`${savedPercent}% saved`}</span>}
            </div>
            <BucketTable
              matches={matches}
              payableRates={job.payableRates}
              workflowType={workflowType}
              countUnit={countUnit}
              totals={totals}
              compact
            />
            <p className={styles.note}>
              {job.isSplit
                ? `Payable rates are set on the job, so all ${job.chunkCount} chunks share them.`
                : 'Payable rates set on this job.'}
            </p>
          </div>

          {files.length > 0 && (
            <div className={styles.files}>
              {manyFiles ? (
                <button
                  type="button"
                  className={styles.filesToggle}
                  aria-expanded={filesShown}
                  onClick={() => setFilesShown((shown) => !shown)}
                >
                  {`${filesShown ? 'Hide' : 'Show'} the breakdown by file (${formatCount(files.length)})`}
                </button>
              ) : (
                <h3 className={styles.filesTitle}>File</h3>
              )}
              {(!manyFiles || filesShown) && (
                <FileList
                  files={files}
                  payableRates={job.payableRates}
                  workflowType={workflowType}
                  countUnit={countUnit}
                  scroll
                />
              )}
            </div>
          )}
        </>
      ) : (
        <p className={styles.pending}>
          The breakdown by bucket and by file appears when the analysis is
          complete.
        </p>
      )}

      <OutsourceOfferCard
        projectId={project.id}
        projectPassword={project.password}
        job={job}
        chunk={isWholeSplitJob ? null : item}
        jobOutsource={projectJob?.outsource ?? null}
        actionsEnabled={actionsEnabled}
        onBuy={onBuy}
      />
    </section>
  )
}

DetailsPanel.propTypes = {
  // Plain project, as returned by the project API
  project: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
    password: PropTypes.string.isRequired,
    jobs: PropTypes.array,
  }).isRequired,
  job: PropTypes.object.isRequired,
  // null on a split job: the whole job
  chunk: PropTypes.object,
  workflowType: PropTypes.oneOf(Object.values(ANALYSIS_WORKFLOW_TYPES)),
  known: PropTypes.shape({
    raw: PropTypes.bool,
    weighted: PropTypes.bool,
    liveWeighted: PropTypes.bool,
  }).isRequired,
  actionsEnabled: PropTypes.bool,
  onBuy: PropTypes.func.isRequired,
}
