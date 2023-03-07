import React from 'react'
import _ from 'lodash'
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
        data.password,
        data.undo,
        revision_number,
      )
      setTimeout(function () {
        $('.undo-password').off('click')
        $('.undo-password').on('click', function () {
          CatToolActions.removeNotification(notification)
          changeJobPassword(
            self.props.job.toJS(),
            data.password,
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
              data.password,
              data.undo,
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
    changeJobPassword(this.props.job.toJS(), this.oldPassword).then(function (
      data,
    ) {
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
        data.password,
        data.undo,
        null,
      )
      setTimeout(function () {
        $('.undo-password').off('click')
        $('.undo-password').on('click', function () {
          CatToolActions.removeNotification(notification)
          changeJobPassword(
            self.props.job.toJS(),
            data.password,
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
              data.password,
              data.undo,
              null,
              translator,
            )
          })
        })
      }, 500)
    })
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
      $(this.downloadMenu).addClass('disabled')
      this.setState({
        showDownloadProgress: true,
      })
    }
  }

  enableDownloadMenu(idJob) {
    if (this.props.job.get('id') === idJob) {
      $(this.downloadMenu).removeClass('disabled')
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
    let jobStatus = CommonUtils.getTranslationStatus(
      this.props.job.get('stats').toJS(),
    )
    let remoteService = this.props.project.get('remote_file_service')
    let label = (
      <a
        className="item"
        onClick={this.downloadTranslation}
        ref={(downloadMenu) => (this.downloadMenu = downloadMenu)}
      >
        <i className="icon-eye icon" /> Draft
      </a>
    )
    if (
      (jobStatus === 'translated' || jobStatus === 'approved') &&
      !remoteService
    ) {
      label = (
        <a
          className="item"
          onClick={this.downloadTranslation}
          ref={(downloadMenu) => (this.downloadMenu = downloadMenu)}
        >
          <i className="icon-download icon" /> Download Translation
        </a>
      )
    } else if (
      (jobStatus === 'translated' || jobStatus === 'approved') &&
      remoteService === 'gdrive'
    ) {
      label = (
        <a
          className="item"
          onClick={this.downloadTranslation}
          ref={(downloadMenu) => (this.downloadMenu = downloadMenu)}
        >
          <i className="icon-download icon" /> Open in Google Drive
        </a>
      )
    } else if (remoteService && remoteService === 'gdrive') {
      label = (
        <a
          className="item"
          onClick={this.downloadTranslation}
          ref={(downloadMenu) => (this.downloadMenu = downloadMenu)}
        >
          <i className="icon-eye icon" /> Preview in Google Drive
        </a>
      )
    }
    return label
  }

  getJobMenu() {
    let jobTMXUrl =
      '/TMX/' + this.props.job.get('id') + '/' + this.props.job.get('password')
    let exportXliffUrl =
      '/SDLXLIFF/' +
      this.props.job.get('id') +
      '/' +
      this.props.job.get('password') +
      '/' +
      this.props.project.get('project_slug') +
      '.zip'

    let originalUrl =
      '/?action=downloadOriginal&id_job=' +
      this.props.job.get('id') +
      ' &password=' +
      this.props.job.get('password') +
      '&download_type=all'
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

  getTMIcon() {
    if (this.props.job.get('private_tm_key').size) {
      let keys = this.props.job.get('private_tm_key')
      let tooltipText = ''
      keys.forEach(function (key) {
        let descript = key.get('name') ? key.get('name') : 'Private resource'
        let item =
          '<div style="text-align: left"><span style="font-weight: bold">' +
          descript +
          '</span> (' +
          key.get('key') +
          ')</div>'
        tooltipText = tooltipText + item
      })
      return (
        <a
          className=" ui icon basic button tm-keys"
          data-html={tooltipText}
          data-variation="tiny"
          ref={(tooltip) => (this.tmTooltip = tooltip)}
          onClick={this.openTMPanel.bind(this)}
          data-testid="tm-button"
        >
          <i className="icon-tm-matecat icon" />
        </a>
      )
    } else {
      return ''
    }
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
          <a
            className=" ui icon basic button comments-tooltip"
            data-html={tooltipText}
            href={translatedUrl}
            data-variation="tiny"
            target="_blank"
            ref={(tooltip) => (this.commentsTooltip = tooltip)}
            rel="noreferrer"
          >
            <i className="icon-uniE96B icon" />
          </a>
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
          <a
            className="ui icon basic button qr-tooltip "
            data-html={tooltipText}
            href={url}
            target="_blank"
            data-position="top center"
            data-variation="tiny"
            ref={(tooltip) => (this.activityTooltip = tooltip)}
            rel="noreferrer"
          >
            <i className={'icon-qr-matecat icon ' + classQuality} />
          </a>
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
          <a
            className="ui icon basic button warning-tooltip"
            data-html={tooltipText}
            href={url}
            target="_blank"
            data-position="top center"
            data-variation="tiny"
            ref={(tooltip) => (this.warningTooltip = tooltip)}
            rel="noreferrer"
          >
            <i className="icon-notice icon red" />
          </a>
        </div>
      )
    }
    return icon
  }

  getWarningsMenuItem() {
    var icon = ''
    var warnings = this.props.job.get('warnings_count')
    if (warnings > 0) {
      var url = this.getTranslateUrl() + '?action=warnings'
      let tooltipText = 'Click to see issues'
      icon = (
        <a
          className="ui icon basic button "
          href={url}
          target="_blank"
          data-position="top center"
          rel="noreferrer"
        >
          <i className="icon-notice icon red" />
          {tooltipText}
        </a>
      )
    }
    return icon
  }

  getCommentsMenuItem() {
    let icon = ''
    let openThreads = this.props.job.get('open_threads_count')
    if (openThreads > 0) {
      var translatedUrl = this.getTranslateUrl() + '?action=openComments'
      if (this.props.job.get('open_threads_count') === 1) {
        icon = (
          <a
            className=" ui icon basic button "
            href={translatedUrl}
            target="_blank"
            rel="noreferrer"
          >
            <i className="icon-uniE96B icon" />
            There is an open thread
          </a>
        )
      } else {
        icon = (
          <a
            className=" ui icon basic button "
            href={translatedUrl}
            target="_blank"
            rel="noreferrer"
          >
            <i className="icon-uniE96B icon" />
            There are <span style={{fontWeight: 'bold'}}>
              {openThreads}
            </span>{' '}
            open threads
          </a>
        )
      }
    }
    return icon
  }

  getQRMenuItem() {
    var icon = ''
    var quality = this.props.job.get('quality_summary').get('quality_overall')
    if (quality === 'poor' || quality === 'fail') {
      var url = this.getQAReport()
      let tooltipText = 'Overall quality: ' + quality.toUpperCase()
      var classQuality = quality === 'poor' ? 'yellow' : 'red'
      icon = (
        <a
          className="ui icon basic button"
          href={url}
          target="_blank"
          data-position="top center"
          rel="noreferrer"
        >
          <i className={'icon-qr-matecat icon ' + classQuality} />
          {tooltipText}
        </a>
      )
    }
    return icon
  }

  openOutsourceModal(showTranslatorBox, extendedView) {
    if (showTranslatorBox && !this.props.job.get('outsource_available')) {
      this.setState({
        showTranslatorBox: showTranslatorBox,
        extendedView: false,
      })
    } else if (this.props.job.get('outsource_available')) {
      if (!this.state.openOutsource) {
        $(document).trigger('outsource-request')
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
    if (!config.enable_outsource) {
      return null
    }
    let label = (
      <a
        className="open-outsource buy-translation ui button"
        id="open-quote-request"
        onClick={this.openOutsourceModal.bind(this, false, true)}
        data-testid="buy-translation-button"
      >
        <span className="buy-translation-span">Buy Translation</span>
        <span>by</span>
        <img
          src={
            "data:image/svg+xml;charset=utf-8,%3Csvg width='40' height='40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M19.686 2.77C10.353 2.78 2.79 10.317 2.78 19.62c0 9.306 7.57 16.85 16.907 16.85 9.338 0 16.908-7.544 16.908-16.85 0-9.307-7.57-16.85-16.908-16.85zm0-2.77c10.872 0 19.686 8.784 19.686 19.62 0 10.835-8.814 19.62-19.686 19.62S0 30.454 0 19.62C0 8.784 8.814 0 19.686 0zm4.756 27.857a5.533 5.533 0 01-3.448 1.074c-1.766 0-3.119-.578-3.92-1.842-.495-.743-.69-1.623-.69-3.027v-7.568h-3.54v-2.331h3.54v-3.88h3.063v13.821c0 1.458.69 2.144 1.969 2.144a2.892 2.892 0 001.82-.63l1.206 2.24zm-1.415-12.556c-.004-.474.182-.93.515-1.267.333-.338.787-.53 1.263-.533h.056c.475.004.93.195 1.262.533.334.337.519.793.515 1.267a1.788 1.788 0 01-1.777 1.796h-.056a1.788 1.788 0 01-1.778-1.796z' fill-rule='evenodd'/%3E%3C/svg%3E"
          }
        />
      </a>
    )
    if (this.props.job.get('outsource')) {
      if (this.props.job.get('outsource').get('id_vendor') == '1') {
        label = (
          <a
            className="open-outsourced ui button "
            id="open-quote-request"
            onClick={this.openOutsourceModal.bind(this, false, true)}
          >
            View status
          </a>
        )
      }
    }
    return label
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
      let email = this.props.job.get('translator').get('email')

      outsourceJobLabel = (
        <div
          id="open-quote-request"
          className="job-to-translator"
          data-variation="tiny"
          ref={(tooltip) => (this.emailTooltip = tooltip)}
          onClick={this.openOutsourceModal.bind(this, true, false)}
        >
          {email}
        </div>
      )
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
    let outsourceDelivery = ''

    if (this.props.job.get('outsource')) {
      if (this.props.job.get('outsource').get('id_vendor') == '1') {
        let gmtDate = CommonUtils.getGMTDate(
          this.props.job.get('outsource').get('delivery_timestamp') * 1000,
        )
        outsourceDelivery = (
          <div className="job-delivery" title="Delivery date">
            <div className="outsource-day-text">{gmtDate.day}</div>
            <div className="outsource-month-text">{gmtDate.month}</div>
            <div className="outsource-time-text">{gmtDate.time}</div>
            <div className="outsource-gmt-text"> ({gmtDate.gmt})</div>
          </div>
        )
      }
    } else if (this.props.job.get('translator')) {
      let gmtDate = CommonUtils.getGMTDate(
        this.props.job.get('translator').get('delivery_timestamp') * 1000,
      )
      outsourceDelivery = (
        <div className="job-delivery" title="Delivery date">
          <div className="outsource-day-text">{gmtDate.day}</div>
          <div className="outsource-month-text">{gmtDate.month}</div>
          <div className="outsource-time-text">{gmtDate.time}</div>
          <div className="outsource-gmt-text"> ({gmtDate.gmt})</div>
        </div>
      )
    }

    return outsourceDelivery
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

  getWarningsInfo() {
    let n = {
      number: 0,
      icon: '',
    }
    let quality = this.props.job.get('quality_summary').get('quality_overall')
    if ((quality && quality === 'poor') || quality === 'fail') {
      n.number++
      n.icon = this.getQRIcon()
    }
    if (
      this.props.job.get('open_threads_count') &&
      this.props.job.get('open_threads_count') > 0
    ) {
      n.number++
      n.icon = this.getCommentsIcon()
    }
    if (
      this.props.job.get('warnings_count') &&
      this.props.job.get('warnings_count') > 0
    ) {
      n.number++
      n.icon = this.getWarningsIcon()
    }
    return n
  }

  getWarningsGroup() {
    const icons = this.getWarningsInfo()
    const iconsBody =
      icons.number > 1 ? (
        <div
          className="ui icon top right pointing dropdown group-activity-icon basic button"
          ref={(button) => (this.iconsButton = button)}
        >
          <i className="icon-alarm icon" />
          <div className="menu group-activity-icons transition hidden">
            <div className="item">{this.getQRMenuItem()}</div>
            <div className="item">{this.getWarningsMenuItem()}</div>
            <div className="item">{this.getCommentsMenuItem()}</div>
          </div>
        </div>
      ) : (
        icons.icon
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
      nextState.showTranslatorBox !== this.state.showTranslatorBox
    )
  }

  componentDidUpdate(prevProps, prevState) {
    var self = this
    $(this.iconsButton).dropdown()
    this.initTooltips()
    if (this.updated) {
      this.container.classList.add('updated-job')
      setTimeout(function () {
        self.container.classList.remove('updated-job')
        $(self.dropdown).dropdown({
          belowOrigin: true,
        })
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
    $(this.dropdown).dropdown({
      belowOrigin: true,
    })
    this.initTooltips()
    $(this.iconsButton).dropdown()
    ProjectsStore.addListener(
      ManageConstants.ENABLE_DOWNLOAD_BUTTON,
      this.enableDownloadMenu.bind(this),
    )
    ProjectsStore.addListener(
      ManageConstants.DISABLE_DOWNLOAD_BUTTON,
      this.disableDownloadMenu.bind(this),
    )
  }

  initTooltips() {
    $(this.rejectedTooltip).popup()
    $(this.approvedTooltip).popup()
    $(this.approved2ndPassTooltip).popup()
    $(this.translatedTooltip).popup()
    $(this.draftTooltip).popup()
    $(this.activityTooltip).popup()
    $(this.commentsTooltip).popup()
    $(this.tmTooltip).popup({hoverable: true})
    $(this.warningTooltip).popup()
    $(this.languageTooltip).popup()
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
    let tmIcon = this.getTMIcon()
    let outsourceClass = this.props.job.get('outsource')
      ? 'outsource'
      : 'translator'

    let idJobLabel = !this.props.isChunk
      ? this.props.job.get('id')
      : this.props.job.get('id') + '-' + this.props.index
    let approvedPercFormatted = this.props.job
      .get('stats')
      .get('APPROVED_PERC_FORMATTED')
    let approvedPerc = this.props.job.get('stats').get('APPROVED_PERC')
    let approvedPerc2ndPass, approvedPercFormatted2ndPass
    if (
      this.props.project.has('features') &&
      this.props.project.get('features').indexOf('second_pass_review') > -1 &&
      this.props.job.get('stats').has('revises') &&
      this.props.job.get('stats').get('revises').size > 1 &&
      this.props.job
        .get('stats')
        .get('revises')
        .get(1)
        .get('advancement_wc') !== 0.0
    ) {
      let approved = this.props.job
        .get('stats')
        .get('revises')
        .find((item) => {
          return item.get('revision_number') === 1
        })
      approvedPerc = approved
        ? (approved.get('advancement_wc') * 100) /
          this.props.job.get('stats').get('TOTAL')
        : approvedPerc
      approvedPercFormatted = _.round(approvedPerc, 1)
      let approved2ndPass = this.props.job
        .get('stats')
        .get('revises')
        .find((item) => {
          return item.get('revision_number') === 2
        })
      approvedPerc2ndPass = approved2ndPass
        ? (approved2ndPass.get('advancement_wc') * 100) /
          this.props.job.get('stats').get('TOTAL')
        : approved2ndPass
      approvedPercFormatted2ndPass = _.round(approvedPerc2ndPass, 1)
    }

    return (
      <div className="sixteen wide column chunk-container">
        <div
          className="ui grid"
          ref={(container) => (this.container = container)}
        >
          {!this.state.openOutsource ? (
            <div
              className="chunk wide column pad-right-10 shadow-1"
              ref={(chunkRow) => (this.chunkRow = chunkRow)}
            >
              <div className="job-id" title="Job Id">
                ID: {idJobLabel}
              </div>
              <div
                className="source-target languages-tooltip"
                ref={(tooltip) => (this.languageTooltip = tooltip)}
                data-html={
                  this.props.job.get('sourceTxt') +
                  ' > ' +
                  this.props.job.get('targetTxt')
                }
                data-variation="tiny"
              >
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
              <div className="progress-bar">
                <div className="progr">
                  <div className="meter">
                    <a
                      className="warning-bar translate-tooltip"
                      data-variation="tiny"
                      data-html={
                        'Rejected ' +
                        this.props.job
                          .get('stats')
                          .get('REJECTED_PERC_FORMATTED') +
                        '%'
                      }
                      style={{
                        width:
                          this.props.job.get('stats').get('REJECTED_PERC') +
                          '%',
                      }}
                      ref={(tooltip) => (this.rejectedTooltip = tooltip)}
                    />
                    <a
                      className="approved-bar translate-tooltip"
                      data-variation="tiny"
                      data-html={'Approved ' + approvedPercFormatted + '%'}
                      style={{width: approvedPerc + '%'}}
                      ref={(tooltip) => (this.approvedTooltip = tooltip)}
                    />
                    {approvedPercFormatted2ndPass ? (
                      <a
                        className="approved-bar-2nd-pass translate-tooltip"
                        data-variation="tiny"
                        data-html={
                          'Approved ' + approvedPercFormatted2ndPass + '%'
                        }
                        style={{width: approvedPerc2ndPass + '%'}}
                        ref={(tooltip) =>
                          (this.approved2ndPassTooltip = tooltip)
                        }
                      />
                    ) : null}
                    <a
                      className="translated-bar translate-tooltip"
                      data-variation="tiny"
                      data-html={
                        'Translated ' +
                        this.props.job
                          .get('stats')
                          .get('TRANSLATED_PERC_FORMATTED') +
                        '%'
                      }
                      style={{
                        width:
                          this.props.job.get('stats').get('TRANSLATED_PERC') +
                          '%',
                      }}
                      ref={(tooltip) => (this.translatedTooltip = tooltip)}
                    />
                    <a
                      className="draft-bar translate-tooltip"
                      data-variation="tiny"
                      data-html={
                        'Draft ' +
                        this.props.job
                          .get('stats')
                          .get('DRAFT_PERC_FORMATTED') +
                        '%'
                      }
                      style={{
                        width:
                          this.props.job.get('stats').get('DRAFT_PERC') + '%',
                      }}
                      ref={(tooltip) => (this.draftTooltip = tooltip)}
                    />
                  </div>
                </div>
              </div>
              <div className="job-payable">
                <a href={analysisUrl} target="_blank" rel="noreferrer">
                  <span id="words">
                    {this.props.job.get('stats').get('TOTAL_FORMATTED')}
                  </span>{' '}
                  words
                </a>
              </div>
              <div className="tm-job" data-testid="tm-container">
                {tmIcon}
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
              <div
                className="ui icon top right pointing dropdown job-menu  button"
                title="Job menu"
                ref={(dropdown) => (this.dropdown = dropdown)}
                data-testid="job-menu-button"
              >
                <i className="icon-more_vert icon" />
                {jobMenu}
              </div>
              <a
                className="open-translate ui primary button open"
                target="_blank"
                href={translateUrl}
                rel="noreferrer"
              >
                Open
              </a>
              {outsourceButton}

              {this.state.showDownloadProgress ? (
                <div className="chunk-download-progress" />
              ) : (
                ''
              )}
            </div>
          ) : null}
        </div>
        <OutsourceContainer
          project={this.props.project}
          job={this.props.job}
          url={this.getTranslateUrl()}
          showTranslatorBox={this.state.showTranslatorBox}
          extendedView={this.state.extendedView}
          onClickOutside={this.closeOutsourceModal.bind(this)}
          openOutsource={this.state.openOutsource}
          idJobLabel={idJobLabel}
        />
      </div>
    )
  }
}
export default JobContainer
