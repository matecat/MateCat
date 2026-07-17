import React from 'react'
import {isUndefined} from 'lodash'

import JobAnalyze from './JobAnalyze'

class ProjectAnalyze extends React.Component {
  constructor(props) {
    super(props)
  }

  getJobs() {
    let idArray = []
    return this.props.project.get('jobs').map((job) => {
      const jobInfo = this.props.volumeAnalysis.find(
        (item) => item.get('id') === job.get('id'),
      )
      if (
        idArray.indexOf(job.get('id')) < 0 &&
        this.props.volumeAnalysis &&
        jobInfo
      ) {
        idArray.push(job.get('id'))
        return (
          <JobAnalyze
            key={job.get('password')}
            chunks={jobInfo.get('chunks')}
            project={this.props.project}
            idJob={job.get('id')}
            jobInfo={jobInfo.toJS()}
            status={this.props.status}
            jobToScroll={this.props.jobToScroll}
            showAnalysis={this.props.showAnalysis}
          />
        )
      }
    })
  }

  shouldComponentUpdate(nextProps) {
    return (
      !nextProps.volumeAnalysis.equals(this.props.volumeAnalysis) ||
      nextProps.status !== this.props.status ||
      nextProps.jobToScroll !== this.props.jobToScroll
    )
  }

  render() {
    return <div className="jobs sixteen wide column">{this.getJobs()}</div>
  }
}

export default ProjectAnalyze
