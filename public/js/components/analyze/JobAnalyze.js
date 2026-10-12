import React, {useCallback, useEffect, useRef} from 'react'
import {map} from 'lodash/collection'
import $ from 'jquery'
import JobAnalyzeHeader from './JobAnalyzeHeader'
import JobTableHeader from './JobTableHeader'
import ChunkAnalyze from './ChunkAnalyze'

const JobAnalyze = ({chunks, jobInfo, project, idJob, status, jobToScroll}) => {
  const containerRef = useRef(null)

  const scrollElement = useCallback(() => {
    const itemComponent = containerRef.current
    if (itemComponent) {
      $('#analyze-container').animate(
        {
          scrollTop: $(itemComponent).offset().top - 200,
        },
        500,
      )
    } else {
      setTimeout(() => scrollElement(), 500)
    }
  }, [])

  const showDetails = useCallback(() => {
    if (jobToScroll === idJob) {
      scrollElement()
    }
  }, [jobToScroll, idJob, scrollElement])

  useEffect(() => {
    const timer = setTimeout(() => showDetails())
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    showDetails()
  }, [jobToScroll, showDetails])

  const getChunks = () => {
    if (chunks) {
      return map(jobInfo.chunks, (item, index) => {
        let chunk = chunks.find((c) => c.get('password') === item.password)
        index++
        let job = project.get('jobs').find(function (jobElem) {
          return jobElem.get('password') === item.password
        })

        return (
          <ChunkAnalyze
            key={item.password}
            files={chunk.get('files').toJS()}
            job={job}
            project={project}
            total={item.summary}
            index={index}
            chunkInfo={item}
            chunksSize={jobInfo.chunks.length}
            rates={jobInfo.payable_rates}
            workflowType={project.get('analysis').get('workflow_type')}
          />
        )
      })
    }
    return ''
  }

  const iceMTRawWords = jobInfo.chunks.reduce((total, item) => {
    const iceMT = item.summary.find((t) => t.type === 'ice_mt')
    if (iceMT) return total + iceMT.raw
    else return total
  }, 0)

  return (
    <div className="job" ref={containerRef}>
      <JobAnalyzeHeader project={project} jobInfo={jobInfo} status={status} />
      <JobTableHeader
        rates={jobInfo.payable_rates}
        iceMTRawWords={iceMTRawWords}
        workflowType={project.get('analysis').get('workflow_type')}
      />
      <div className="chunks-analyze">{getChunks()}</div>
    </div>
  )
}

export default JobAnalyze
