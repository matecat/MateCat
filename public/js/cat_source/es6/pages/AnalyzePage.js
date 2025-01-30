import React, {useContext, useEffect, useRef, useState} from 'react'
import UserStore from '../stores/UserStore'
import Header from '../components/header/Header'
import AnalyzeMain from '../components/analyze/AnalyzeMain'
import {CookieConsent} from '../components/common/CookieConsent'
import {getJobVolumeAnalysis} from '../api/getJobVolumeAnalysis'
import {getProject} from '../api/getProject'
import {getVolumeAnalysis} from '../api/getVolumeAnalysis'
import {fromJS} from 'immutable'
import {ANALYSIS_STATUS} from '../constants/Constants'
import {mountPage} from './mountPage'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper'
import SocketListener from '../sse/SocketListener'

let pollingTime = 1000
const segmentsThreshold = 50000

const AnalyzePage = () => {
  const [project, setProject] = useState()
  const [volumeAnalysis, setVolumeAnalysis] = useState()
  const containerRef = useRef()
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const getProjectVolumeAnalysisData = () => {
    if (config.jobAnalysis) {
      getJobVolumeAnalysis().then((response) => {
        //TODO Temp fix to filter only the requested job
        let filteredJob = response.jobs.find((job) =>
          job.chunks.find((chunk) => chunk.password === config.jpassword),
        )
        filteredJob.chunks = filteredJob.chunks.filter(
          (chunk) => chunk.password === config.jpassword,
        )
        const volumeAnalysis = {...response, jobs: [filteredJob]}
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
          volumeAnalysis={fromJS(volumeAnalysis)}
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
  rootElement: document.getElementsByClassName('analyze-page')[0],
})
