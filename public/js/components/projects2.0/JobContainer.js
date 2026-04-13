import PropTypes from 'prop-types'
import React, {useEffect, useRef, useState} from 'react'
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
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import TranslatedIconSmall from '../../../img/icons/TranslatedIconSmall'
import JobProgressBar from '../common/JobProgressBar'
import Tooltip from '../common/Tooltip'
import QR from '../../../img/icons/QR'
import AlertIcon from '../../../img/icons/AlertIcon'
import CommentsIcon from '../../../img/icons/CommentsIcon'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'
import OutsourceContainer from '../outsource/OutsourceContainer'
import {fromJS} from 'immutable'

export const JobContainer = ({
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
  const [showingOutsource, setShowingOutsource] = useState()

  const qrIconRef = useRef()
  const warningsIconRef = useRef()
  const commentsIconRef = useRef()
  const sourceTargetTextRef = useRef()
  const deliveryDateRef = useRef()

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

  const idJobLabel = !isChunk ? job.get('id') : job.get('id') + '-' + index

  const getReviseUrl = () => {
    const use_prefix = project.get('jobs').size > 1 && isChunk
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

  const removeTranslator = () => {}

  const getTranslateUrl = () => {
    const use_prefix = project.get('jobs').size > 1 && isChunk
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
        downloadLabel={getDownloadLabel()}
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
    if (
      (showTranslatorBox && !job.get('outsource_available')) ||
      job.get('outsource_available')
    ) {
      if (
        job.get('outsource_available') &&
        typeof showingOutsource !== 'undefined'
      ) {
        const data = {
          event: 'outsource_request',
        }
        CommonUtils.dispatchAnalyticsEvents(data)
      }
      setShowingOutsource({showTranslatorBox, extendedView})
    } else {
      window.open('https://translated.com/contact-us', '_blank')
    }
  }

  const closeOutsourceModal = () => setShowingOutsource()

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

  const getJobOutsourceMock = () => {
    if (job.get('id') === 93)
      return fromJS({
        outsource: {
          vendor_name: 'Translated',
          quote_pid: 1005529538,
          price: 8.8,
          delivery_timestamp: 1774967400,
          id_vendor: 1,
          currency: 'EUR',
          create_date: '2026-03-31 11:10:22',
          create_timestamp: 1774948222,
          delivery_date: '2026-03-31 16:30:00',
          id_job: 12202606,
          password: '93fc11c85000',
          quote_review_link:
            'https://www.translated.net/int/ots.php?pid=1005529538',
        },
        translator: null,
      })
    else return job
  }

  const getOutsourceJobSent = () => {
    const job = getJobOutsourceMock()

    let outsourceJobElement = ''
    if (job.get('outsource')) {
      if (job.get('outsource').get('id_vendor') == '1') {
        outsourceJobElement = (
          <a
            className="job-container-outsource-logo"
            href={job.get('outsource').get('quote_review_link')}
            target="_blank"
            rel="noreferrer"
          >
            <img
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
          className="job-container-button-weight-normal"
          onClick={() => openOutsourceModal(true, false)}
        >
          Assign
        </Button>
      )
    }
    return outsourceJobElement
  }

  const getOutsourceDelivery = () => {
    const job = getJobOutsourceMock()

    const gmtDate =
      job.get('outsource') && job.get('outsource').get('id_vendor') == '1'
        ? CommonUtils.getGMTDate(
            job.get('outsource').get('delivery_timestamp') * 1000,
          )
        : job.get('translator') &&
          CommonUtils.getGMTDate(
            job.get('translator').get('delivery_timestamp') * 1000,
          )

    return (
      gmtDate && (
        <div className="outsource-delivery-container">
          <div className="job-delivery-date">
            {job.get('translator') && (
              <div
                className="job-delivery-email"
                onClick={() => openOutsourceModal(true, false)}
              >
                {job.get('translator').get('email')}
              </div>
            )}{' '}
            <Tooltip
                content={`${gmtDate.day} ${gmtDate.month} ${gmtDate.year} - ${gmtDate.time} ${gmtDate.gmt}`}
              >
            <span ref={deliveryDateRef}>
              {gmtDate.day} {gmtDate.month} - {gmtDate.time}
            </span>
            </Tooltip>
          </div>
        </div>
      )
    )
  }

  const getOutsourceButton = () => {
    const job = getJobOutsourceMock()

    if (!config.enable_outsource) return

    const outsourceInfo = job.get('outsource_info')
      ? job.get('outsource_info').toJS()
      : undefined
    let label =
      !job.get('outsource_available') && outsourceInfo?.custom_payable_rate ? (
        <div>
          <Button
            size={BUTTON_SIZE.SMALL}
            className="job-container-button-weight-normal"
            id="open-quote-request"
            data-testid="buy-translation-button"
            disabled={true}
            tooltip={
              "Jobs created with custom billing models cannot be outsourced to Translated.<br />In order to outsource this job to Translated, please recreate it using Matecat's standard billing model"
            }
          >
            Buy Translation from
            <TranslatedIconSmall size={20} />
          </Button>
        </div>
      ) : (
        <div>
          <Button
            size={BUTTON_SIZE.SMALL}
            className="job-container-button-weight-normal"
            id="open-quote-request"
            onClick={() => openOutsourceModal(false, true)}
            data-testid="buy-translation-button"
            disabled={
              !job.get('outsource_available') &&
              job.get('outsource_info')?.toJS()?.custom_payable_rate
            }
          >
            Buy Translation from
            <TranslatedIconSmall size={20} />
          </Button>
        </div>
      )
    if (job.get('outsource')) {
      if (job.get('outsource').get('id_vendor') == '1') {
        label = (
          <Button
            type={BUTTON_TYPE.DEFAULT}
            mode={BUTTON_MODE.OUTLINE}
            size={BUTTON_SIZE.SMALL}
            id="open-quote-request"
            onClick={() => openOutsourceModal(false, true)}
          >
            View status
          </Button>
        )
      }
    }
    return label
  }

  const stats = job.get('stats').toJS()

  return (
    <div className="job-container">
      <div
        className={`job-container-grid ${isChunk ? 'chunk-job-container' : ''}`}
      >
        {!isChunk && (
          <Checkbox
            onChange={() => onCheckedJob(job.get('id'))}
            value={
              isChecked ? CHECKBOX_STATE.CHECKED : CHECKBOX_STATE.UNCHECKED
            }
          />
        )}

        <div>
          <div className="job-container-id">
            {!isChunk && (
              <Tooltip
                content={`${job.get('sourceTxt')} - ${job.get('targetTxt')}`}
              >
                <span ref={sourceTargetTextRef} className="job-languages-code">
                  {job.get('source')}
                  <IconDown size={16} />
                  {job.get('target')}
                </span>
              </Tooltip>
            )}
            ID: {idJobLabel}
          </div>
        </div>
        <div>
          <JobProgressBar stats={stats} />
        </div>
        <div>
          <Button
            tooltip={`Total: ${Math.round(stats.equivalent.total)} / Weighted: ${Math.round(stats.equivalent.total)}`}
            className="job-container-button-weight-normal job-container-words-button"
            onClick={() => window.open(getProjectAnalyzeUrl(), '_blank')}
          >
            Words: {Math.round(stats.raw.total)}{' '}
            <span>({Math.round(stats.equivalent.total)})</span>
          </Button>
        </div>
        <div>{getWarningsGroup()}</div>
        <div className="job-container-outsource">
          {getOutsourceJobSent()}
          {getOutsourceDelivery()}
          {job.get('translator') && (
            <div
              className="item"
              onClick={removeTranslator}
              data-testid="remove-translator-button"
            >
              <div className="ui cancel label">
                <i className="icon-cancel3" />
              </div>
            </div>
          )}
        </div>
        <div>{getOutsourceButton()}</div>
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
      {typeof showingOutsource !== 'undefined' && (
        <div className="job-container-outsource-container">
          <OutsourceContainer
            project={project}
            job={job}
            standardWC={Math.round(parseFloat(stats.equivalent.total))}
            showTranslatorBox={showingOutsource.showTranslatorBox}
            extendedView={showingOutsource.extendedView}
            onClickOutside={closeOutsourceModal}
            openOutsource={showingOutsource}
            idJobLabel={job.id}
          />
        </div>
      )}
    </div>
  )
}

JobContainer.propTypes = {
  job: PropTypes.object.isRequired,
  project: PropTypes.object.isRequired,
  isChunk: PropTypes.bool.isRequired,
  isChecked: PropTypes.bool.isRequired,
  onCheckedJob: PropTypes.func.isRequired,
  index: PropTypes.number,
}
