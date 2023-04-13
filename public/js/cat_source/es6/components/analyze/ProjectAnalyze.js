import React from 'react'
import _ from 'lodash'

import JobAnalyze from './JobAnalyze'

class ProjectAnalyze extends React.Component {
  constructor(props) {
    super(props)
  }

  getJobs() {
    let idArray = []
    return this.props.project.get('jobs').map((job) => {
      if (
        idArray.indexOf(job.get('id')) < 0 &&
        !_.isUndefined(this.props.jobsInfo[job.get('id').toString()])
      ) {
        let jobVolumeAnalysisChunk = this.props.volumeAnalysis
          .get(job.get('id').toString())
          .get('chunks')
        let jobVolumeAnalysisTotal = this.props.volumeAnalysis
          .get(job.get('id').toString())
          .get('totals')
        idArray.push(job.get('id'))
        return (
          <JobAnalyze
            key={job.get('password')}
            chunks={jobVolumeAnalysisChunk}
            total={jobVolumeAnalysisTotal}
            project={this.props.project}
            idJob={job.get('id')}
            jobInfo={this.props.jobsInfo[job.get('id')]}
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
