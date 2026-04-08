import PropTypes from 'prop-types'
import React, {useEffect, useMemo, useRef, useState} from 'react'
import {JobContainer} from './JobContainer'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'
import IconDown from '../icons/IconDown'
import JobMenu from '../projects/JobMenu'
import Download from '../../../img/icons/Download'
import CommonUtils from '../../utils/commonUtils'
import ModalsActions from '../../actions/ModalsActions'
import ManageActions from '../../actions/ManageActions'
import CatToolActions from '../../actions/CatToolActions'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import Tooltip from '../common/Tooltip'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'

export const ChunksJobContainer = ({chunks, ...props}) => {
  const [showDownloadProgress, setShowDownloadProgress] = useState(false)

  const sourceTargetTextRef = useRef()

  const job = useMemo(() => chunks[0], [chunks])

  const {project} = props

  useEffect(() => {
    const disableDownloadMenu = (idJob) => {
      if (job.get('id') === idJob) {
        setShowDownloadProgress(true)
      }
    }

    const enableDownloadMenu = (idJob) => {
      if (job.get('id') === idJob) {
        setShowDownloadProgress(false)
      }
    }

    ProjectsStore.addListener(
      ManageConstants.ENABLE_DOWNLOAD_BUTTON,
      enableDownloadMenu,
    )
    ProjectsStore.addListener(
      ManageConstants.DISABLE_DOWNLOAD_BUTTON,
      disableDownloadMenu,
    )

    return () => {
      ProjectsStore.removeListener(
        ManageConstants.ENABLE_DOWNLOAD_BUTTON,
        enableDownloadMenu,
      )
      ProjectsStore.removeListener(
        ManageConstants.DISABLE_DOWNLOAD_BUTTON,
        disableDownloadMenu,
      )
    }
  }, [job])

  const getTranslateUrl = () => {
    return (
      '/translate/' +
      project.get('project_slug') +
      '/' +
      job.get('source') +
      '-' +
      job.get('target') +
      '/' +
      job.get('password')
    )
  }

  const downloadTranslation = () => {
    const url = getTranslateUrl() + '?action=warnings'
    props.downloadTranslationFn(project.toJS(), job.toJS(), url)
  }

  const getDownloadLabel = () => {
    const stats = job.get('stats').toJS()
    const jobTranslated = stats.raw.draft === 0 && stats.raw.new === 0
    const remoteService = props.project.get('remote_file_service')
    let label = (
      <>
        <Download size={18} /> Draft
      </>
    )
    let action = () => {
      const data = {
        event: 'download_draft',
      }
      CommonUtils.dispatchAnalyticsEvents(data)
      downloadTranslation()
    }
    if (jobTranslated && !remoteService) {
      label = (
        <>
          <Download size={18} /> Download Translation
        </>
      )
      action = downloadTranslation
    } else if (jobTranslated && remoteService === 'gdrive') {
      label = (
        <>
          <Download size={18} /> Open in Google Drive
        </>
      )
      action = downloadTranslation
    } else if (remoteService && remoteService === 'gdrive') {
      label = (
        <>
          <Download size={18} /> Preview in Google Drive
        </>
      )
      action = downloadTranslation
    }
    return {label, action}
  }

  const openMergeModal = () => {
    ModalsActions.openMergeModal(
      project.toJS(),
      job.toJS(),
      ManageActions.reloadProjects,
    )
  }

  const archiveJob = () => {
    ManageActions.changeJobStatus(project, job, 'archive')
    if (project.get('jobs').size > 1) {
      CatToolActions.addNotification({
        title: `Jobs archived`,
        text: `The selected jobs has been successfully archived.`,
        type: 'warning',
        position: 'bl',
        allowHtml: true,
        timer: 10000,
      })
    }
  }

  const activateJob = () => {
    ManageActions.changeJobStatus(project, job, 'active')
    if (project.get('jobs').size > 1) {
      CatToolActions.addNotification({
        title: `Jobs unarchived`,
        text: `The selected jobs has been successfully unarchived.`,
        type: 'warning',
        position: 'bl',
        allowHtml: true,
        timer: 10000,
      })
    }
  }

  const cancelJob = () => {
    ManageActions.changeJobStatus(project, job, 'cancel')
    if (project.get('jobs').size > 1) {
      CatToolActions.addNotification({
        title: `Jobs canceled`,
        text: `The selected jobs has been successfully canceled.`,
        type: 'warning',
        position: 'bl',
        allowHtml: true,
        timer: 10000,
      })
    }
  }

  const deleteJob = () => {
    const props = {
      text:
        'You are about to delete this job permanently. This action cannot be undone.</br>' +
        ' Are you sure you want to proceed?',
      successText: 'Yes, delete it',
      successCallback: () => {
        ManageActions.changeJobStatus(project, job, 'delete')
        if (project.get('jobs').size > 1) {
          CatToolActions.addNotification({
            title: `Jobs deleted permanently`,
            text: `The selected jobs has been successfully deleted permanently.`,
            type: 'warning',
            position: 'bl',
            allowHtml: true,
            timer: 10000,
          })
        }
      },
      cancelCallback: () => {},
    }
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      props,
      'Confirmation required',
    )
  }

  const getJobMenu = () => {
    const jobTMXUrl = '/api/v2/tmx/' + job.get('id') + '/' + job.get('password')
    const exportXliffUrl =
      '/api/v2/xliff/' +
      job.get('id') +
      '/' +
      job.get('password') +
      '/' +
      props.project.get('project_slug') +
      '.zip'

    const originalUrl = `/api/v2/original/${job.get('id')}/${job.get('password')}`

    return (
      <JobMenu
        jobId={job.get('id')}
        review_password={job.get('review_password')}
        project={props.project}
        job={job}
        isChunk={props.isChunk}
        isJobChunks={true}
        status={job.get('status')}
        isChunkOutsourced={props.isChunkOutsourced}
        jobTMXUrl={jobTMXUrl}
        exportXliffUrl={exportXliffUrl}
        originalUrl={originalUrl}
        getDownloadLabel={getDownloadLabel()}
        openMergeModalFn={openMergeModal}
        archiveJobFn={archiveJob}
        activateJobFn={activateJob}
        cancelJobFn={cancelJob}
        deleteJobFn={deleteJob}
        disableDownload={showDownloadProgress}
      />
    )
  }

  return (
    <div className="chunks-job-container">
      <div className="chunks-job-container-line">
        <div className="chunks-job-container-line-sx">
          <Checkbox
            onChange={() => props.onCheckedJob(job.get('id'))}
            value={
              props.isChecked
                ? CHECKBOX_STATE.CHECKED
                : CHECKBOX_STATE.UNCHECKED
            }
          />
          <Tooltip
            content={`${job.get('sourceTxt')} - ${job.get('targetTxt')}`}
          >
            <span ref={sourceTargetTextRef} className="job-languages-code">
              {job.get('source')}
              <IconDown size={16} />
              {job.get('target')}
            </span>
          </Tooltip>
        </div>
        <div className="chunks-job-container-line-dx">{getJobMenu()}</div>
      </div>
      <div className="chunks-job-container-list">
        {chunks.map((job, index) => (
          <JobContainer
            key={`${job.get('id')}-${index + 1}`}
            job={job}
            index={index + 1}
            {...props}
          />
        ))}
      </div>
    </div>
  )
}

ChunksJobContainer.propTypes = {
  chunks: PropTypes.array.isRequired,
}
