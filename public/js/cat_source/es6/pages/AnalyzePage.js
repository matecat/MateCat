import React, {useEffect} from 'react'
import TeamsStore from '../stores/TeamsStore'
import Header from '../components/header/Header'
import AnalyzeMain from '../components/analyze/AnalyzeMain'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import {CookieConsent} from '../components/common/CookieConsent'
import {getJobVolumeAnalysis} from '../api/getJobVolumeAnalysis'
import {getProject} from '../api/getProject'
import {getVolumeAnalysis} from '../api/getVolumeAnalysis'
const AnalyzePage = () => {
  const getProjectVolumeAnalysisData = () => {
    if (config.jobAnalysis) {
      getJobVolumeAnalysis().then((response) => {
        const volumeAnalysis = response.data
        getProject(config.id_project).then((response) => {
          const project = response.project
        })
        self.pollData(response)
      })
    } else {
      getVolumeAnalysis().then((response) => {
        const volumeAnalysis = response.data
        getProject(config.id_project).then((response) => {
          const project = response.project
        })
        self.pollData(response)
      })
    }
  }
  useEffect(() => {
    getProjectVolumeAnalysisData()
  })
  return (
    <>
      <header>
        <Header
          loggedUser={config.isLoggedIn}
          showSubHeader={false}
          showModals={false}
          changeTeam={false}
          user={TeamsStore.getUser()}
        />
      </header>
      <div className="project-list" id="analyze-container">
        <AnalyzeMain jobsInfo={config.jobs} />
      </div>

      <div className="notifications-wrapper">
        <NotificationBox />
      </div>
      <footer>
        <CookieConsent />
      </footer>
    </>
  )
}

export default AnalyzePage
