import React from 'react'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import AnalyzeConstants from '../../constants/AnalyzeConstants'
import AnalyzeActions from '../../actions/AnalyzeActions'
import AnalyzeHeader from './AnalyzeHeader'
import AnalyzeChunksResume from './AnalyzeChunksResume'
import ProjectAnalyze from './ProjectAnalyze'
import AnalyzeStore from '../../stores/AnalyzeStore'

class AnalyzeMain extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      volumeAnalysis: null,
      project: null,
      showAnalysis: false,
      intervalId: 0,
      scrollTop: 0,
    }
    this.updateAll = this.updateAll.bind(this)
    this.updateAnalysis = this.updateAnalysis.bind(this)
    this.updateProject = this.updateProject.bind(this)
    this.showDetails = this.showDetails.bind(this)
  }

  updateAll(volumeAnalysis, project) {
    this.setState({
      volumeAnalysis: volumeAnalysis,
      project: project,
    })
  }

  updateAnalysis(volumeAnalysis) {
    this.setState({
      volumeAnalysis: volumeAnalysis,
    })
  }

  updateProject(project) {
    this.setState({
      project: project,
    })
  }

  openAnalysisReport() {
    this.setState({
      showAnalysis: !this.state.showAnalysis,
    })
  }

  showDetails(idJob) {
    if (!this.state.showAnalysis) {
      this.setState({
        showAnalysis: true,
      })
      setTimeout(function () {
        AnalyzeActions.showDetails(idJob)
      }, 500)
    }
  }

  scrollStep() {
    if (window.pageYOffset === 0) {
      clearInterval(this.state.intervalId)
    }
    window.scroll(0, window.pageYOffset - 50)
  }

  scrollToTop() {
    let intervalId = setInterval(this.scrollStep.bind(this), 16.6)
    this.setState({intervalId: intervalId})
  }

  handleScroll() {
    let self = this
    self.setState({scrollTop: $(window).scrollTop()})
  }

  componentDidUpdate() {}

  componentDidMount() {
    window.addEventListener(
      'scroll',
      _.debounce(this.handleScroll.bind(this), 200),
    )
    AnalyzeStore.addListener(AnalyzeConstants.RENDER_ANALYSIS, this.updateAll)
    AnalyzeStore.addListener(
      AnalyzeConstants.UPDATE_ANALYSIS,
      this.updateAnalysis,
    )
    AnalyzeStore.addListener(
      AnalyzeConstants.UPDATE_PROJECT,
      this.updateProject,
    )
    AnalyzeStore.addListener(AnalyzeConstants.SHOW_DETAILS, this.showDetails)
  }

  componentWillUnmount() {
    window.removeEventListener('scroll', this.handleScroll.bind(this))
    AnalyzeStore.removeListener(
      AnalyzeConstants.RENDER_ANALYSIS,
      this.updateAll,
    )
    AnalyzeStore.removeListener(
      AnalyzeConstants.UPDATE_ANALYSIS,
      this.updateAnalysis,
    )
    AnalyzeStore.removeListener(
      AnalyzeConstants.UPDATE_PROJECT,
      this.updateProject,
    )
    AnalyzeStore.removeListener(AnalyzeConstants.SHOW_DETAILS, this.showDetails)
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      !this.state.volumeAnalysis ||
      (nextState.project && !nextState.project.equals(this.state.project)) ||
      !nextState.volumeAnalysis.equals(this.state.volumeAnalysis) ||
      nextState.showAnalysis !== this.state.showAnalysis ||
      nextState.intervalId !== this.state.intervalId ||
      nextState.scrollTop !== this.state.scrollTop
    )
  }

  render() {
    var spinnerContainer = {
      position: 'absolute',
      height: '100%',
      width: '100%',
      backgroundColor: 'rgba(76, 69, 69, 0.3)',
      top: $(window).scrollTop(),
      left: 0,
      zIndex: 3,
    }
    var spinner = (
      <div style={spinnerContainer}>
        <div className="ui active inverted dimmer">
          <div className="ui massive text loader">Loading Volume Analysis</div>
        </div>
      </div>
    )
    return (
      <div className="ui container">
        {this.state.volumeAnalysis && this.state.project ? (
          <div className="project ui grid">
            <div className="sixteen wide column">
              <div className="analyze-header">
                <AnalyzeHeader
                  data={this.state.volumeAnalysis.get('summary')}
                  project={this.state.project}
                />
              </div>

              <AnalyzeChunksResume
                jobsAnalysis={this.state.volumeAnalysis.get('jobs')}
                jobsInfo={this.props.jobsInfo}
                project={this.state.project}
                status={this.state.volumeAnalysis.get('summary').get('STATUS')}
                openAnalysisReport={this.openAnalysisReport.bind(this)}
              />

              {this.state.showAnalysis ? (
                <div className="project-body ui grid">
                  <TransitionGroup>
                    <CSSTransition
                      key={0}
                      classNames="transitionAnalyzeMain"
                      timeout={{enter: 1000, exit: 300}}
                    >
                      <ProjectAnalyze
                        volumeAnalysis={this.state.volumeAnalysis.get('jobs')}
                        project={this.state.project}
                        jobsInfo={this.props.jobsInfo}
                        status={this.state.volumeAnalysis
                          .get('summary')
                          .get('STATUS')}
                      />
                    </CSSTransition>
                  </TransitionGroup>
                </div>
              ) : null}
            </div>
            {this.state.scrollTop > 200 ? (
              <button
                title="Back to top"
                className="scroll"
                onClick={this.scrollToTop.bind(this)}
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
}

export default AnalyzeMain
