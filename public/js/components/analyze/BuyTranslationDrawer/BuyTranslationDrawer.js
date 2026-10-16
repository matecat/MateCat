import React, {useMemo} from 'react'
import PropTypes from 'prop-types'
import {fromJS} from 'immutable'
import OutsourceDrawer from '../../outsource/OutsourceDrawer'
import OutsourceOrderPanel from '../../outsource/OutsourceOrderPanel'
import {formatCount} from '../utils/buckets'
import {getLanguagePair} from '../LanguageTree'
import styles from './BuyTranslationDrawer.module.scss'

// "en-US › fr-FR · chunk 1 of 6 · 59 Matecat weighted words"
export const getOrderItem = (job, chunk) => {
  const unit = chunk ?? job
  const part = !job.isSplit
    ? ''
    : chunk
      ? ` · chunk ${chunk.index} of ${chunk.count}`
      : ` · ${job.chunkCount} chunks`
  return `${getLanguagePair(job)}${part} · ${formatCount(unit.totals.equivalent)} Matecat weighted words`
}

const ChunkPicker = ({job, onPickChunk, onMerge}) => (
  <div className={styles.picker}>
    <p className={styles.pickerText}>
      {`This job is split into ${job.chunkCount} chunks, and each chunk is bought on its own.`}
    </p>
    {onMerge && (
      <div className={styles.mergeBox}>
        <span>To buy the whole job in one order, merge it first.</span>
        <button type="button" className={styles.mergeButton} onClick={onMerge}>
          Merge
        </button>
      </div>
    )}
    <span className={styles.muted}>Or pick a chunk:</span>
    <div className={styles.chunks}>
      {job.chunks.map((chunk) => (
        <button
          key={chunk.index}
          type="button"
          className={styles.chunk}
          onClick={() => onPickChunk(chunk)}
        >
          <span className={styles.chunkName}>{`Chunk ${chunk.index}`}</span>
          <span className={styles.muted}>
            {`${formatCount(chunk.totals.equivalent)} words`}
          </span>
          <span className={styles.chunkAction}>Get quote</span>
        </button>
      ))}
    </div>
  </div>
)

ChunkPicker.propTypes = {
  job: PropTypes.object.isRequired,
  onPickChunk: PropTypes.func.isRequired,
  onMerge: PropTypes.func,
}

/**
 * The analysis page's order drawer: the shared outsource drawer with the
 * order panel for a job or chunk (quotes cached with the offer card's). A
 * whole split job first asks for a chunk.
 */
export const BuyTranslationDrawer = ({
  open,
  onClose,
  project,
  job,
  chunk,
  onPickChunk,
  onMerge,
}) => {
  const projectJob = useMemo(
    () =>
      chunk &&
      ((project.jobs ?? []).find(
        (current) =>
          String(current.id) === String(job.id) &&
          current.password === chunk.password,
      ) ?? {
        id: job.id,
        password: chunk.password,
        source: job.source,
        target: job.target,
      }),
    [project, job, chunk],
  )
  const immutableProject = useMemo(() => fromJS(project), [project])
  const immutableJob = useMemo(
    () => projectJob && fromJS(projectJob),
    [projectJob],
  )

  return (
    <OutsourceDrawer
      open={open}
      onClose={onClose}
      item={getOrderItem(job, chunk)}
    >
      {chunk ? (
        <OutsourceOrderPanel
          key={`${job.id}-${chunk.password}`}
          project={immutableProject}
          job={immutableJob}
          standardWC={chunk.totals.equivalent}
          cache={true}
        />
      ) : (
        <ChunkPicker job={job} onPickChunk={onPickChunk} onMerge={onMerge} />
      )}
    </OutsourceDrawer>
  )
}

BuyTranslationDrawer.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  // Plain project, as returned by the project API
  project: PropTypes.object.isRequired,
  // deriveAnalysisView job
  job: PropTypes.object.isRequired,
  // The chunk to buy; null on a whole split job asks for one
  chunk: PropTypes.object,
  onPickChunk: PropTypes.func,
  onMerge: PropTypes.func,
}
