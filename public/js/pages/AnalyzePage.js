import React, {useContext, useEffect, useMemo} from 'react'
import PropTypes from 'prop-types'
import UserStore from '../stores/UserStore'
import Header from '../components/header/Header'
import {CookieConsent} from '../components/common/CookieConsent'
import {SpinnerLoader} from '../components/common/SpinnerLoader'
import useVolumeAnalysis from '../hooks/useVolumeAnalysis'
import {mountPage} from './mountPage'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import SocketListener from '../sse/SocketListener'
import {
  ANALYSIS_LAYOUT,
  ANALYSIS_PHASE,
  deriveAnalysisView,
} from '../components/analyze/utils/deriveAnalysisView'
import {AnalysisStatusCard} from '../components/analyze/shared/AnalysisStatusCard'
import {
  ANALYSIS_PAGE_TITLE,
  AnalysisNoticeContext,
  AnalysisPage,
  SCROLL_CONTAINER_CLASS,
} from '../components/analyze/shared/AnalysisPage'
import {ProjectTreeAnalysis} from '../components/analyze/ProjectTreeAnalysis'
import {SingleJobAnalysis} from '../components/analyze/SingleJobAnalysis'
import {JobAnalysis} from '../components/analyze/JobAnalysis'
import {EmptyAnalysis} from '../components/analyze/EmptyAnalysis'
import {
  ANALYSIS_PROBLEM,
  AnalysisProblemCard,
} from '../components/analyze/AnalysisProblemCard'

const LAYOUT_COMPONENTS = {
  [ANALYSIS_LAYOUT.TREE]: ProjectTreeAnalysis,
  [ANALYSIS_LAYOUT.SINGLE]: SingleJobAnalysis,
  [ANALYSIS_LAYOUT.JOB]: JobAnalysis,
}

// Phases in which an analysis with no jobs has nothing more to come
const FINAL_PHASES = [
  ANALYSIS_PHASE.DONE,
  ANALYSIS_PHASE.EMPTY,
  ANALYSIS_PHASE.NOT_ANALYZED,
]

export const REFRESH_FAILED_NOTICE =
  'We could not refresh the analysis. Reload the page to see the latest numbers.'

const AnalysisContent = ({jobAnalysis}) => {
  const {project, analysis, loading, error} = useVolumeAnalysis()
  const view = useMemo(
    () => deriveAnalysisView({analysis, jobAnalysis}),
    [analysis, jobAnalysis],
  )
  const title = jobAnalysis
    ? ANALYSIS_PAGE_TITLE.JOB
    : ANALYSIS_PAGE_TITLE.PROJECT

  if (loading && !analysis) {
    return (
      <AnalysisPage title={title}>
        <SpinnerLoader label={`Loading the ${title.toLowerCase()}`} />
      </AnalysisPage>
    )
  }
  if (!analysis || !project) {
    // Loaded, yet nothing to show: a job page whose project could not be
    // read is as good as a job that is gone
    return (
      <AnalysisPage title={title}>
        <AnalysisProblemCard
          problem={
            jobAnalysis && analysis && !error
              ? ANALYSIS_PROBLEM.JOB_NOT_FOUND
              : ANALYSIS_PROBLEM.LOAD_ERROR
          }
        />
      </AnalysisPage>
    )
  }

  let content
  if (view.jobs.length === 0 && !FINAL_PHASES.includes(view.phase)) {
    // Still counting: the jobs arrive with the next poll
    content = (
      <AnalysisPage title={title}>
        <AnalysisStatusCard phase={view.phase} progress={view.progress} />
      </AnalysisPage>
    )
  } else if (view.layout === ANALYSIS_LAYOUT.EMPTY || view.jobs.length === 0) {
    content = (
      <EmptyAnalysis project={project} view={view} jobAnalysis={jobAnalysis} />
    )
  } else {
    const Layout = LAYOUT_COMPONENTS[view.layout]
    content = (
      <Layout
        project={project}
        view={view}
        workflowType={analysis.workflow_type ?? project.analysis?.workflow_type}
      />
    )
  }

  // A failed refresh leaves the last numbers on screen, with a word about it
  return (
    <AnalysisNoticeContext.Provider
      value={error ? REFRESH_FAILED_NOTICE : null}
    >
      {content}
    </AnalysisNoticeContext.Provider>
  )
}

AnalysisContent.propTypes = {
  jobAnalysis: PropTypes.bool,
}

const AnalyzePage = () => {
  const jobAnalysis = !!config.jobAnalysis
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  useEffect(() => {
    document.title = `${
      jobAnalysis ? ANALYSIS_PAGE_TITLE.JOB : ANALYSIS_PAGE_TITLE.PROJECT
    } - Matecat`
  }, [jobAnalysis])

  return (
    <>
      <header>
        <Header
          loggedUser={isUserLogged}
          showSubHeader={false}
          showModals={false}
          changeTeam={false}
          user={UserStore.getUser()}
        />
      </header>
      <div
        className={`project-list ${SCROLL_CONTAINER_CLASS}`}
        id="analyze-container"
      >
        <div className="layout__container">
          <AnalysisContent jobAnalysis={jobAnalysis} />
        </div>
      </div>
      <footer>
        <CookieConsent />
      </footer>
      <SocketListener
        isAuthenticated={isUserLogged}
        userId={isUserLogged ? userInfo.user.uid : null}
      />
    </>
  )
}

export default AnalyzePage

mountPage({
  Component: AnalyzePage,
  rootElement: document.getElementsByClassName('analyze')[0],
})
