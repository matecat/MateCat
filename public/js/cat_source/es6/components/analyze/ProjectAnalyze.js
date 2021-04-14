import JobAnalyze from './JobAnalyze'

class ProjectAnalyze extends React.Component {
  constructor(props) {
    super(props)
  }

  getJobs() {
    var self = this
    let idArray = []
    return this.props.project.get('jobs').map(function (job, i) {
      if (
        idArray.indexOf(job.get('id')) < 0 &&
        !_.isUndefined(self.props.jobsInfo[job.get('id')])
      ) {
        let jobVolumeAnalysisChunk = self.props.volumeAnalysis
          .get(job.get('id').toString())
          .get('chunks')
        let jobVolumeAnalysisTotal = self.props.volumeAnalysis
          .get(job.get('id').toString())
          .get('totals')
        idArray.push(job.get('id'))
        return (
          <JobAnalyze
            key={job.get('password')}
            chunks={jobVolumeAnalysisChunk}
            total={jobVolumeAnalysisTotal}
            project={self.props.project}
            idJob={job.get('id')}
            jobInfo={self.props.jobsInfo[job.get('id')]}
            status={self.props.status}
          />
        )
      }
    })
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      !nextProps.volumeAnalysis.equals(this.props.volumeAnalysis) ||
      nextProps.status !== this.props.status
    )
  }

  render() {
    return <div className="jobs sixteen wide column">{this.getJobs()}</div>
  }
}

export default ProjectAnalyze
