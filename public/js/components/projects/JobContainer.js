import React, {useRef} from 'react'
import $ from 'jquery'
import JobMenu from './JobMenu'
import OutsourceContainer from '../outsource/OutsourceContainer'
import CommonUtils from '../../utils/commonUtils'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import ManageConstants from '../../constants/ManageConstants'
import ProjectsStore from '../../stores/ProjectsStore'
import {changeJobPassword} from '../../api/changeJobPassword'
import CatToolActions from '../../actions/CatToolActions'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import TranslatedIconSmall from '../../../img/icons/TranslatedIconSmall'
import Tooltip from '../common/Tooltip'
import JobProgressBar from '../common/JobProgressBar'
import {Popup} from 'semantic-ui-react'
import {DropdownMenu} from '../common/DropdownMenu/DropdownMenu'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'
import Download from '../../../img/icons/Download'
import QR from '../../../img/icons/QR'
import InfoIcon from '../../../img/icons/InfoIcon'
import AlertIcon from '../../../img/icons/AlertIcon'
import CommentsIcon from '../../../img/icons/CommentsIcon'

class JobContainer extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      showDownloadProgress: false,
      openOutsource: false,
      showTranslatorBox: false,
      extendedView: true,
    }

    this.getTranslateUrl = this.getTranslateUrl.bind(this)
    this.getAnalysisUrl = this.getAnalysisUrl.bind(this)
    this.changePassword = this.changePassword.bind(this)
    this.removeTranslator = this.removeTranslator.bind(this)
    this.downloadTranslation = this.downloadTranslation.bind(this)
    this.openMergeModal = this.openMergeModal.bind(this)
    this.openSplitModal = this.openSplitModal.bind(this)
    this.archiveJob = this.archiveJob.bind(this)
    this.activateJob = this.activateJob.bind(this)
    this.cancelJob = this.cancelJob.bind(this)
    this.deleteJob = this.deleteJob.bind(this)
  }

  getTranslateUrl() {
    let use_prefix = this.props.jobsLenght > 1
    let chunk_id =
      this.props.job.get('id') + (use_prefix ? '-' + this.props.index : '')
    return (
      '/translate/' +
      this.props.project.get('project_slug') +
      '/' +
      this.props.job.get('source') +
      '-' +
      this.props.job.get('target') +
      '/' +
      chunk_id +
      '-' +
      this.props.job.get('password') +
      (use_prefix ? '#' + this.props.job.get('job_first_segment') : '')
    )
  }

  getReviseUrl() {
    let use_prefix = this.props.jobsLenght > 1
    let chunk_id =
      this.props.job.get('id') + (use_prefix ? '-' + this.props.index : '')
    let possibly_different_review_password = this.props.job.has(
      'revise_passwords',
    )
      ? this.props.job.get('revise_passwords').get(0).get('password')
      : this.props.job.get('password')

    return (
      '/revise/' +
      this.props.project.get('project_slug') +
      '/' +
      this.props.job.get('source') +
      '-' +
      this.props.job.get('target') +
      '/' +
      chunk_id +
      '-' +
      possibly_different_review_password +
      (use_prefix ? '#' + this.props.job.get('job_first_segment') : '')
    )
  }

  getEditingLogUrl() {
    return (
      '/editlog/' +
      this.props.job.get('id') +
      '-' +
      this.props.job.get('password')
    )
  }

  getQAReport() {
    if (
      this.props.project.get('features') &&
      this.props.project.get('features').indexOf('review_improved') > -1
    ) {
      return (
        '/plugins/review_improved/quality_report/' +
        this.props.job.get('id') +
        '/' +
        this.props.job.get('password')
      )
    } else {
      return (
        '/revise-summary/' +
        this.props.job.get('id') +
        '-' +
        this.props.job.get('password')
      )
    }
  }

  changePassword(revision_number) {
    let self = this
    let label = ''
    switch (revision_number) {
      case undefined: {
        this.oldPassword = this.props.job.get('password')
        label = 'Translate'
        break
      }
      case 1: {
        this.oldPassword = this.props.job
          .get('revise_passwords')
          .get(0)
          .get('password')
        label = 'Revise'
        break
      }
      case 2: {
        this.oldPassword = this.props.job
          .get('revise_passwords')
          .get(1)
          .get('password')
        label = '2nd Revise'
        break
      }
    }
    changeJobPassword(
      this.props.job.toJS(),
      this.oldPassword,
      revision_number,
    ).then(function (data) {
      const notification = {
        uid: 'change-password',
        title: 'Change job ' + label + ' password',
        text:
          'The ' +
          label +
          ' password has been changed. <a class="undo-password">Undo</a>',
        type: 'warning',
        position: 'bl',
        allowHtml: true,
        timer: 10000,
      }
      CatToolActions.addNotification(notification)
      let translator = self.props.job.get('translator')
      ManageActions.changeJobPassword(
        self.props.project,
        self.props.job,
        data.new_pwd,
        data.old_pwd,
        revision_number,
      )
      setTimeout(function () {
        $('.undo-password').off('click')
        $('.undo-password').on('click', function () {
          CatToolActions.removeNotification(notification)
          changeJobPassword(
            self.props.job.toJS(),
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
              self.props.project,
              self.props.job,
              data.new_pwd,
              data.old_pwd,
              revision_number,
              translator,
            )
          })
        })
      }, 500)
    })
  }

  removeTranslator() {
    let self = this
    this.oldPassword = this.props.job.get('password')
    changeJobPassword(this.props.job.toJS(), this.oldPassword).then(
      function (data) {
        const notification = {
          uid: 'remove-translator',
          title: 'Job unassigned',
          text: 'The translator has been removed and the password changed. <a class="undo-password">Undo</a>',
          type: 'warning',
          position: 'bl',
          allowHtml: true,
          timer: 10000,
        }
        CatToolActions.addNotification(notification)
        let translator = self.props.job.get('translator')
        ManageActions.changeJobPassword(
          self.props.project,
          self.props.job,
          data.new_pwd,
          data.old_pwd,
          null,
          null,
        )
        setTimeout(function () {
          $('.undo-password').off('click')
          $('.undo-password').on('click', function () {
            CatToolActions.removeNotification(notification)
            changeJobPassword(
              self.props.job.toJS(),
              data.new_pwd,
              null,
              1,
              self.oldPassword,
            ).then(function (data) {
              const passwordNotification = {
                uid: 'change-password',
                title: 'Change job password',
                text: 'The previous password has been restored.',
                type: 'warning',
                position: 'bl',
                timer: 7000,
              }
              CatToolActions.addNotification(passwordNotification)
              ManageActions.changeJobPassword(
                self.props.project,
                self.props.job,
                data.new_pwd,
                data.old_pwd,
                null,
                translator,
              )
            })
          })
        }, 500)
      },
    )
  }

  archiveJob() {
    ManageActions.changeJobStatus(this.props.project, this.props.job, 'archive')
  }

  cancelJob() {
    ManageActions.changeJobStatus(this.props.project, this.props.job, 'cancel')
  }

  activateJob() {
    ManageActions.changeJobStatus(this.props.project, this.props.job, 'active')
  }

  deleteJob() {
    const props = {
      text:
        'You are about to delete this job permanently. This action cannot be undone.</br>' +
        ' Are you sure you want to proceed?',
      successText: 'Yes, delete it',
      successCallback: () => {
        ManageActions.changeJobStatus(
          this.props.project,
          this.props.job,
          'delete',
        )
      },
      cancelCallback: () => {},
    }
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      props,
      'Confirmation required',
    )
  }

  downloadTranslation() {
    let url = this.getTranslateUrl() + '?action=warnings'
    this.props.downloadTranslationFn(
      this.props.project.toJS(),
      this.props.job.toJS(),
      url,
    )
  }

  disableDownloadMenu(idJob) {
    if (this.props.job.get('id') === idJob) {
      this.setState({
        showDownloadProgress: true,
      })
    }
  }

  enableDownloadMenu(idJob) {
    if (this.props.job.get('id') === idJob) {
      this.setState({
        showDownloadProgress: false,
      })
    }
  }

  openSplitModal() {
    ModalsActions.openSplitJobModal(
      this.props.job,
      this.props.project,
      ManageActions.reloadProjects,
    )
  }

  openMergeModal() {
    ModalsActions.openMergeModal(
      this.props.project.toJS(),
      this.props.job.toJS(),
      ManageActions.reloadProjects,
    )
  }

  getDownloadLabel() {
    const stats = this.props.job.get('stats').toJS()
    let jobTranslated = stats.raw.draft === 0 && stats.raw.new === 0
    let remoteService = this.props.project.get('remote_file_service')
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
      this.downloadTranslation()
    }
    if (jobTranslated && !remoteService) {
      label = (
        <>
          <Download size={18} /> Download Translation
        </>
      )
      action = this.downloadTranslation
    } else if (jobTranslated && remoteService === 'gdrive') {
      label = (
        <>
          <Download size={18} /> Open in Google Drive
        </>
      )
      action = this.downloadTranslation
    } else if (remoteService && remoteService === 'gdrive') {
      label = (
        <>
          <Download size={18} /> Preview in Google Drive
        </>
      )
      action = this.downloadTranslation
    }
    return {label, action}
  }

  getJobMenu() {
    let jobTMXUrl =
      '/api/v2/tmx/' +
      this.props.job.get('id') +
      '/' +
      this.props.job.get('password')
    let exportXliffUrl =
      '/api/v2/xliff/' +
      this.props.job.get('id') +
      '/' +
      this.props.job.get('password') +
      '/' +
      this.props.project.get('project_slug') +
      '.zip'

    let originalUrl = `/api/v2/original/${this.props.job.get('id')}/${this.props.job.get('password')}`
    return (
      <JobMenu
        jobId={this.props.job.get('id')}
        review_password={this.props.job.get('review_password')}
        project={this.props.project}
        job={this.props.job}
        isChunk={this.props.isChunk}
        status={this.props.job.get('status')}
        isChunkOutsourced={this.props.isChunkOutsourced}
        reviseUrl={this.getReviseUrl()}
        editingLogUrl={this.getEditingLogUrl()}
        qAReportUrl={this.getQAReport()}
        jobTMXUrl={jobTMXUrl}
        exportXliffUrl={exportXliffUrl}
        originalUrl={originalUrl}
        getDownloadLabel={this.getDownloadLabel()}
        openSplitModalFn={this.openSplitModal}
        openMergeModalFn={this.openMergeModal}
        changePasswordFn={this.changePassword}
        archiveJobFn={this.archiveJob}
        activateJobFn={this.activateJob}
        cancelJobFn={this.cancelJob}
        deleteJobFn={this.deleteJob}
        disableDownload={this.state.showDownloadProgress}
      />
    )
  }

  getAnalysisUrl() {
    return (
      '/jobanalysis/' +
      this.props.project.get('id') +
      '-' +
      this.props.job.get('id') +
      '-' +
      this.props.job.get('password')
    )
  }

  getProjectAnalyzeUrl() {
    return (
      '/analyze/' +
      this.props.project.get('project_slug') +
      '/' +
      this.props.project.get('id') +
      '-' +
      this.props.project.get('password')
    )
  }

  openTMPanel() {
    ManageActions.openJobTMPanel(
      this.props.job.toJS(),
      this.props.project.get('project_slug'),
    )
  }

  getCommentsIcon() {
    let icon = ''
    let openThreads = this.props.job.get('open_threads_count')
    if (openThreads > 0) {
      let tooltipText = ''
      if (this.props.job.get('open_threads_count') === 1) {
        tooltipText = 'There is an open thread'
      } else {
        tooltipText =
          'There are <span style="font-weight: bold">' +
          openThreads +
          '</span> open threads'
      }
      var translatedUrl = this.getTranslateUrl() + '?action=openComments'
      icon = (
        <div className="comments-icon-container activity-icon-single">
          <Popup
            content={tooltipText}
            size="tiny"
            trigger={
              <a
                className=" ui icon basic button comments-tooltip"
                href={translatedUrl}
                target="_blank"
                rel="noreferrer"
              >
                <CommentsIcon />
              </a>
            }
          />
        </div>
      )
    }
    return icon
  }

  getQRIcon() {
    var icon = ''
    var quality = this.props.job.get('quality_summary').get('quality_overall')
    if (quality === 'poor' || quality === 'fail') {
      var url = this.getQAReport()
      let tooltipText = 'Overall quality: ' + quality.toUpperCase()
      var classQuality = quality === 'poor' ? 'yellow' : 'red'
      icon = (
        <div className="qreport-icon-container activity-icon-single">
          <Popup
            content={tooltipText}
            position="top center"
            size="tiny"
            trigger={
              <a
                className="ui icon basic button qr-tooltip "
                href={url}
                target="_blank"
                rel="noreferrer"
                style={{...(classQuality && {color: classQuality})}}
              >
                <QR />
              </a>
            }
          />
        </div>
      )
    }
    return icon
  }

  getWarningsIcon() {
    var icon = ''
    var warnings = this.props.job.get('warnings_count')
    if (warnings > 0) {
      var url = this.getTranslateUrl() + '?action=warnings'
      let tooltipText = 'Click to see issues'
      icon = (
        <div className="warnings-icon-container activity-icon-single">
          <Popup
            content={tooltipText}
            position="top center"
            size="tiny"
            trigger={
              <a
                className="ui icon basic button warning-tooltip"
                href={url}
                target="_blank"
                rel="noreferrer"
                style={{color: 'red'}}
              >
                <AlertIcon />
              </a>
            }
          />
        </div>
      )
    }
    return icon
  }

  openOutsourceModal = (showTranslatorBox, extendedView) => {
    if (showTranslatorBox && !this.props.job.get('outsource_available')) {
      this.setState({
        showTranslatorBox: showTranslatorBox,
        extendedView: false,
      })
    } else if (this.props.job.get('outsource_available')) {
      if (!this.state.openOutsource) {
        const data = {
          event: 'outsource_request',
        }
        CommonUtils.dispatchAnalyticsEvents(data)
      }
      this.setState({
        openOutsource: true,
        showTranslatorBox: showTranslatorBox,
        extendedView: extendedView,
      })
    } else {
      window.open('https://translated.com/contact-us', '_blank')
    }
  }

  closeOutsourceModal() {
    this.setState({
      openOutsource: false,
      showTranslatorBox: false,
      extendedView: false,
    })
  }

  getOutsourceButton() {
    return (
      <OutsourceButton
        job={this.props.job}
        openOutsourceModal={this.openOutsourceModal}
      />
    )
  }

  getOutsourceJobSent() {
    let outsourceJobLabel = ''
    if (this.props.job.get('outsource')) {
      if (this.props.job.get('outsource').get('id_vendor') == '1') {
        outsourceJobLabel = (
          <a
            className="outsource-logo-box"
            href={this.props.job.get('outsource').get('quote_review_link')}
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
    } else if (this.props.job.get('translator')) {
      outsourceJobLabel = undefined
    } else {
      outsourceJobLabel = (
        <div className="job-to-translator not-assigned" data-variation="tiny">
          <a
            id="open-quote-request"
            onClick={this.openOutsourceModal.bind(this, true, false)}
          >
            Assign job to translator
          </a>
        </div>
      )
    }
    return outsourceJobLabel
  }

  getOutsourceDelivery() {
    const gmtDate =
      this.props.job.get('outsource') &&
      this.props.job.get('outsource').get('id_vendor') == '1'
        ? CommonUtils.getGMTDate(
            this.props.job.get('outsource').get('delivery_timestamp') * 1000,
          )
        : this.props.job.get('translator') &&
          CommonUtils.getGMTDate(
            this.props.job.get('translator').get('delivery_timestamp') * 1000,
          )

    return (
      gmtDate && (
        <div className="outsource-delivery-container">
          <div className="job-delivery-date" title="Delivery date">
            {this.props.job.get('translator') && (
              <div
                className="job-delivery-email"
                onClick={this.openOutsourceModal.bind(this, true, false)}
              >
                {this.props.job.get('translator').get('email')}
              </div>
            )}{' '}
            <span>
              {gmtDate.day} {gmtDate.month} {gmtDate.time}
            </span>{' '}
            {gmtDate.gmt}
          </div>
        </div>
      )
    )
  }

  getOutsourceDeliveryPrice() {
    let outsourceDeliveryPrice = ''
    if (this.props.job.get('outsource')) {
      if (this.props.job.get('outsource').get('id_vendor') == '1') {
        let price = this.props.job.get('outsource').get('price')
        outsourceDeliveryPrice = (
          <div className="job-price">
            <span className="valuation">
              {this.props.job.get('outsource').get('currency')}{' '}
            </span>
            <span className="price">{price}</span>
          </div>
        )
      }
    }
    return outsourceDeliveryPrice
  }

  getWarningsGroup() {
    const iconsBody = (
      <>
        {this.getQRIcon()}
        {this.getWarningsIcon()}
        {this.getCommentsIcon()}
      </>
    )

    return (
      <div className="job-activity-icons" data-testid="job-activity-icons">
        {iconsBody}
      </div>
    )
  }

  shouldComponentUpdate(nextProps, nextState) {
    if (
      !nextProps.job.equals(this.props.job) ||
      nextState.showDownloadProgress !== this.state.showDownloadProgress ||
      nextState.openOutsource !== this.state.openOutsource ||
      nextState.showTranslatorBox !== this.state.showTranslatorBox
    ) {
      this.updated = true
    }
    return (
      !nextProps.job.equals(this.props.job) ||
      nextProps.lastAction !== this.props.lastAction ||
      nextState.showDownloadProgress !== this.state.showDownloadProgress ||
      nextState.openOutsource !== this.state.openOutsource ||
      nextState.showTranslatorBox !== this.state.showTranslatorBox ||
      nextProps.isChecked !== this.props.isChecked ||
      nextProps.isCheckboxVisible !== this.props.isCheckboxVisible
    )
  }

  componentDidUpdate(prevProps, prevState) {
    var self = this
    if (this.updated && this.container?.classList) {
      this.container.classList.add('updated-job')
      setTimeout(function () {
        self.container.classList.remove('updated-job')
      }, 500)
      self.updated = false
    }
    if (prevState.openOutsource && this.chunkRow) {
      setTimeout(function () {
        $('.after-open-outsource').removeClass('after-open-outsource')
        self.chunkRow.classList.add('after-open-outsource')
      }, 400)
    }
  }

  componentDidMount() {
    ProjectsStore.addListener(
      ManageConstants.ENABLE_DOWNLOAD_BUTTON,
      this.enableDownloadMenu.bind(this),
    )
    ProjectsStore.addListener(
      ManageConstants.DISABLE_DOWNLOAD_BUTTON,
      this.disableDownloadMenu.bind(this),
    )
  }

  componentWillUnmount() {
    ProjectsStore.removeListener(
      ManageConstants.ENABLE_DOWNLOAD_BUTTON,
      this.enableDownloadMenu,
    )
    ProjectsStore.removeListener(
      ManageConstants.DISABLE_DOWNLOAD_BUTTON,
      this.disableDownloadMenu,
    )
  }

  render() {
    let translateUrl = this.getTranslateUrl()
    let outsourceButton = this.getOutsourceButton()
    let outsourceJobLabel = this.getOutsourceJobSent()
    let outsourceDelivery = this.getOutsourceDelivery()
    // let outsourceDeliveryPrice = this.getOutsourceDeliveryPrice();
    let analysisUrl = this.getProjectAnalyzeUrl()
    let warningIcons = this.getWarningsGroup()
    let jobMenu = this.getJobMenu()
    let outsourceClass = this.props.job.get('outsource')
      ? 'outsource'
      : 'translator'

    let idJobLabel = !this.props.isChunk
      ? this.props.job.get('id')
      : this.props.job.get('id') + '-' + this.props.index
    const stats = this.props.job.get('stats').toJS()

    return (
      <div className="sixteen wide column chunk-container">
        {!this.state.openOutsource ? (
          <div
            className="ui grid"
            ref={(container) => (this.container = container)}
          >
            <div
              className="chunk wide column pad-right-10"
              ref={(chunkRow) => (this.chunkRow = chunkRow)}
            >
              <Checkbox
                className={
                  !this.props.isCheckboxVisible
                    ? 'project-container-checkbox-hidden'
                    : ''
                }
                onChange={() =>
                  this.props.onCheckedJob(this.props.job.get('id'))
                }
                value={
                  this.props.isChecked
                    ? CHECKBOX_STATE.CHECKED
                    : CHECKBOX_STATE.UNCHECKED
                }
              />
              <div className="job-id" title="Job Id">
                ID: {idJobLabel}
              </div>
              <Popup
                size="tiny"
                hoverable
                content={
                  this.props.job.get('sourceTxt') +
                  ' > ' +
                  this.props.job.get('targetTxt')
                }
                trigger={
                  <div className="source-target languages-tooltip">
                    <div className="source-box" data-testid="source-label">
                      {this.props.job.get('sourceTxt')}
                    </div>
                    <div className="in-to">
                      <i className="icon-chevron-right icon" />
                    </div>
                    <div className="target-box" data-testid="target-label">
                      {this.props.job.get('targetTxt')}
                    </div>
                  </div>
                }
              />

              <JobProgressBar stats={stats} />
              <div className="job-payable">
                <a href={analysisUrl} target="_blank" rel="noreferrer">
                  <span id="words">{Math.round(stats.raw.total)}</span> words
                </a>
              </div>
              {warningIcons}
              <div className="outsource-job">
                <div className={'translated-outsourced ' + outsourceClass}>
                  {outsourceJobLabel}
                  {outsourceDelivery}
                  {/*{outsourceDeliveryPrice}*/}
                  {this.props.job.get('translator') ? (
                    <div
                      className="item"
                      onClick={this.removeTranslator}
                      data-testid="remove-translator-button"
                    >
                      <div className="ui cancel label">
                        <i className="icon-cancel3" />
                      </div>
                    </div>
                  ) : (
                    ''
                  )}
                </div>
              </div>
              {outsourceButton}
              <Button
                type={BUTTON_TYPE.PRIMARY}
                onClick={() => window.open(translateUrl, '_blank')}
              >
                Open
              </Button>
              {jobMenu}

              {this.state.showDownloadProgress ? (
                <div className="chunk-download-progress" />
              ) : (
                ''
              )}
            </div>
          </div>
        ) : null}
        <OutsourceContainer
          project={this.props.project}
          job={this.props.job}
          url={this.getTranslateUrl()}
          showTranslatorBox={this.state.showTranslatorBox}
          extendedView={this.state.extendedView}
          onClickOutside={this.closeOutsourceModal.bind(this)}
          openOutsource={this.state.openOutsource}
          idJobLabel={idJobLabel}
          standardWC={Math.round(parseFloat(stats.equivalent.total))}
        />
      </div>
    )
  }
}

const OutsourceButton = ({job, openOutsourceModal}) => {
  const outsourceButton = useRef()
  if (!config.enable_outsource) {
    return null
  }
  const outsourceInfo = job.get('outsource_info')
    ? job.get('outsource_info').toJS()
    : undefined
  let label =
    !job.get('outsource_available') && outsourceInfo?.custom_payable_rate ? (
      <div>
        <Button
          // className="open-outsource open-outsource-disabled buy-translation"
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
          // className="open-outsource buy-translation "
          id="open-quote-request"
          onClick={openOutsourceModal.bind(this, false, true)}
          data-testid="buy-translation-button"
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
          id="open-quote-request"
          onClick={openOutsourceModal.bind(this, false, true)}
        >
          View status
        </Button>
      )
    }
  }
  return label
}
export default JobContainer
