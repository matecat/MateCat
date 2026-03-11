import React, {memo} from 'react'

import JobAnalyze from './JobAnalyze'

const ProjectAnalyze = memo(
  ({project, volumeAnalysis, status, jobToScroll}) => {
    const getJobs = () => {
      const idArray = []
      return project.get('jobs').map((job) => {
        const jobInfo = volumeAnalysis.find(
          (item) => item.get('id') === job.get('id'),
        )
        if (idArray.indexOf(job.get('id')) < 0 && volumeAnalysis && jobInfo) {
          idArray.push(job.get('id'))
          return (
            <JobAnalyze
              key={job.get('password')}
              chunks={jobInfo.get('chunks')}
              project={project}
              idJob={job.get('id')}
              jobInfo={jobInfo.toJS()}
              status={status}
              jobToScroll={jobToScroll}
            />
          )
        }
      })
    }

    return getJobs()
  },
  (prevProps, nextProps) =>
    nextProps.volumeAnalysis.equals(prevProps.volumeAnalysis) &&
    nextProps.status === prevProps.status &&
    nextProps.jobToScroll === prevProps.jobToScroll,
)

ProjectAnalyze.displayName = 'ProjectAnalyze'

export default ProjectAnalyze
