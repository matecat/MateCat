import React, {createRef, useId, useMemo} from 'react'
import PropTypes from 'prop-types'
import {fromJS} from 'immutable'
import ModalsActions from '../../../actions/ModalsActions'
import {getGlobalWarnings} from '../../../api/getGlobalWarnings'
import CommonUtils from '../../../utils/commonUtils'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../../constants/Constants'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import Tooltip from '../../common/Tooltip'
import {JobDownloadMenu} from '../../common/JobDownloadMenu'
import Merge from '../../../../img/icons/Merge'
import Split from '../../../../img/icons/Split'
import Download from '../../../../img/icons/Download'
import {formatCount} from '../utils/buckets'
import {BucketTable} from '../shared/BucketTable'
import {FileList} from '../shared/FileList'
import {Pending} from '../shared/Pending'
import {useValueFlash, VALUE_FLASH_CLASS} from '../shared/useValueFlash'
import {OutsourceOfferCard} from '../OutsourceOfferCard'
import {ACTIONS_DISABLED_TITLE, getLanguagePair} from '../LanguageTree'
import {DisabledHint} from '../shared/DisabledHint'
import styles from './DetailsPanel.module.scss'

const SPLIT_NOT_ALLOWED_HINT =
  'Only the project owner or a member of its team can split a job'
const MERGE_NOT_ALLOWED_HINT =
  'Only the project owner or a member of its team can merge a job'

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

// Segments with at least one ERROR issue, counted once whatever the category
// (as SegmentStore.updateGlobalWarnings does)
export const countErrorSegments = (details) =>
  new Set(Object.values(details?.ERROR?.Categories ?? {}).flat()).size

export const getSubtitle = (job, chunk) => {
  if (!job.isSplit) return `ID ${job.id}`
  if (!chunk) return `Whole job · ${job.chunkCount} chunks · ID ${job.id}`
  return `Chunk ${chunk.index} of ${chunk.count} · job ${job.id}`
}

const TotalValue = ({label, value, known, live}) => {
  const flashing = useValueFlash(value, {enabled: live && known})
  return (
    <div
      className={[
        styles.total,
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
}

const NotAllowedButton = ({hint, className, children}) => (
  // A refused split/merge stays hoverable so the tooltip can explain why
  <Tooltip content={hint} stylePointerElement={{display: 'inline-flex'}}>
    <Button
      ref={createRef()}
      type={BUTTON_TYPE.PRIMARY}
      mode={BUTTON_MODE.OUTLINE}
      size={BUTTON_SIZE.SMALL}
      className={className}
      aria-disabled={true}
      tabIndex="0"
      onClick={() => {}}
    >
      {children}
    </Button>
  </Tooltip>
)

NotAllowedButton.propTypes = {
  hint: PropTypes.string.isRequired,
  className: PropTypes.string,
  children: PropTypes.node,
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
  const isWholeSplitJob = job.isSplit && !chunk
  // An unsplit job is its only chunk
  const item = chunk ?? (job.isSplit ? null : job.chunks[0])
  // The selected chunk, or every chunk of a whole split job
  const chunks = item ? [item] : job.chunks

  const projectJobs = useMemo(() => project.jobs ?? [], [project])
  const findProjectJob = (password) =>
    projectJobs.find(
      (projectJob) =>
        String(projectJob.id) === String(job.id) &&
        projectJob.password === password,
    )
  const projectJob = item ? findProjectJob(item.password) : undefined

  const isJobCompleted = chunks.every((current) => {
    const stats = findProjectJob(current.password)?.stats
    return stats?.raw ? CommonUtils.isJobCompleted(stats) : false
  })

  const thereIsChunkOutsourced = projectJobs.some(
    (projectJob) =>
      String(projectJob.id) === String(job.id) && !!projectJob.outsource,
  )

  const totals = item ? item.totals : job.totals
  const matches = item
    ? item.summary
    : sumMatches(job.chunks.map((c) => c.summary))
  const files = item ? item.files : mergeFiles(job.chunks)
  const weightedKnown = known.weighted || known.liveWeighted
  const live = known.liveWeighted
  const countUnit = job.countUnit ?? UNIT_COUNT.WORDS
  const characters = countUnit === UNIT_COUNT.CHARACTERS

  const findProjectJobForModals = () =>
    projectJobs.find((projectJob) => String(projectJob.id) === String(job.id))

  const openSplitModal = () => {
    ModalsActions.openSplitJobModal(
      fromJS(findProjectJobForModals()),
      fromJS(project),
      () => window.location.reload(),
    )
  }

  const openMergeModal = () => {
    ModalsActions.openMergeModal(project, findProjectJobForModals(), () =>
      window.location.reload(),
    )
  }

  // The QA check of the CAT tool: segments with errors may break the file
  const showWarnings = (proceed, proceedWithoutErrors, chunkWithErrors) =>
    ModalsActions.showDownloadWarningsModal(
      proceed,
      proceedWithoutErrors,
      () => window.open(chunkWithErrors.urls.t, '_blank'),
      // The analysis page has no ownerIsMe: splitEnabled is set for the
      // project owner and the members of its team
      {ownerIsMe: !!config.splitEnabled},
    )

  const checkGlobalWarnings = (proceed, proceedWithoutErrors) => {
    // A Google Drive download opens a window, which the browser allows only
    // inside the click: decide from the project's warnings_count, no await
    const counts = chunks.map(
      (current) => findProjectJob(current.password)?.warnings_count,
    )
    if (
      config.isGDriveProject &&
      counts.every((count) => typeof count === 'number')
    ) {
      const index = counts.findIndex((count) => count > 0)
      if (index < 0) proceed()
      else showWarnings(proceed, proceedWithoutErrors, chunks[index])
      return
    }
    Promise.all(
      chunks.map((current) =>
        getGlobalWarnings({id_job: job.id, password: current.password}).then(
          (data) => ({
            chunk: current,
            errors: countErrorSegments(data.details),
          }),
        ),
      ),
    )
      .then((results) => {
        const withErrors = results.find((result) => result.errors > 0)
        if (!withErrors) {
          proceed()
          return
        }
        showWarnings(proceed, proceedWithoutErrors, withErrors.chunk)
      })
      // The server still checks the file: a failed warnings call does not block
      .catch(() => proceed())
  }

  const splitOrMerge = job.isSplit ? (
    config.splitEnabled ? (
      <Button
        type={BUTTON_TYPE.PRIMARY}
        mode={BUTTON_MODE.OUTLINE}
        size={BUTTON_SIZE.SMALL}
        className="merge"
        tabIndex="0"
        disabled={!actionsEnabled}
        onClick={openMergeModal}
      >
        <Merge size={18} />
        Merge
      </Button>
    ) : (
      <NotAllowedButton
        hint={MERGE_NOT_ALLOWED_HINT}
        className="merge merge-not-allowed"
      >
        <Merge size={18} />
        Merge
      </NotAllowedButton>
    )
  ) : config.splitFeatureAvailable ? (
    config.splitEnabled ? (
      <Button
        type={BUTTON_TYPE.PRIMARY}
        mode={BUTTON_MODE.OUTLINE}
        size={BUTTON_SIZE.SMALL}
        className="split"
        tabIndex="0"
        disabled={!actionsEnabled || thereIsChunkOutsourced}
        onClick={openSplitModal}
      >
        <Split size={18} />
        Split
      </Button>
    ) : (
      <NotAllowedButton
        hint={SPLIT_NOT_ALLOWED_HINT}
        className="split split-not-allowed"
      >
        <Split size={18} />
        Split
      </NotAllowedButton>
    )
  ) : null

  return (
    <section className={styles.panel} aria-labelledby={titleId}>
      <div className={styles.header}>
        <div>
          <h2 id={titleId} className={styles.title}>
            {getLanguagePair(job)}
          </h2>
          <p className={styles.subtitle}>{getSubtitle(job, chunk)}</p>
        </div>
        <div className={styles.headerActions}>
          <DisabledHint hint={ACTIONS_DISABLED_TITLE} active={!actionsEnabled}>
            {splitOrMerge}
          </DisabledHint>
          {/* Every download returns the whole job, whatever chunk password
              it gets: a single chunk of a split job has no download menu */}
          {!(job.isSplit && chunk) && chunks.length > 0 && (
            <DisabledHint
              hint={ACTIONS_DISABLED_TITLE}
              active={!actionsEnabled}
            >
              <JobDownloadMenu
                // For a whole split job, the first chunk's password
                jid={job.id}
                password={chunks[0].password}
                isGDriveProject={!!config.isGDriveProject}
                isJobCompleted={isJobCompleted}
                disabled={!actionsEnabled}
                onBeforeDownload={checkGlobalWarnings}
                triggerProps={{
                  children: (
                    <>
                      <Download size={18} />
                      Download
                    </>
                  ),
                  type: BUTTON_TYPE.PRIMARY,
                  mode: BUTTON_MODE.OUTLINE,
                  size: BUTTON_SIZE.SMALL,
                }}
              />
            </DisabledHint>
          )}
        </div>
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
        />
      </dl>

      {workflowType && (
        <BucketTable
          matches={matches}
          payableRates={job.payableRates}
          workflowType={workflowType}
          countUnit={countUnit}
          totals={totals}
        />
      )}

      {workflowType && files.length > 0 && (
        <div className={styles.section}>
          <h3 className={styles.sectionTitle}>Files</h3>
          <FileList
            files={files}
            payableRates={job.payableRates}
            workflowType={workflowType}
            countUnit={countUnit}
          />
        </div>
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
