import React, {useCallback, useMemo, useState} from 'react'
import PropTypes from 'prop-types'
import CommonUtils from '../../../utils/commonUtils'
import {ANALYSIS_PHASE} from '../utils/deriveAnalysisView'
import {ProjectHeaderCard} from '../ProjectHeaderCard'
import {AnalysisStatusCard} from '../shared/AnalysisStatusCard'
import {LanguageTree, getLanguagePair} from '../LanguageTree'
import {DetailsPanel, getSubtitle} from '../DetailsPanel'
import {BuyTranslationDrawer} from '../BuyTranslationDrawer'
import {CONTACT_US_URL} from '../OutsourceOfferCard'
import styles from './ProjectTreeAnalysis.module.scss'

export const PAGE_TITLE = 'Project analysis'

// For the page's scroll container (#analyze-container): see the SCSS
export const SCROLL_CONTAINER_CLASS = styles.scrollContainer

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
  const [drawer, setDrawer] = useState(null)

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

  const closeDrawer = useCallback(() => setDrawer(null), [])

  const openBuy = useCallback(
    (job, chunk) => {
      if (!job.outsourceAvailable) {
        window.open(CONTACT_US_URL, '_blank')
        return
      }
      const projectJob = (project.jobs ?? []).find(
        (current) =>
          String(current.id) === String(job.id) &&
          current.password === chunk.password,
      ) ?? {id: job.id, password: chunk.password}
      CommonUtils.dispatchAnalyticsEvents({event: 'outsource_request'})
      setSelection({
        jobId: job.id,
        chunkIndex: job.isSplit ? chunk.index : null,
      })
      setDrawer({
        projectJob,
        title: getLanguagePair(job),
        subtitle: getSubtitle(job, job.isSplit ? chunk : null),
        weightedWords: chunk.totals.equivalent,
      })
    },
    [project],
  )

  return (
    <div className={styles.page}>
      <h1 className={styles.title}>{PAGE_TITLE}</h1>
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
      {drawer && (
        <BuyTranslationDrawer
          open={true}
          onClose={closeDrawer}
          project={project}
          projectJob={drawer.projectJob}
          title={drawer.title}
          subtitle={drawer.subtitle}
          weightedWords={drawer.weightedWords}
        />
      )}
    </div>
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
