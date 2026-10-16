import React, {useCallback, useMemo, useState} from 'react'
import PropTypes from 'prop-types'
import ModalsActions from '../../../actions/ModalsActions'
import {ANALYSIS_PHASE} from '../utils/deriveAnalysisView'
import {ProjectHeaderCard} from '../ProjectHeaderCard'
import {AnalysisStatusCard} from '../shared/AnalysisStatusCard'
import {LanguageTree} from '../LanguageTree'
import {DetailsPanel} from '../DetailsPanel'
import {BuyTranslationDrawer, useBuyTranslation} from '../BuyTranslationDrawer'
import {ANALYSIS_PAGE_TITLE, AnalysisPage} from '../shared/AnalysisPage'
import styles from './ProjectTreeAnalysis.module.scss'

export const PAGE_TITLE = ANALYSIS_PAGE_TITLE.PROJECT

// The first split job as a whole if there is one, else the first job
export const getDefaultSelection = (jobs) => {
  const job = jobs.find((current) => current.isSplit) ?? jobs[0]
  return job ? {jobId: job.id, chunkIndex: null} : null
}

const resolveSelection = (jobs, selection) => {
  const job =
    selection && jobs.find((current) => current.id === selection.jobId)
  if (!job) return null
  if (selection.chunkIndex == null) return {job, chunk: null}
  const chunk = job.chunks.find(
    (current) => current.index === selection.chunkIndex,
  )
  return chunk ? {job, chunk} : null
}

/**
 * The analysis of a project with more than one job, or one job split in
 * chunks: the tree of jobs and chunks with the details of the selected one.
 */
export const ProjectTreeAnalysis = ({project, view, workflowType}) => {
  const {jobs, phase, known, actionsEnabled, progress, totals} = view
  const [selection, setSelection] = useState(() => getDefaultSelection(jobs))

  // A selection that no longer exists (e.g. merged chunks) falls back
  const selected =
    resolveSelection(jobs, selection) ??
    resolveSelection(jobs, getDefaultSelection(jobs))

  const outsourcedChunks = useMemo(
    () =>
      new Set(
        (project.jobs ?? [])
          .filter((projectJob) => !!projectJob.outsource)
          .map((projectJob) => `${projectJob.id}-${projectJob.password}`),
      ),
    [project],
  )

  const selectBought = useCallback(
    (job, chunk) =>
      setSelection({
        jobId: job.id,
        chunkIndex: job.isSplit && chunk ? chunk.index : null,
      }),
    [],
  )
  const {order, openBuy, pickChunk, closeBuy} = useBuyTranslation({
    onOpen: selectBought,
  })

  // From the drawer's chunk picker: merging buys the whole job in one order
  const mergeOrderedJob = useCallback(() => {
    const projectJob = (project.jobs ?? []).find(
      (current) => String(current.id) === String(order?.job.id),
    )
    closeBuy()
    ModalsActions.openMergeModal(project, projectJob, () =>
      window.location.reload(),
    )
  }, [project, order, closeBuy])

  return (
    <AnalysisPage title={PAGE_TITLE}>
      <ProjectHeaderCard
        projectId={project.id}
        projectName={project.name}
        projectPassword={project.password}
        languageCount={jobs.length}
        phase={phase}
        totals={totals}
      />
      {phase !== ANALYSIS_PHASE.DONE && (
        <AnalysisStatusCard phase={phase} progress={progress} />
      )}
      <div className={styles.columns}>
        <div className={styles.tree}>
          <LanguageTree
            jobs={jobs}
            workflowType={workflowType}
            known={known}
            actionsEnabled={actionsEnabled}
            totals={totals}
            selection={
              selected && {
                jobId: selected.job.id,
                chunkIndex: selected.chunk?.index ?? null,
              }
            }
            outsourcedChunks={outsourcedChunks}
            onSelect={setSelection}
            onBuy={openBuy}
          />
        </div>
        {selected && (
          <div className={styles.details}>
            <DetailsPanel
              // A new selection starts with its file breakdown closed
              key={`${selected.job.id}-${selected.chunk?.password ?? 'job'}`}
              project={project}
              job={selected.job}
              chunk={selected.chunk}
              workflowType={workflowType}
              known={known}
              actionsEnabled={actionsEnabled}
              onBuy={openBuy}
            />
          </div>
        )}
      </div>
      {order && (
        <BuyTranslationDrawer
          open={true}
          onClose={closeBuy}
          project={project}
          job={order.job}
          chunk={order.chunk}
          onPickChunk={pickChunk}
          onMerge={config.splitEnabled ? mergeOrderedJob : undefined}
        />
      )}
    </AnalysisPage>
  )
}

ProjectTreeAnalysis.propTypes = {
  // Plain project, as returned by the project API
  project: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
    name: PropTypes.string,
    password: PropTypes.string.isRequired,
    jobs: PropTypes.array,
  }).isRequired,
  // deriveAnalysisView output
  view: PropTypes.shape({
    phase: PropTypes.string.isRequired,
    known: PropTypes.object.isRequired,
    actionsEnabled: PropTypes.bool,
    progress: PropTypes.object,
    jobs: PropTypes.array.isRequired,
    totals: PropTypes.object.isRequired,
  }).isRequired,
  workflowType: PropTypes.string,
}
