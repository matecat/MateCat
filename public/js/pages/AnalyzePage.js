import React, {useContext, useEffect, useMemo, useRef} from 'react'
import UserStore from '../stores/UserStore'
import Header from '../components/header/Header'
import AnalyzeMain from '../components/analyze/AnalyzeMain'
import {CookieConsent} from '../components/common/CookieConsent'
import {fromJS} from 'immutable'
import useVolumeAnalysis from '../hooks/useVolumeAnalysis'
import {mountPage} from './mountPage'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import SocketListener from '../sse/SocketListener'
import {
  ANALYSIS_LAYOUT,
  deriveAnalysisView,
} from '../components/analyze/utils/deriveAnalysisView'
import {
  PAGE_TITLE,
  ProjectTreeAnalysis,
  SCROLL_CONTAINER_CLASS,
} from '../components/analyze/ProjectTreeAnalysis'

const AnalyzePage = () => {
  const jobAnalysis = !!config.jobAnalysis
  const {project, analysis} = useVolumeAnalysis()
  const containerRef = useRef()
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const view = useMemo(
    () => deriveAnalysisView({analysis, jobAnalysis}),
    [analysis, jobAnalysis],
  )
  // The other layouts still render the previous page until they are rebuilt
  const showTree =
    !!project &&
    !!analysis &&
    view.layout === ANALYSIS_LAYOUT.TREE &&
    view.jobs.length > 0

  useEffect(() => {
    if (showTree) document.title = `${PAGE_TITLE} - Matecat`
  }, [showTree])

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
        className={
          showTree ? `project-list ${SCROLL_CONTAINER_CLASS}` : 'project-list'
        }
        id="analyze-container"
        ref={containerRef}
      >
        {showTree ? (
          <div className="layout__container">
            <ProjectTreeAnalysis
              project={project}
              view={view}
              workflowType={
                analysis.workflow_type ?? project.analysis?.workflow_type
              }
            />
          </div>
        ) : (
          <AnalyzeMain
            project={fromJS(project)}
            volumeAnalysis={fromJS(analysis)}
            parentRef={containerRef}
          />
        )}
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
