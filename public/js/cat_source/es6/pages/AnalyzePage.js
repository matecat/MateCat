import React, {useEffect, useRef, useState} from 'react'
import TeamsStore from '../stores/TeamsStore'
import Header from '../components/header/Header'
import AnalyzeMain from '../components/analyze/AnalyzeMain'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import {CookieConsent} from '../components/common/CookieConsent'
import {getJobVolumeAnalysis} from '../api/getJobVolumeAnalysis'
import {getProject} from '../api/getProject'
import {getVolumeAnalysis} from '../api/getVolumeAnalysis'
import Immutable from 'immutable'
import {createRoot} from 'react-dom/client'
import {ANALYSIS_STATUS} from '../constants/Constants'

let pollingTime = 1000
const segmentsThreshold = 50000

const AnalyzePage = () => {
  const [project, setProject] = useState()
  const [volumeAnalysis, setVolumeAnalysis] = useState()
  const containerRef = useRef()
  const getProjectVolumeAnalysisData = () => {
    if (config.jobAnalysis) {
      getJobVolumeAnalysis().then((response) => {
        const volumeAnalysis = response
        getProject(config.id_project).then((response) => {
          const project = response.project
          setProject(project)
          setVolumeAnalysis(volumeAnalysis)
        })
        pollData(response)
      })
    } else {
      getVolumeAnalysis().then((response) => {
        const volumeAnalysis = response
        getProject(config.id_project).then((response) => {
          const project = response.project
          setProject(project)
          setVolumeAnalysis(volumeAnalysis)
        })
        pollData(response)
      })
    }
  }
  const pollData = (response) => {
    if (
      response.summary.status !== ANALYSIS_STATUS.DONE &&
      response.summary.status !== ANALYSIS_STATUS.NOT_TO_ANALYZE
    ) {
      if (response.summary.total_segments > segmentsThreshold) {
        pollingTime = response.summary.total_segments / 20
      }

      setTimeout(function () {
        getVolumeAnalysis().then((response) => {
          setVolumeAnalysis(response)
          if (
            response.summary.status === ANALYSIS_STATUS.DONE ||
            response.summary.status === ANALYSIS_STATUS.NOT_TO_ANALYZE
          ) {
            getProject(config.id_project).then((response) => {
              if (response.project) {
                setProject(response.project)
              }
            })
          } else {
            pollData(response)
          }
        })
      }, pollingTime)
    }
  }
  useEffect(() => {
    getProjectVolumeAnalysisData()
  }, [])
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
      <div className="project-list" id="analyze-container" ref={containerRef}>
        <AnalyzeMain
          project={Immutable.fromJS(project)}
          volumeAnalysis={Immutable.fromJS(volumeAnalysis)}
          parentRef={containerRef}
        />
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
document.addEventListener('DOMContentLoaded', () => {
  const analyzePage = createRoot(
    document.getElementsByClassName('analyze-page')[0],
  )
  analyzePage.render(React.createElement(AnalyzePage))
})
