import React, {useEffect, useState} from 'react'
import {debounce} from 'lodash/function'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import AnalyzeHeader from './AnalyzeHeader'
import AnalyzeChunksResume from './AnalyzeChunksResume'
import ProjectAnalyze from './ProjectAnalyze'

const AnalyzeMain = ({volumeAnalysis, project, parentRef}) => {
  const [showAnalysis, setShowAnalysis] = useState(false)
  const [intervalId, setIntervalId] = useState()
  const [scrollTop, setScrollTop] = useState()
  const [jobToScroll, setJobToScroll] = useState()

  const spinnerContainer = {
    position: 'absolute',
    height: '100%',
    width: '100%',
    backgroundColor: 'rgba(76, 69, 69, 0.3)',
    top: $(parentRef.current).scrollTop(),
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

  const openAnalysisReport = (idJob, forceOpen) => {
    setShowAnalysis((showAnalysis) => (forceOpen ? forceOpen : !showAnalysis))
    setJobToScroll(idJob)
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
    setScrollTop($(parentRef.current).scrollTop())
  }
  useEffect(() => {
    parentRef.current.addEventListener('scroll', debounce(handleScroll, 200))
    return () => {
      parentRef.current.removeEventListener('scroll', handleScroll)
    }
  })

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
            {volumeAnalysis.get('jobs').size > 0 ? (
              <>
                {' '}
                <AnalyzeChunksResume
                  jobsAnalysis={volumeAnalysis.get('jobs').toJS()}
                  project={project}
                  status={volumeAnalysis.get('summary').get('status')}
                  showAnalysis={showAnalysis}
                  openAnalysisReport={openAnalysisReport}
                />
                {showAnalysis ? (
                  <div className="project-body ui grid">
                    {/*<TransitionGroup>
                      <CSSTransition
                        key={0}
                        classNames="transitionAnalyzeMain"
                        timeout={{enter: 1000, exit: 300}}
                      >*/}
                    <ProjectAnalyze
                      volumeAnalysis={volumeAnalysis.get('jobs')}
                      project={project}
                      status={volumeAnalysis.get('summary').get('status')}
                      jobToScroll={jobToScroll}
                      showAnalysis={showAnalysis}
                    />
                    {/* </CSSTransition>
                    </TransitionGroup>*/}
                  </div>
                ) : null}
              </>
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
