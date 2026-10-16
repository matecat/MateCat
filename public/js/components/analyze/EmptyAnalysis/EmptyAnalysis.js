import React from 'react'
import PropTypes from 'prop-types'
import {ANALYSIS_PHASE} from '../utils/deriveAnalysisView'
import {ProjectHeaderCard} from '../ProjectHeaderCard'
import {ANALYSIS_PROBLEM, AnalysisProblemCard} from '../AnalysisProblemCard'
import {ANALYSIS_PAGE_TITLE, AnalysisPage} from '../shared/AnalysisPage'

// Why an analysis has nothing to show
export const getAnalysisProblem = (phase, jobAnalysis = false) => {
  if (phase === ANALYSIS_PHASE.NOT_ANALYZED) {
    return ANALYSIS_PROBLEM.NOT_ANALYZED
  }
  // A job page whose chunk is not in the analysis
  if (jobAnalysis && phase !== ANALYSIS_PHASE.EMPTY) {
    return ANALYSIS_PROBLEM.JOB_NOT_FOUND
  }
  return ANALYSIS_PROBLEM.EMPTY
}

/**
 * An analysis with nothing to show: no text in the files, an analysis that
 * failed, or a job page whose chunk is gone. The project page keeps its
 * header so the project can still be told apart.
 */
export const EmptyAnalysis = ({project, view, jobAnalysis = false}) => {
  const problem = getAnalysisProblem(view.phase, jobAnalysis)

  return (
    <AnalysisPage
      title={
        jobAnalysis ? ANALYSIS_PAGE_TITLE.JOB : ANALYSIS_PAGE_TITLE.PROJECT
      }
    >
      {!jobAnalysis && project?.password && (
        <ProjectHeaderCard
          projectId={project.id}
          projectName={project.name}
          projectPassword={project.password}
          languageCount={view.jobs.length}
          phase={view.phase}
          totals={view.totals}
        />
      )}
      <AnalysisProblemCard problem={problem} />
    </AnalysisPage>
  )
}

EmptyAnalysis.propTypes = {
  // Plain project, as returned by the project API
  project: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    name: PropTypes.string,
    password: PropTypes.string,
  }),
  // deriveAnalysisView output
  view: PropTypes.shape({
    phase: PropTypes.string.isRequired,
    jobs: PropTypes.array.isRequired,
    totals: PropTypes.object.isRequired,
  }).isRequired,
  jobAnalysis: PropTypes.bool,
}
