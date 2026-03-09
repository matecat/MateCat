import React, {useRef, useState, useEffect, useCallback} from 'react'
import ModalsActions from '../../actions/ModalsActions'
import CommonUtils from '../../utils/commonUtils'
import {ANALYSIS_STATUS} from '../../constants/Constants'
import UserStore from '../../stores/UserStore'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import CompareTableHeader from './CompareTableHeader'
import SingleChunkJob from './SingleChunkJob'

const AnalyzeChunksResume = ({
  project,
  status,
  jobsAnalysis,
  idJob,
  openAnalysisReport,
}) => {
  const payableValues = useRef({})
  const payableValuesChanged = useRef({})
  const containers = useRef({})
  const jobLinkRef = useRef({})

  const [openOutsource, setOpenOutsource] = useState(false)
  const [outsourceJobId, setOutsourceJobId] = useState(null)

  const showDetails = useCallback(
    (jobId) => (evt) => {
      if (!evt.target.closest('.outsource-container')) {
        evt.preventDefault()
        evt.stopPropagation()
        openAnalysisReport(jobId, true)
      }
    },
    [openAnalysisReport],
  )

  const openSplitModal = useCallback(
    (id) => (e) => {
      e.stopPropagation()
      e.preventDefault()
      const job = project.get('jobs').find((item) => item.get('id') === id)
      ModalsActions.openSplitJobModal(job, project, () =>
        window.location.reload(),
      )
    },
    [project],
  )

  const openMergeModal = useCallback(
    (id) => (e) => {
      e.stopPropagation()
      e.preventDefault()
      const job = project.get('jobs').find((item) => item.get('id') === id)
      ModalsActions.openMergeModal(project.toJS(), job.toJS(), () =>
        window.location.reload(),
      )
    },
    [project],
  )

  const thereIsChunkOutsourced = useCallback(() => {
    const outsourceChunk = project
      .get('jobs')
      .find((item) => !!item.get('outsource') && item.get('id') === idJob)
    return outsourceChunk !== undefined
  }, [project, idJob])

  const handleOpenOutsourceModal = useCallback(
    (jobId, chunk) => (e) => {
      e.stopPropagation()
      e.preventDefault()
      if (status !== ANALYSIS_STATUS.DONE) return

      CommonUtils.dispatchAnalyticsEvents({event: 'outsource_request'})
      if (chunk.outsource_available) {
        setOpenOutsource(true)
        setOutsourceJobId(jobId)
      } else {
        window.open('https://translated.com/contact-us', '_blank')
      }
    },
    [status],
  )

  const closeOutsourceModal = useCallback(() => {
    setOpenOutsource(false)
    setOutsourceJobId(null)
  }, [])

  const checkPayableChanged = useCallback((jobId, payable) => {
    if (
      payableValues.current[jobId] &&
      payable !== payableValues.current[jobId]
    ) {
      payableValuesChanged.current[jobId] = true
    }
    payableValues.current[jobId] = payable
  }, [])

  const copyJobLinkToClipboard = useCallback(
    (jid) => (e) => {
      e.stopPropagation()
      const url = jobLinkRef.current[jid]?.value
      if (url) {
        navigator.clipboard.writeText(url)
      }
    },
    [],
  )

  const goToTranslate = useCallback((chunk, index, e) => {
    e.preventDefault()
    e.stopPropagation()
    const key = `first_translate_click${config.id_project}`
    if (!sessionStorage.getItem(key)) {
      const userInfo = UserStore.getUser()
      if (userInfo) {
        CommonUtils.dispatchAnalyticsEvents({
          event: 'open_job',
          userStatus: 'loggedUser',
          userId: userInfo.user.uid,
          idProject: parseInt(config.id_project),
        })
        sessionStorage.setItem(key, 'true')
      }
    }
    window.open(chunk.urls.t, '_blank')
  }, [])

  const getDirectOpenButton = useCallback(
    (chunk, index) => (
      <Button
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.SMALL}
        className="open-translate"
        disabled={status !== ANALYSIS_STATUS.DONE}
        onClick={(e) => goToTranslate(chunk, index, e)}
      >
        Translate
      </Button>
    ),
    [status, goToTranslate],
  )

  const workflowType = project.get('analysis').get('workflow_type')
  const countUnit = jobsAnalysis?.[0]?.count_unit

  const sharedProps = {
    project,
    status,
    openOutsource,
    outsourceJobId,
    showDetails,
    checkPayableChanged,
    copyJobLinkToClipboard,
    getDirectOpenButton,
    closeOutsourceModal,
    handleOpenOutsourceModal,
    jobLinkRef,
    containers,
  }

  const renderJobs = () => {
    if (!jobsAnalysis) {
      return project.get('jobs').map((jobInfo, indexJob) => (
        <div key={`${jobInfo.get('id')}-${indexJob}`}>
          <CompareTableHeader
            countUnit={countUnit}
            workflowType={workflowType}
            jobInfo={jobInfo}
          />
          <div>
            <div>
              <div>
                <div>
                  <div>
                    <div>
                      <div>0</div>
                    </div>
                    <div>
                      <div>0</div>
                    </div>
                    <div>
                      <div>0</div>
                    </div>
                  </div>
                  <div />
                </div>
              </div>
            </div>
          </div>
        </div>
      ))
    }

    return jobsAnalysis.map((job, indexJob) => {
      const isSplit = job.chunks.length > 1

      return (
        <div key={indexJob} className="project-card">
          <CompareTableHeader
            countUnit={countUnit}
            workflowType={workflowType}
            isSplit={isSplit}
            openSplitModal={openSplitModal}
            openMergeModal={openMergeModal}
            job={job}
            thereIsChunkOutsourced={thereIsChunkOutsourced}
            {...sharedProps}
          />
          <SingleChunkJob
            job={job}
            thereIsChunkOutsourced={thereIsChunkOutsourced}
            {...sharedProps}
          />
        </div>
      )
    })
  }

  // Animate payable value changes on update
  useEffect(() => {
    const changed = payableValuesChanged.current
    const changedKeys = Object.keys(changed).filter((key) => changed[key])

    if (changedKeys.length > 0) {
      changedKeys.forEach((key) => {
        const el = containers.current[key]
        if (el) {
          el.classList.add('updated-count')
          setTimeout(() => el.classList.remove('updated-count'), 400)
        }
      })
    }
  })

  // Initial animation on mount when analysis is done
  useEffect(() => {
    if (status === 'DONE') {
      Object.entries(containers.current).forEach(([, el]) => {
        if (el) {
          el.classList.add('updated-count')
          setTimeout(() => el.classList.remove('updated-count'), 400)
        }
      })
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className={`project-top type-${workflowType}`}>{renderJobs()}</div>
  )
}

export default AnalyzeChunksResume
