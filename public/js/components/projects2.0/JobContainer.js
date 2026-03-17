import PropTypes from 'prop-types'
import React, {useRef, useState} from 'react'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'
import JobMenu from '../projects/JobMenu'
import Download from '../../../img/icons/Download'
import CommonUtils from '../../utils/commonUtils'
import ModalsActions from '../../actions/ModalsActions'
import ManageActions from '../../actions/ManageActions'
import {changeJobPassword} from '../../api/changeJobPassword'
import CatToolActions from '../../actions/CatToolActions'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import IconDown from '../icons/IconDown'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import TranslatedIconSmall from '../../../img/icons/TranslatedIconSmall'
import JobProgressBar from '../common/JobProgressBar'
import Tooltip from '../common/Tooltip'
import QR from '../../../img/icons/QR'
import AlertIcon from '../../../img/icons/AlertIcon'
import CommentsIcon from '../../../img/icons/CommentsIcon'

export const JobContainer = ({
  jobsLength,
  job,
  project,
  isChunk,
  isChecked,
  isChunkOutsourced,
  onCheckedJob,
  downloadTranslationFn,
  index,
}) => {
  const [showDownloadProgress, setShowDownloadProgress] = useState(false)

  const qrIconRef = useRef()
  const warningsIconRef = useRef()
  const commentsIconRef = useRef()

  const idJobLabel = !isChunk ? job.get('id') : job.get('id') + '-' + index

  const getReviseUrl = () => {
    const use_prefix = jobsLength > 1
    const chunk_id = job.get('id') + (use_prefix ? '-' + index : '')
    const possibly_different_review_password = job.has('revise_passwords')
      ? job.get('revise_passwords').get(0).get('password')
      : job.get('password')

    return (
      '/revise/' +
      project.get('project_slug') +
      '/' +
      job.get('source') +
      '-' +
      job.get('target') +
      '/' +
      chunk_id +
      '-' +
      possibly_different_review_password +
      (use_prefix ? '#' + job.get('job_first_segment') : '')
    )
  }

  const getEditingLogUrl = () => {
    return '/editlog/' + job.get('id') + '-' + job.get('password')
  }

  const getQAReport = () => {
    if (
      project.get('features') &&
      project.get('features').indexOf('review_improved') > -1
    ) {
      return (
        '/plugins/review_improved/quality_report/' +
        job.get('id') +
        '/' +
        job.get('password')
      )
    } else {
      return '/revise-summary/' + job.get('id') + '-' + job.get('password')
    }
  }

  const downloadTranslation = () => {
    const url = getTranslateUrl() + '?action=warnings'
    downloadTranslationFn(project.toJS(), job.toJS(), url)
  }

  const getDownloadLabel = () => {
    const stats = job.get('stats').toJS()
    const jobTranslated = stats.raw.draft === 0 && stats.raw.new === 0
    const remoteService = project.get('remote_file_service')
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

  const openSplitModal = () => {
    ModalsActions.openSplitJobModal(job, project, ManageActions.reloadProjects)
  }

  const openMergeModal = () => {
    ModalsActions.openMergeModal(
      project.toJS(),
      job.toJS(),
      ManageActions.reloadProjects,
    )
  }

  const changePassword = (revision_number) => {
    let oldPassword

    switch (revision_number) {
      case undefined: {
        oldPassword = job.get('password')
        break
      }
      case 1: {
        oldPassword = job.get('revise_passwords').get(0).get('password')
        break
      }
      case 2: {
        oldPassword = job.get('revise_passwords').get(1).get('password')
        break
      }
    }
    changeJobPassword(job.toJS(), oldPassword, revision_number).then(
      function (data) {
        const notification = {
          uid: 'change-password',
          title: revision_number
            ? `${revision_number === 1 ? 'Revise' : 'Revise 2'} password changed`
            : 'Translate password changed',
          text: revision_number
            ? `The ${revision_number === 1 ? 'Revise' : 'Revise 2'} password has been changed. <a class="undo-password">Undo</a>`
            : 'The Translate password has been changed. <a class="undo-password">Undo</a>',
          type: 'warning',
          position: 'bl',
          allowHtml: true,
          timer: 10000,
        }
        CatToolActions.addNotification(notification)
        let translator = job.get('translator')
        ManageActions.changeJobPassword(
          project,
          job,
          data.new_pwd,
          data.old_pwd,
          revision_number,
        )
        setTimeout(function () {
          $('.undo-password').off('click')
          $('.undo-password').on('click', function () {
            CatToolActions.removeNotification(notification)
            changeJobPassword(
              job.toJS(),
              data.new_pwd,
              revision_number,
              1,
              self.oldPassword,
            ).then(function (data) {
              const restoreNotification = {
                title: 'Change job password',
                text: 'The previous password has been restored.',
                type: 'warning',
                position: 'bl',
                timer: 7000,
              }
              CatToolActions.addNotification(restoreNotification)
              ManageActions.changeJobPassword(
                project,
                job,
                data.new_pwd,
                data.old_pwd,
                revision_number,
                translator,
              )
            })
          })
        }, 500)
      },
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

  const getTranslateUrl = () => {
    const use_prefix = jobsLength > 1
    const chunk_id = job.get('id') + (use_prefix ? '-' + index : '')
    return (
      '/translate/' +
      project.get('project_slug') +
      '/' +
      job.get('source') +
      '-' +
      job.get('target') +
      '/' +
      chunk_id +
      '-' +
      job.get('password') +
      (use_prefix ? '#' + job.get('job_first_segment') : '')
    )
  }

  const getProjectAnalyzeUrl = () => {
    return (
      '/analyze/' +
      project.get('project_slug') +
      '/' +
      project.get('id') +
      '-' +
      project.get('password')
    )
  }

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

  const getJobMenu = () => {
    const jobTMXUrl = '/api/v2/tmx/' + job.get('id') + '/' + job.get('password')
    const exportXliffUrl =
      '/api/v2/xliff/' +
      job.get('id') +
      '/' +
      job.get('password') +
      '/' +
      project.get('project_slug') +
      '.zip'

    const originalUrl = `/api/v2/original/${job.get('id')}/${job.get('password')}`

    return (
      <JobMenu
        jobId={job.get('id')}
        review_password={job.get('review_password')}
        project={project}
        job={job}
        isChunk={isChunk}
        status={job.get('status')}
        isChunkOutsourced={isChunkOutsourced}
        reviseUrl={getReviseUrl()}
        editingLogUrl={getEditingLogUrl()}
        qAReportUrl={getQAReport()}
        jobTMXUrl={jobTMXUrl}
        exportXliffUrl={exportXliffUrl}
        originalUrl={originalUrl}
        getDownloadLabel={getDownloadLabel()}
        openSplitModalFn={openSplitModal}
        openMergeModalFn={openMergeModal}
        changePasswordFn={changePassword}
        archiveJobFn={archiveJob}
        activateJobFn={activateJob}
        cancelJobFn={cancelJob}
        deleteJobFn={deleteJob}
        disableDownload={showDownloadProgress}
      />
    )
  }

  const openOutsourceModal = (showTranslatorBox, extendedView) => {
    //   if (showTranslatorBox && !job.get('outsource_available')) {
    //     setState({
    //       showTranslatorBox: showTranslatorBox,
    //       extendedView: false,
    //     })
    //   } else if (job.get('outsource_available')) {
    //     if (!state.openOutsource) {
    //       const data = {
    //         event: 'outsource_request',
    //       }
    //       CommonUtils.dispatchAnalyticsEvents(data)
    //     }
    //     setState({
    //       openOutsource: true,
    //       showTranslatorBox: showTranslatorBox,
    //       extendedView: extendedView,
    //     })
    //   } else {
    //     window.open('https://translated.com/contact-us', '_blank')
    //   }
  }

  const getQRIcon = () => {
    const quality = job.get('quality_summary').get('quality_overall')
    if (quality === 'poor' || quality === 'fail') {
      const url = getQAReport()
      const tooltipText = 'Overall quality: ' + quality?.toUpperCase()
      const classQuality = quality === 'poor' ? 'yellow' : 'red'
      return (
        <Tooltip content={tooltipText}>
          <Button
            ref={qrIconRef}
            type={BUTTON_TYPE.ICON}
            size={BUTTON_SIZE.ICON_XSMALL}
            onClick={() => window.open(url, '_blank')}
            style={{...(classQuality && {color: classQuality})}}
          >
            <QR />
          </Button>
        </Tooltip>
      )
    }
  }

  const getWarningsIcon = () => {
    const warnings = job.get('warnings_count')
    if (warnings > 0) {
      const url = getTranslateUrl() + '?action=warnings'
      let tooltipText = 'Click to see issues'
      return (
        <Tooltip content={tooltipText}>
          <Button
            ref={warningsIconRef}
            type={BUTTON_TYPE.ICON}
            size={BUTTON_SIZE.ICON_XSMALL}
            onClick={() => window.open(url, '_blank')}
            style={{color: 'red'}}
          >
            <AlertIcon />
          </Button>
        </Tooltip>
      )
    }
  }

  const getCommentsIcon = () => {
    const openThreads = job.get('open_threads_count')
    if (openThreads > 0) {
      const tooltipText =
        job.get('open_threads_count') === 1
          ? 'There is an open thread'
          : `There are  ${openThreads} open threads`

      var translatedUrl = getTranslateUrl() + '?action=openComments'
      return (
        <Tooltip content={tooltipText}>
          <Button
            ref={commentsIconRef}
            type={BUTTON_TYPE.ICON}
            size={BUTTON_SIZE.ICON_XSMALL}
            onClick={() => window.open(translatedUrl, '_blank')}
          >
            <CommentsIcon />
          </Button>
        </Tooltip>
      )
    }
  }

  const getWarningsGroup = () => {
    const iconsBody = (
      <>
        {getQRIcon()}
        {getWarningsIcon()}
        {getCommentsIcon()}
      </>
    )

    return (
      <div className="job-activity-icons" data-testid="job-activity-icons">
        {iconsBody}
      </div>
    )
  }

  const getOutsourceJobSent = () => {
    let outsourceJobElement = ''
    if (job.get('outsource')) {
      if (job.get('outsource').get('id_vendor') == '1') {
        outsourceJobElement = (
          <a
            className="outsource-logo-box"
            href={job.get('outsource').get('quote_review_link')}
            target="_blank"
            rel="noreferrer"
          >
            <img
              className="outsource-logo"
              src="/public/img/matecat-logo-translated.svg"
              title="Outsourced to translated.net"
              alt="Translated logo"
            />
          </a>
        )
      }
    } else if (job.get('translator')) {
      outsourceJobElement = undefined
    } else {
      outsourceJobElement = (
        <Button
          className="job-container-words-button"
          onClick={openOutsourceModal.bind(this, true, false)}
        >
          Assign job to translator
        </Button>
      )
    }
    return outsourceJobElement
  }

  const stats = job.get('stats').toJS()

  return (
    <div className={`job-container ${isChunk ? 'chunk-job-container' : ''}`}>
      {!isChunk && (
        <Checkbox
          onChange={() => onCheckedJob(job.get('id'))}
          value={isChecked ? CHECKBOX_STATE.CHECKED : CHECKBOX_STATE.UNCHECKED}
        />
      )}

      <div>
        <div className="job-container-id" title="Job Id">
          {!isChunk && (
            <span className="job-languages-codes">
              {job.get('source')}
              <IconDown size={16} />
              {job.get('target')}
            </span>
          )}
          ID: {idJobLabel}
        </div>
      </div>
      <div>
        <JobProgressBar stats={stats} />
      </div>
      <div>
        <Button
          className="job-container-words-button"
          onClick={() => window.open(getProjectAnalyzeUrl(), '_blank')}
        >
          Words: {Math.round(stats.raw.total)}{' '}
          <span>({Math.round(stats.equivalent.total)})</span>
        </Button>
      </div>
      <div>{getWarningsGroup()}</div>
      <div className="job-container-outsource">{getOutsourceJobSent()}</div>
      <div>
        <Button
          className="job-container-translation-button"
          id="open-quote-request"
          onClick={openOutsourceModal.bind(this, false, true)}
          data-testid="buy-translation-button"
        >
          <TranslatedIconSmall size={20} />
          Buy Translation from
        </Button>
      </div>
      <div>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.SMALL}
          onClick={() => window.open(getTranslateUrl(), '_blank')}
        >
          Open
        </Button>
      </div>
      <div>{getJobMenu()}</div>
    </div>
  )
}

JobContainer.propTypes = {
  jobsLength: PropTypes.number.isRequired,
  job: PropTypes.object.isRequired,
  project: PropTypes.object.isRequired,
  isChunk: PropTypes.bool.isRequired,
  isChecked: PropTypes.bool.isRequired,
  onCheckedJob: PropTypes.func.isRequired,
  index: PropTypes.number.isRequired,
}
