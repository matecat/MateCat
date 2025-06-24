import $ from 'jquery'
import SegmentActions from '../actions/SegmentActions'
import {checkConnectionPing} from '../api/checkConnectionPing'
import CatToolActions from '../actions/CatToolActions'

const OfflineUtils = {
  offline: false,
  offlineCacheSize: 20,
  offlineCacheRemaining: 20,
  checkingConnection: false,
  currentConnectionCountdown: null,

  startOfflineMode: function () {
    if (!this.offline) {
      checkConnectionPing()
        .then(() => {
          this.offlineCacheRemaining = this.offlineCacheSize
          const notification = {
            uid: 'offline-counter',
            title: "We're sorry, something went wrong",
            text: 'Something went wrong while processing your request. Please try again later. If the issue persists, contact our support team for assistance.',
            type: 'warning',
            position: 'bl',
            autoDismiss: false,
            allowHtml: true,
            timer: 7000,
          }
          CatToolActions.addNotification(notification)
        })
        .catch(() => {
          this.offline = true
          UI.body.attr('data-offline-mode', 'light-off')
          this.checkingConnection = setInterval(() => {
            this.checkConnection()
          }, 5000)
        })
    }
  },

  endOfflineMode: function () {
    if (this.offline) {
      this.offline = false
      var notification = {
        uid: 'offline-back',
        title: 'Connection is back',
        text: 'We are saving translated segments in the database.',
        type: 'success',
        position: 'bl',
        autoDismiss: true,
        timer: 10000,
        openCallback: () => {
          CatToolActions.removeAllNotifications()
        },
      }
      CatToolActions.addNotification(notification)

      clearInterval(this.currentConnectionCountdown)
      clearInterval(this.checkingConnection)
      this.currentConnectionCountdown = null
      this.checkingConnection = false
      UI.body.removeAttr('data-offline-mode')
    }
  },
  failedConnection: function () {
    this.startOfflineMode()
  },
  checkConnection: function () {
    checkConnectionPing()
      .then(() => {
        this.endOfflineMode()
        UI.executingSetTranslation = []
        UI.execSetTranslationTail()
        //reset counter
        this.offlineCacheRemaining = this.offlineCacheSize
      })
      .catch(() => {
        /**
         * do Nothing there are already a thread running
         * @see UI.startOfflineMode
         * @see UI.endOfflineMode
         */
      })
  },

  decrementOfflineCacheRemaining: function () {
    console.log('Segmenti in coda', this.offlineCacheRemaining)
    if (this.offline) {
      const notification = {
        uid: 'offline-counter',
        title:
          '<div class="message-offline-icons"><span class="icon-power-cord"></span><span class="icon-power-cord2"></span></div>No connection available',
        text:
          'You can still translate <span class="remainingSegments">' +
          --this.offlineCacheRemaining +
          '</span> segments in offline mode. Do not refresh or you lose the segments!',
        type: 'warning',
        position: 'bl',
        autoDismiss: false,
        allowHtml: true,
        timer: 7000,
      }
      CatToolActions.addNotification(notification)
    }
  },
  incrementOfflineCacheRemaining: function () {
    // reset counter by 1
    this.offlineCacheRemaining += 1
  },

  changeStatusOffline: function (sid) {
    if ($('#segment-' + sid + ' .editarea').text() != '') {
      SegmentActions.removeClassToSegment(sid, 'status-draft')
      SegmentActions.removeClassToSegment(sid, 'status-approved')
      SegmentActions.removeClassToSegment(sid, 'status-new')
      SegmentActions.removeClassToSegment(sid, 'status-rejected')
      SegmentActions.removeClassToSegment(sid, 'status-fixed')
      SegmentActions.removeClassToSegment(sid, 'status-rebutted')
      SegmentActions.addClassToSegment(sid, 'status-translated')
    }
  },
}

export default OfflineUtils
