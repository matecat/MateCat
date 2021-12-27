import ReactDOM from 'react-dom'
import React from 'react'
import $ from 'jquery'
import _ from 'lodash'

import AppDispatcher from '../stores/AppDispatcher'
import CattolConstants from '../constants/CatToolConstants'
import Notifications from '../sse/sse'
import {QualityReportButton} from '../components/review/QualityReportButton'
import SubHeaderContainer from '../components/header/cattol/SubHeaderContainer'
import SegmentFilter from '../components/header/cattol/segment_filter/segment_filter'
import {CattolFooter} from '../components/footer/CattoolFooter'
import RevisionFeedbackModal from '../components/modals/RevisionFeedbackModal'
import CommonUtils from '../utils/commonUtils'
import CatToolStore from '../stores/CatToolStore'
import {getJobStatistics} from '../api/getJobStatistics'
import {sendRevisionFeedback} from '../api/sendRevisionFeedback'
import {ModalWindow} from '../components/modals/ModalWindow'

let CatToolActions = {
  popupInfoUserMenu: () => 'infoUserMenu-' + config.userMail,

  openQaIssues: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.SHOW_CONTAINER,
      container: 'qaComponent',
    })
  },
  openSearch: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.SHOW_CONTAINER,
      container: 'search',
    })
  },
  openSegmentFilter: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.SHOW_CONTAINER,
      container: 'segmentFilter',
    })
  },
  setSegmentFilter: function (segments, state) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.SET_SEGMENT_FILTER,
      data: segments,
      state: state,
    })
  },
  reloadSegmentFilter: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.RELOAD_SEGMENT_FILTER,
    })
  },
  toggleQaIssues: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.TOGGLE_CONTAINER,
      container: 'qaComponent',
    })
  },
  toggleSearch: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.TOGGLE_CONTAINER,
      container: 'search',
    })
  },
  storeSearchResults: function (data) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.STORE_SEARCH_RESULT,
      data: data,
    })
  },
  toggleSegmentFilter: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.TOGGLE_CONTAINER,
      container: 'segmentFilter',
    })
  },
  closeSubHeader: function () {
    $('.mbc-history-balloon-outer').removeClass('mbc-visible')
    $('#mbc-history').removeClass('open')
    AppDispatcher.dispatch({
      actionType: CattolConstants.CLOSE_SUBHEADER,
    })
  },
  closeSearch: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.CLOSE_SEARCH,
    })
    setTimeout(() => window.dispatchEvent(new Event('resize')))
  },
  startNotifications: function () {
    Notifications.start()
  },
  clientConntected: function (clientId) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.CLIENT_CONNECT,
      clientId,
    })
  },
  renderQualityReportButton() {
    var revision_number = config.revisionNumber ? config.revisionNumber : '1'
    var qrParam = config.secondRevisionsCount
      ? '?revision_type=' + revision_number
      : ''
    window.quality_report_btn_component = ReactDOM.render(
      React.createElement(QualityReportButton, {
        vote: config.overall_quality_class,
        quality_report_href: config.quality_report_href + qrParam,
      }),
      $('#quality-report-button')[0],
    )
  },
  renderSubHeader() {
    ReactDOM.render(
      React.createElement(SubHeaderContainer, {
        filtersEnabled: SegmentFilter.enabled(),
      }),
      $('#header-bars-wrapper')[0],
    )
  },

  showHeaderTooltip: function () {
    var closedPopup = localStorage.getItem(this.popupInfoUserMenu())

    if (config.is_cattool) {
      UI.showProfilePopUp(!closedPopup)
    } else if (!closedPopup) {
      AppDispatcher.dispatch({
        actionType: CattolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
      })
    }
  },
  setPopupUserMenuCookie: function () {
    CommonUtils.addInStorage(this.popupInfoUserMenu(), true, 'infoUserMenu')
  },
  storeFilesInfo: function (data) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.STORE_FILES_INFO,
      files: data.files,
    })

    config.last_job_segment = data.last_segment
    config.firstSegmentOfFiles = data.files
  },
  renderFooter: function () {
    var mountPoint = $('footer.stats-foo')[0]
    ReactDOM.render(
      React.createElement(CattolFooter, {
        idProject: config.id_project,
        idJob: config.job_id,
        password: config.password,
        source: config.source_rfc,
        target: config.target_rfc,
        isReview: config.isReview,
        isCJK: config.isCJK,
        languagesArray: config.languages_array,
      }),
      mountPoint,
    )
  },
  updateFooterStatistics: function () {
    getJobStatistics(config.id_job, config.password).then(function (data) {
      if (data.stats) {
        CatToolActions.setProgress(data.stats)
        UI.setDownloadStatus(data.stats)
      }
    })
  },
  setProgress: function (stats) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.SET_PROGRESS,
      stats: stats,
    })
    //TODO move it
    UI.projectStats = stats
    this.checkQualityReport(stats)
  },
  checkQualityReport: function (stats) {
    if (stats.APPROVED_PERC > 10) {
      $('#quality-report-button').attr('data-revised', true)
    }
    let reviseCount = config.isReview
      ? _.filter(
          stats.revises,
          (rev) => rev.revision_number === config.revisionNumber,
        )
      : null
    if (
      config.isReview &&
      reviseCount &&
      reviseCount.length > 0 &&
      reviseCount[0].advancement_wc >= stats.TOTAL
    ) {
      let revise = CatToolStore.getQR(config.revisionNumber)
      if (revise && !revise[0].feedback) {
        // var notification = {
        //     title: 'Leave your feedback',
        //     text: "Kudos, you have completed the job! Please remember to leave some feedback for the translator. " +
        //         "<a id='leaveFeedback' style='cursor: pointer'> Write feedback. </a>",
        //     allowHtml: true,
        //     timer: 6000,
        //     type: 'warning',
        //     position: "tc",
        //     closeCallback: ()=>{
        //         $('#leaveFeedback').off('click');
        //     },
        //     openCallback: ()=>{
        //         $('#leaveFeedback').bind('click','#leaveFeedback', () => {
        //             APP.removeAllNotifications();
        //             CatToolActions.openFeedbackModal("", config.revisionNumber);
        //         });
        //     }
        // };
        //
        // APP.addNotification(notification);
        const isModalClosed =
          CommonUtils.getFromSessionStorage('feedback-modal')
        if (!isModalClosed) {
          CatToolActions.openFeedbackModal('', config.revisionNumber)
        }
      }
    }
  },
  openFeedbackModal: function (feedback, revisionNumber) {
    var props = {
      feedback: feedback,
      revisionNumber: revisionNumber,
      overlay: true,
      onCloseCallback: function () {
        CommonUtils.addInSessionStorage('feedback-modal', 1, 'feedback-modal')
      },
      successCallback: function () {
        ModalWindow.onCloseModal()
      },
    }
    ModalWindow.showModalComponent(
      RevisionFeedbackModal,
      props,
      'Feedback submission',
    )
  },
  sendRevisionFeedback: function (text) {
    return sendRevisionFeedback(
      config.id_job,
      config.revisionNumber,
      config.review_password,
      text,
    )
  },
  updateQualityReport: function (qr) {
    var revNumber = config.revisionNumber ? config.revisionNumber : 1
    var review = qr.chunk.reviews.find(function (value) {
      return value.revision_number === revNumber
    })
    AppDispatcher.dispatch({
      actionType: CattolConstants.UPDATE_QR,
      qr: qr,
    })
    if (review) {
      window.quality_report_btn_component.setState({
        is_pass: review.is_pass,
        score: review.score,
        feedback: review.feedback,
      })
    }
  },
}

export default CatToolActions
