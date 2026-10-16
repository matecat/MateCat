import React from 'react'
import PropTypes from 'prop-types'
import {getLanguagePair} from '../LanguageTree'
import {JobActions} from '../JobActions'
import {JobSummaryCard} from '../JobSummaryCard'
import {BreakdownCard} from '../BreakdownCard'
import {FilesCard} from '../FilesCard'
import {PhaseBadge} from '../ProjectHeaderCard'
import {ANALYSIS_PHASE} from '../utils/deriveAnalysisView'
import {ANALYSIS_PAGE_TITLE, AnalysisPage} from '../shared/AnalysisPage'
import styles from './JobAnalysis.module.scss'

/**
 * The job page (/jobanalysis/): the one chunk the translator opened, its
 * totals, buckets and files. Reached once the analysis is done; nothing to
 * buy, split or merge here.
 */
export const JobAnalysis = ({project, view, workflowType}) => {
  const {jobs, phase, known, actionsEnabled} = view
  const [job] = jobs
  const [chunk] = job.chunks
  const done = phase === ANALYSIS_PHASE.DONE

  return (
    <AnalysisPage title={ANALYSIS_PAGE_TITLE.JOB}>
      <JobSummaryCard
        title={getLanguagePair(job)}
        badge={
          job.isSplit ? `Chunk ${chunk.index} of ${chunk.count}` : undefined
        }
        subtitle={`Job ID ${job.id}`}
        status={
          <p className={styles.status}>
            <span>Analysis status:</span>
            <PhaseBadge phase={phase} />
          </p>
        }
        totals={chunk.totals}
        known={known}
        workflowType={workflowType}
        countUnit={job.countUnit}
        actions={
          <>
            {/* A chunk of a split job gets no download: every download
                returns the whole job */}
            <JobActions
              project={project}
              job={job}
              chunk={job.isSplit ? chunk : null}
              actionsEnabled={actionsEnabled}
              showSplitMerge={false}
            />
            {chunk.urls?.t && (
              <a className={styles.back} href={chunk.urls.t}>
                Back to translation
              </a>
            )}
          </>
        }
      />
      <div className={styles.columns}>
        <BreakdownCard
          matches={chunk.summary}
          payableRates={job.payableRates}
          workflowType={workflowType}
          countUnit={job.countUnit}
          totals={chunk.totals}
          done={done}
          reloadWhenDone={true}
        />
        <FilesCard
          files={chunk.files}
          payableRates={job.payableRates}
          workflowType={workflowType}
          countUnit={job.countUnit}
          done={done}
          reloadWhenDone={true}
        />
      </div>
    </AnalysisPage>
  )
}

JobAnalysis.propTypes = {
  // Plain project, as returned by the project token API
  project: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    jobs: PropTypes.array,
  }).isRequired,
  // deriveAnalysisView output of the job page: one job, the opened chunk
  view: PropTypes.shape({
    phase: PropTypes.string.isRequired,
    known: PropTypes.object.isRequired,
    actionsEnabled: PropTypes.bool,
    jobs: PropTypes.array.isRequired,
  }).isRequired,
  workflowType: PropTypes.string,
}
