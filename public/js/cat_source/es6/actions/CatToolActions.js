import {createRoot} from 'react-dom/client'
import React from 'react'
import $ from 'jquery'

import AppDispatcher from '../stores/AppDispatcher'
import RevisionFeedbackModal from '../components/modals/RevisionFeedbackModal'
import CommonUtils from '../utils/commonUtils'
import CatToolStore from '../stores/CatToolStore'
import {getJobStatistics} from '../api/getJobStatistics'
import {sendRevisionFeedback} from '../api/sendRevisionFeedback'
import ModalsActions from './ModalsActions'
import {Header} from '../components/header/cattol/Header'
import {getTmKeysJob} from '../api/getTmKeysJob'
import {getDomainsList} from '../api/getDomainsList'
import {checkJobKeysHaveGlossary} from '../api/checkJobKeysHaveGlossary'
import {getJobMetadata} from '../api/getJobMetadata'
import CatToolConstants from '../constants/CatToolConstants'
import SegmentStore from '../stores/SegmentStore'

let CatToolActions = {
  popupInfoUserMenu: () => 'infoUserMenu-' + config.userMail,

  openQaIssues: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SHOW_CONTAINER,
      container: 'qaComponent',
    })
  },
  openSearch: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SHOW_CONTAINER,
      container: 'search',
    })
  },
  openSegmentFilter: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SHOW_CONTAINER,
      container: 'segmentFilter',
    })
  },
  setSegmentFilter: function (segments, state) {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SET_SEGMENT_FILTER,
      data: segments,
      state: state,
    })
  },
  reloadSegmentFilter: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.RELOAD_SEGMENT_FILTER,
    })
  },
  toggleQaIssues: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.TOGGLE_CONTAINER,
      container: 'qaComponent',
    })
  },
  toggleSearch: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.TOGGLE_CONTAINER,
      container: 'search',
    })
  },
  storeSearchResults: function (data) {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.STORE_SEARCH_RESULT,
      data: data,
    })
  },
  toggleSegmentFilter: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.TOGGLE_CONTAINER,
      container: 'segmentFilter',
    })
  },
  closeSubHeader: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.CLOSE_SUBHEADER,
    })
  },
  closeSearch: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.CLOSE_SEARCH,
    })
    setTimeout(() => window.dispatchEvent(new Event('resize')))
  },
  clientConnected: function (clientId) {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.CLIENT_CONNECT,
      clientId,
    })
  },
  clientReconnect: () => {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.CLIENT_RECONNECTION,
    })
  },

  showHeaderTooltip: function () {
    var closedPopup = localStorage.getItem(this.popupInfoUserMenu())

    if (config.is_cattool) {
      UI.showProfilePopUp(!closedPopup)
    } else if (!closedPopup) {
      AppDispatcher.dispatch({
        actionType: CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
      })
    }
  },
  setPopupUserMenuCookie: function () {
    CommonUtils.addInStorage(this.popupInfoUserMenu(), true, 'infoUserMenu')
  },
  storeFilesInfo: function (files, firstSegment, lastSegment) {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.STORE_FILES_INFO,
      files: files,
    })

    config.last_job_segment = lastSegment
    config.firstSegmentOfFiles = files
  },
  updateFooterStatistics: function () {
    getJobStatistics(config.id_job, config.password).then(function (data) {
      if (data) {
        CatToolActions.setProgress(data)
      }
    })
  },
  setProgress: function (data) {
    const stats = data.stats ? data.stats : data
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SET_PROGRESS,
      stats: stats,
    })
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
      actionType: CatToolConstants.RELOAD_QR,
    })
  },
  updateQualityReport: function (qr) {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.UPDATE_QR,
      qr: qr,
    })
  },
  retrieveJobKeys: function (forceUpdate = false) {
    const jobKeys = CatToolStore.getJobTmKeys()
    const domains = CatToolStore.getKeysDomains()
    const haveKeysGlossary = CatToolStore.getHaveKeysGlossary()
    if ((!jobKeys || forceUpdate) && CatToolStore.isClientConnected()) {
      getTmKeysJob().then(({tm_keys: tmKeys}) => {
        // filter not private keys
        const filteredKeys = tmKeys.filter(({is_private}) => !is_private)
        getDomainsList({
          keys: filteredKeys.map(({key}) => key),
        })
        const keys = filteredKeys.map((item) => ({...item, id: item.key}))
        AppDispatcher.dispatch({
          actionType: CatToolConstants.UPDATE_TM_KEYS,
          keys,
        })
      })

      // check job keys have glossary (response sse channel)
      checkJobKeysHaveGlossary()
    } else {
      AppDispatcher.dispatch({
        actionType: CatToolConstants.UPDATE_TM_KEYS,
        keys: jobKeys,
      })
      //From sse channel
      if (domains) {
        AppDispatcher.dispatch({
          actionType: CatToolConstants.UPDATE_DOMAINS,
          entries: domains,
        })
      }
      if (haveKeysGlossary !== undefined) {
        AppDispatcher.dispatch({
          actionType: CatToolConstants.HAVE_KEYS_GLOSSARY,
          value: haveKeysGlossary,
          wasAlreadyVerified: true,
        })
      }
    }
  },
  setDomains: ({entries, sid}) => {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.UPDATE_DOMAINS,
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
      actionType: CatToolConstants.ADD_NOTIFICATION,
      notification,
    })
  },
  removeNotification: function (notification) {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.REMOVE_NOTIFICATION,
      notification,
    })
  },

  removeAllNotifications: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.REMOVE_ALL_NOTIFICATION,
    })
  },
  onRender: (props = {}) => {
    SegmentStore.nextUntranslatedFromServer = null

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
      actionType: CatToolConstants.ON_RENDER,
      ...props,
      ...(UI.startSegmentId && {startSegmentId: UI.startSegmentId}),
      where: props.where ? props.where : 'center',
    })
  },
  onTMKeysChangeStatus: () => {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.ON_TM_KEYS_CHANGE_STATUS,
    })
  },
  setHaveKeysGlossary: (value) => {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.HAVE_KEYS_GLOSSARY,
      value,
    })
  },
  openSettingsPanel: (value) => {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.OPEN_SETTINGS_PANEL,
      value,
    })
  },
  getJobMetadata: ({idJob, password}) => {
    if (!CatToolStore.jobMetadata) {
      getJobMetadata(idJob, password).then((jobMetadata) => {
        AppDispatcher.dispatch({
          actionType: CatToolConstants.GET_JOB_METADATA,
          jobMetadata,
        })
        CatToolStore.jobMetadata = jobMetadata
      })
    } else {
      AppDispatcher.dispatch({
        actionType: CatToolConstants.GET_JOB_METADATA,
        jobMetadata: CatToolStore.jobMetadata,
      })
    }
  },
}

export default CatToolActions
