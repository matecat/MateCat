import React, {useContext, useRef} from 'react'
import UserStore from '../stores/UserStore'
import Header from '../components/header/Header'
import AnalyzeMain from '../components/analyze/AnalyzeMain'
import {CookieConsent} from '../components/common/CookieConsent'
import {fromJS} from 'immutable'
import useVolumeAnalysis from '../hooks/useVolumeAnalysis'
import {mountPage} from './mountPage'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import SocketListener from '../sse/SocketListener'

const AnalyzePage = () => {
  const {project, analysis} = useVolumeAnalysis()
  const containerRef = useRef()
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)
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
      <div className="project-list" id="analyze-container" ref={containerRef}>
        <AnalyzeMain
          project={fromJS(project)}
          volumeAnalysis={fromJS(analysis)}
          parentRef={containerRef}
        />
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
