import React, {useEffect, useState} from 'react'
import _ from 'lodash'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import AnalyzeConstants from '../../constants/AnalyzeConstants'
import AnalyzeActions from '../../actions/AnalyzeActions'
import AnalyzeHeader from './AnalyzeHeader'
import AnalyzeChunksResume from './AnalyzeChunksResume'
import ProjectAnalyze from './ProjectAnalyze'
import AnalyzeStore from '../../stores/AnalyzeStore'

const AnalyzeMain = ({}) => {
  const [volumeAnalysis, setVolumeAnalysis] = useState()
  const [project, setProject] = useState()
  const [showAnalysis, setShowAnalysis] = useState()
  const [intervalId, setIntervalId] = useState()
  const [scrollTop, setScrollTop] = useState()

  const jobsInfo = config.jobs

  const spinnerContainer = {
    position: 'absolute',
    height: '100%',
    width: '100%',
    backgroundColor: 'rgba(76, 69, 69, 0.3)',
    top: $(window).scrollTop(),
    left: 0,
    zIndex: 3,
  }

  const spinner = (
    <div style={spinnerContainer}>
      <div className="ui active inverted dimmer">
        <div className="ui massive text loader">Loading Volume Analysis</div>
      </div>
    </div>
  )

  // constructor(props) {
  //   super(props)
  //   this.state = {
  //     volumeAnalysis: null,
  //     project: null,
  //     showAnalysis: false,
  //     intervalId: 0,
  //     scrollTop: 0,
  //   }
  //   this.updateAll = this.updateAll.bind(this)
  //   this.updateAnalysis = this.updateAnalysis.bind(this)
  //   this.updateProject = this.updateProject.bind(this)
  //   this.showDetails = this.showDetails.bind(this)
  // }

  const updateAll = (volumeAnalysis, project) => {
    setProject(project)
    setVolumeAnalysis(volumeAnalysis)
  }

  const updateAnalysis = (volumeAnalysis) => {
    setVolumeAnalysis(volumeAnalysis)
  }

  const updateProject = (project) => {
    setProject(project)
  }

  const openAnalysisReport = () => {
    setShowAnalysis((prev) => !prev)
  }

  const showDetails = (idJob) => {
    if (showAnalysis) {
      setShowAnalysis(true)

      setTimeout(() => {
        AnalyzeActions.showDetails(idJob)
      }, 500)
    }
  }

  const scrollStep = () => {
    if (window.pageYOffset === 0) {
      clearInterval(intervalId)
    }
    window.scroll(0, window.pageYOffset - 50)
  }

  const scrollToTop = () => {
    let newIntervalId = setInterval(scrollStep, 16.6)
    setIntervalId(newIntervalId)
  }

  const handleScroll = () => {
    setScrollTop($(window).scrollTop())
  }
  useEffect(() => {
    window.addEventListener('scroll', _.debounce(handleScroll, 200))
    AnalyzeStore.addListener(AnalyzeConstants.RENDER_ANALYSIS, updateAll)
    AnalyzeStore.addListener(AnalyzeConstants.UPDATE_ANALYSIS, updateAnalysis)
    AnalyzeStore.addListener(AnalyzeConstants.UPDATE_PROJECT, updateProject)
    AnalyzeStore.addListener(AnalyzeConstants.SHOW_DETAILS, showDetails)
    return () => {
      window.removeEventListener('scroll', handleScroll)
      AnalyzeStore.removeListener(AnalyzeConstants.RENDER_ANALYSIS, updateAll)
      AnalyzeStore.removeListener(
        AnalyzeConstants.UPDATE_ANALYSIS,
        updateAnalysis,
      )
      AnalyzeStore.removeListener(
        AnalyzeConstants.UPDATE_PROJECT,
        updateProject,
      )
      AnalyzeStore.removeListener(AnalyzeConstants.SHOW_DETAILS, showDetails)
    }
  })

  // shouldComponentUpdate(nextProps, nextState) {
  //   return (
  //     !this.state.volumeAnalysis ||
  //     (nextState.project && !nextState.project.equals(this.state.project)) ||
  //     !nextState.volumeAnalysis.equals(this.state.volumeAnalysis) ||
  //     nextState.showAnalysis !== this.state.showAnalysis ||
  //     nextState.intervalId !== this.state.intervalId ||
  //     nextState.scrollTop !== this.state.scrollTop
  //   )
  // }

  return (
    <div className="ui container">
      {volumeAnalysis && project ? (
        <div className="project ui grid">
          <div className="sixteen wide column">
            <div className="analyze-header">
              <AnalyzeHeader
                data={volumeAnalysis.get('summary')}
                project={project}
              />
            </div>

            <AnalyzeChunksResume
              jobsAnalysis={volumeAnalysis.get('jobs')}
              jobsInfo={jobsInfo}
              project={project}
              status={volumeAnalysis.get('summary').get('STATUS')}
              openAnalysisReport={() => openAnalysisReport()}
            />

            {showAnalysis ? (
              <div className="project-body ui grid">
                <TransitionGroup>
                  <CSSTransition
                    key={0}
                    classNames="transitionAnalyzeMain"
                    timeout={{enter: 1000, exit: 300}}
                  >
                    <ProjectAnalyze
                      volumeAnalysis={volumeAnalysis.get('jobs')}
                      project={project}
                      jobsInfo={jobsInfo}
                      status={volumeAnalysis.get('summary').get('STATUS')}
                    />
                  </CSSTransition>
                </TransitionGroup>
              </div>
            ) : null}
          </div>
          {scrollTop > 200 ? (
            <button
              title="Back to top"
              className="scroll"
              onClick={() => scrollToTop()}
            >
              <i className="icon-sort-up icon"></i>
            </button>
          ) : null}
        </div>
      ) : (
        spinner
      )}
    </div>
  )
}

export default AnalyzeMain
