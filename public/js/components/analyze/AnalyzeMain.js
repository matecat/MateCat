import React, {useEffect, useState} from 'react'
import {debounce} from 'lodash/function'
import $ from 'jquery'
import AnalyzeHeader from './AnalyzeHeader'
import AnalyzeChunksResume from './AnalyzeChunksResume'
import ProjectAnalyze from './ProjectAnalyze'
import {Button} from '../common/Button/Button'

const AnalyzeMain = ({volumeAnalysis, project}) => {
  const [intervalId, setIntervalId] = useState()
  const [scrollTop, setScrollTop] = useState()
  const [jobToScroll, setJobToScroll] = useState()

  const spinnerContainer = {
    position: 'absolute',
    height: '100%',
    width: '100%',
    // backgroundColor: 'rgba(76, 69, 69, 0.3)',
    // top: $(parentRef.current).scrollTop(),
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

  const openAnalysisReport = (idJob) => {
    setJobToScroll(idJob)
  }

  /*  const scrollStep = () => {
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
      parentRef.current &&
        parentRef.current.removeEventListener('scroll', handleScroll)
    }
  })*/

  return (
    <div className="layout__container">
      {volumeAnalysis && project ? (
        <div className="project">
          <h4>Volume Analysis</h4>
          <AnalyzeHeader
            data={volumeAnalysis.get('summary')}
            project={project}
          />
          {volumeAnalysis.get('jobs').size > 0 ? (
            <>
              <AnalyzeChunksResume
                jobsAnalysis={volumeAnalysis.get('jobs').toJS()}
                project={project}
                status={volumeAnalysis.get('summary').get('status')}
                openAnalysisReport={openAnalysisReport}
              />
              <div className="project-body">
                <ProjectAnalyze
                  volumeAnalysis={volumeAnalysis.get('jobs')}
                  project={project}
                  status={volumeAnalysis.get('summary').get('status')}
                  jobToScroll={jobToScroll}
                />
              </div>
            </>
          ) : null}
          {/*{scrollTop > 200 ? (
            <Button
              title="Back to top"
              className="scroll"
              onClick={() => scrollToTop()}
            >
              <i className="icon-sort-up icon"></i>
            </Button>
          ) : null}*/}
        </div>
      ) : (
        spinner
      )}
    </div>
  )
}

export default AnalyzeMain
