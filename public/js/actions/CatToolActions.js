import AppDispatcher from '../stores/AppDispatcher'
import RevisionFeedbackModal from '../components/modals/RevisionFeedbackModal'
import CommonUtils from '../utils/commonUtils'
import CatToolStore from '../stores/CatToolStore'
import {getJobStatistics} from '../api/getJobStatistics'
import {sendRevisionFeedback} from '../api/sendRevisionFeedback'
import ModalsActions from './ModalsActions'
import {getTmKeysJob} from '../api/getTmKeysJob'
import {getDomainsList} from '../api/getDomainsList'
import {checkJobKeysHaveGlossary} from '../api/checkJobKeysHaveGlossary'
import {getJobMetadata} from '../api/getJobMetadata'
import CatToolConstants from '../constants/CatToolConstants'
import SegmentStore from '../stores/SegmentStore'
import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import {getGlobalWarnings} from '../api/getGlobalWarnings'
import SegmentActions from './SegmentActions'
import OfflineUtils from '../utils/offlineUtils'
import AlertModal from '../components/modals/AlertModal'
import {isUndefined} from 'lodash'

let CatToolActions = {
  popupInfoUserMenu: () => 'infoUserMenu-' + config.userMail,
  startWarningTimeout: undefined,
  setFirstLoad: function (value) {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SET_FIRST_LOAD,
      value,
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
    if (CatToolStore.isClientConnected()) {
      if (!jobKeys || forceUpdate) {
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

    props.openCurrentSegmentAfter = !!(
      !segmentToOpen && !CatToolStore.getFirstLoad()
    )
    let startSegmentId
    if (props.segmentToOpen) {
      startSegmentId = props.segmentToOpen
    } else {
      const hash = CommonUtils.parsedHash.segmentId
      config.last_opened_segment = CommonUtils.getLastSegmentFromLocalStorage()
        ? CommonUtils.getLastSegmentFromLocalStorage()
        : config.first_job_segment

      startSegmentId = hash ? hash : config.last_opened_segment
    }

    AppDispatcher.dispatch({
      actionType: CatToolConstants.ON_RENDER,
      ...props,
      ...(startSegmentId && {startSegmentId: startSegmentId}),
      where: props.where ? props.where : 'center',
    })
  },
  onTMKeysChangeStatus: () => {
    CatToolActions.retrieveJobKeys(true)
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
  setSegmentFilterError: function () {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SEGMENT_FILTER_ERROR,
    })
  },
  showLaraQuotaExceeded: () => {
    const key = 'lara_quote_exceed' + config.id_job
    if (!sessionStorage.getItem(key)) {
      const props = {
        text: config.ownerIsMe
          ? "You've hit the <strong>monthly limit of 10k characters</strong> available with Lara's free plan.</br>" +
            'Lara will stop translating until the limit is reset at the end of the billing cycle.<br><br>' +
            'To enjoy unlimited access to the best machine tranlsation, upgrade your plan.'
          : "The <strong>10k-character monthly limit</strong> for Lara's free plan has been reached.<br> " +
            'Translation will pause until the limit resets at the end of the billing cycle or the project owner upgrades the plan.<br>',
        successText: config.ownerIsMe ? 'Upgrade your plan' : null,
        cancelText: 'Dismiss',
        successCallback: config.ownerIsMe
          ? () => {
              window.open('https://laratranslate.com/pricing', '_blank')
            }
          : null,
        onCloseCallback: () => {
          sessionStorage.setItem(key, true)
        },
      }
      ModalsActions.showModalComponent(
        ConfirmMessageModal,
        props,
        'Lara Free Plan Limit Reached',
      )
    }
  },
  startWarning: function () {
    clearTimeout(CatToolActions.startWarningTimeout)
    CatToolActions.startWarningTimeout = setTimeout(function () {
      // If the tab is not active avoid to make the warnings call
      if (document.visibilityState === 'hidden') {
        CatToolActions.startWarning()
      } else {
        CatToolActions.checkWarnings(false)
      }
    }, config.warningPollingInterval)
  },
  checkWarnings: function () {
    // var mock = {
    //     ERRORS: {
    //         categories: {
    //             'TAG': ['23853','23854','23855','23856','23857'],
    //         }
    //     },
    //     WARNINGS: {
    //         categories: {
    //             'TAG': ['23857','23858','23859'],
    //             'GLOSSARY': ['23860','23863','23864','23866',],
    //             'MISMATCH': ['23860','23863','23864','23866',]
    //         }
    //     },
    //     INFO: {
    //         categories: {
    //         }
    //     }
    // };
    getGlobalWarnings({id_job: config.id_job, password: config.password})
      .then((data) => {
        //console.log('check warnings success');
        CatToolActions.startWarning()

        //check for errors
        if (data.details) {
          SegmentActions.updateGlobalWarnings(data.details)
        }
        CommonUtils.dispatchCustomEvent('getWarning:global:success')
      })
      .catch(() => {
        OfflineUtils.failedConnection()
      })
  },
  processErrors: function (errors, operation) {
    if (Array.isArray(errors)) {
      errors.forEach((error) => {
        const codeInt = parseInt(error.code)

        if (operation === 'setTranslation') {
          if (codeInt !== -10) {
            ModalsActions.showModalComponent(
              AlertModal,
              {
                text: 'Error in saving the translation. Try the following: <br />1) Refresh the page (Ctrl+F5 twice) <br />2) Clear the cache in the browser <br />If the solutions above does not resolve the issue, please stop the translation and report the problem to <b>support@matecat.com</b>',
              },
              'Error',
            )
          }
        }

        if (codeInt === -10 && operation !== 'getSegments') {
          ModalsActions.showModalComponent(
            AlertModal,
            {
              text: 'Job canceled or assigned to another translator',
              successCallback: () => location.reload,
            },
            'Error',
          )
        }
        if (codeInt === -1000 || codeInt === -101) {
          console.log('ERROR ' + codeInt)
          OfflineUtils.startOfflineMode()
        }

        if (codeInt === -2000 && !isUndefined(error.message)) {
          ModalsActions.showModalComponent(
            AlertModal,
            {
              /* text:
                'You cannot change the status of an ICE segment to "Translated" without editing it first.</br>' +
                'Please edit the segment first if you want to change its status to "Translated".',*/
              text: error.message,
            },
            'Error',
          )
        }
      })
    }
  },
}

export default CatToolActions
