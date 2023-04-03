import {createRoot} from 'react-dom/client'
import React from 'react'
import $ from 'jquery'
import _ from 'lodash'

import AppDispatcher from '../stores/AppDispatcher'
import CattolConstants from '../constants/CatToolConstants'
import Notifications from '../sse/sse'
import {CattolFooter} from '../components/footer/CattoolFooter'
import RevisionFeedbackModal from '../components/modals/RevisionFeedbackModal'
import CommonUtils from '../utils/commonUtils'
import CatToolStore from '../stores/CatToolStore'
import {getJobStatistics} from '../api/getJobStatistics'
import {sendRevisionFeedback} from '../api/sendRevisionFeedback'
import ModalsActions from './ModalsActions'
import {Header} from '../components/header/cattol/Header'
import SegmentUtils from '../utils/segmentUtils'
import {getTmKeysJob} from '../api/getTmKeysJob'
import {getDomainsList} from '../api/getDomainsList'
import {checkJobKeysHaveGlossary} from '../api/checkJobKeysHaveGlossary'

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
  storeFilesInfo: function (files, firstSegment, lastSegment) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.STORE_FILES_INFO,
      files: files,
    })

    config.last_job_segment = lastSegment
    config.firstSegmentOfFiles = files
  },
  renderHeader: () => {
    const mountPoint = createRoot($('header')[0])
    mountPoint.render(
      React.createElement(Header, {
        pid: config.id_project,
        jid: config.job_id,
        password: config.password,
        reviewPassword: config.review_password,
        source_code: config.source_rfc,
        target_code: config.target_rfc,
        isReview: config.isReview,
        revisionNumber: config.revisionNumber,
        userLogged: config.isLoggedIn,
        projectName: config.project_name,
        projectCompletionEnabled: config.project_completion_feature_enabled,
        secondRevisionsCount: config.secondRevisionsCount,
        overallQualityClass: config.overall_quality_class,
        qualityReportHref: config.quality_report_href,
        allowLinkToAnalysis: config.allow_link_to_analysis,
        analysisEnabled: config.analysis_enabled,
        isGDriveProject: config.isGDriveProject,
        showReviseLink: config.footer_show_revise_link,
      }),
    )
  },
  renderFooter: function () {
    var mountPoint = createRoot($('footer.stats-foo')[0])
    mountPoint.render(
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
        ModalsActions.onCloseModal()
      },
    }
    ModalsActions.showModalComponent(
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
  reloadQualityReport: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.RELOAD_QR,
    })
  },
  updateQualityReport: function (qr) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.UPDATE_QR,
      qr: qr,
    })
  },
  retrieveJobKeys: function (forceUpdate = false) {
    const jobKeys = CatToolStore.getJobTmKeys()
    const domains = CatToolStore.getKeysDomains()
    const haveKeysGlossary = CatToolStore.getHaveKeysGlossary()
    if ((!jobKeys || forceUpdate) && config.id_client) {
      getTmKeysJob().then(({tm_keys: tmKeys}) => {
        // filter not private keys
        const filteredKeys = tmKeys.filter(({is_private}) => !is_private)
        getDomainsList({
          keys: filteredKeys.map(({key}) => key),
        })
        const keys = filteredKeys.map((item) => ({...item, id: item.key}))
        AppDispatcher.dispatch({
          actionType: CattolConstants.UPDATE_TM_KEYS,
          keys,
        })
      })

      // check job keys have glossary (response sse channel)
      checkJobKeysHaveGlossary()
    } else {
      AppDispatcher.dispatch({
        actionType: CattolConstants.UPDATE_TM_KEYS,
        keys: jobKeys,
      })
      //From sse channel
      if (domains) {
        AppDispatcher.dispatch({
          actionType: CattolConstants.UPDATE_DOMAINS,
          entries: domains,
        })
      }
      if (haveKeysGlossary !== undefined) {
        AppDispatcher.dispatch({
          actionType: CattolConstants.HAVE_KEYS_GLOSSARY,
          value: haveKeysGlossary,
          wasAlreadyVerified: true,
        })
      }
    }
  },
  setDomains: ({entries, sid}) => {
    AppDispatcher.dispatch({
      actionType: CattolConstants.UPDATE_DOMAINS,
      sid,
      entries,
    })
  },
  /**
   * Function to add notifications to the interface
   * notification object with the following properties
   *
   * title:           (String) Title of the notification.
   * text:            (String) Message of the notification
   * type:            (String, Default "info") Level of the notification. Available: success, error, warning and info.
   * position:        (String, Default "bl") Position of the notification. Available: tr (top right), tl (top left),
   *                      tc (top center), br (bottom right), bl (bottom left), bc (bottom center)
   * closeCallback    (Function) A callback function that will be called when the notification is about to be removed.
   * openCallback     (Function) A callback function that will be called when the notification is successfully added.
   * allowHtml:       (Boolean, Default false) Set to true if the text contains HTML, like buttons
   * autoDismiss:     (Boolean, Default true) Set if notification is dismissible by the user.
   *
   */
  addNotification: function (notification) {
    return AppDispatcher.dispatch({
      actionType: CattolConstants.ADD_NOTIFICATION,
      notification,
    })
  },
  removeNotification: function (notification) {
    AppDispatcher.dispatch({
      actionType: CattolConstants.REMOVE_NOTIFICATION,
      notification,
    })
  },

  removeAllNotifications: function () {
    AppDispatcher.dispatch({
      actionType: CattolConstants.REMOVE_ALL_NOTIFICATION,
    })
  },
  onRender: (props = {}) => {
    UI.nextUntranslatedSegmentIdByServer = null
    UI.tagModesEnabled =
      typeof props.tagModesEnabled != 'undefined' ? props.tagModesEnabled : true

    if (UI.tagModesEnabled && !SegmentUtils.checkTPEnabled())
      UI.body.addClass('tagModes')
    else UI.body.removeClass('tagModes')

    UI.setTagLockCustomizeCookie(true)

    const segmentToOpen = props.segmentToOpen || false

    props.openCurrentSegmentAfter = !!(!segmentToOpen && !UI.firstLoad)
    if (props.segmentToOpen) {
      UI.startSegmentId = props.segmentToOpen
    } else {
      const hash = CommonUtils.parsedHash.segmentId
      config.last_opened_segment = CommonUtils.getLastSegmentFromLocalStorage()
        ? CommonUtils.getLastSegmentFromLocalStorage()
        : config.first_job_segment

      UI.startSegmentId = hash ? hash : config.last_opened_segment
    }

    AppDispatcher.dispatch({
      actionType: CattolConstants.ON_RENDER,
      ...props,
      ...(UI.startSegmentId && {startSegmentId: UI.startSegmentId}),
      where: props.where ? props.where : 'center',
    })
  },
  onTMKeysChangeStatus: () => {
    AppDispatcher.dispatch({
      actionType: CattolConstants.ON_TM_KEYS_CHANGE_STATUS,
    })
  },
  setHaveKeysGlossary: (value) => {
    AppDispatcher.dispatch({
      actionType: CattolConstants.HAVE_KEYS_GLOSSARY,
      value,
    })
  },
}

export default CatToolActions
