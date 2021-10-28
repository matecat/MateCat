import SegmentActions from '../actions/SegmentActions'

const OfflineUtils = {
  offline: false,
  offlineCacheSize: 20,
  offlineCacheRemaining: 20,
  checkingConnection: false,
  currentConnectionCountdown: null,
  _backupEvents: null,
  abortedOperations: [],

  startOfflineMode: function () {
    if (!this.offline) {
      this.offline = true
      UI.body.attr('data-offline-mode', 'light-off')

      this.removeOldConnectionNotification()
      var notification = {
        title:
          '<div class="message-offline-icons"><span class="icon-power-cord"/><span class="icon-power-cord2"/></div>No connection available',
        text:
          'You can still translate <span class="remainingSegments">' +
          this.offlineCacheSize +
          '</span> segments in offline mode. Do not refresh or you lose the segments!',
        type: 'warning',
        position: 'bl',
        autoDismiss: false,
        allowHtml: true,
        timer: 7000,
      }
      this.offlineNotification = APP.addNotification(notification)

      this.checkingConnection = setInterval(() => {
        this.checkConnection('Recursive Check authorized')
      }, 5000)
    }
  },

  endOfflineMode: function () {
    if (this.offline) {
      this.offline = false
      this.removeOldConnectionNotification()
      var notification = {
        title: 'Connection is back',
        text: 'We are saving translated segments in the database.',
        type: 'success',
        position: 'bl',
        autoDismiss: true,
        timer: 10000,
        openCallback: () => {
          this.removeOldConnectionNotification()
        },
      }
      this.offlineNotification = APP.addNotification(notification)

      clearInterval(this.currentConnectionCountdown)
      clearInterval(this.checkingConnection)
      this.currentConnectionCountdown = null
      this.checkingConnection = false
      UI.body.removeAttr('data-offline-mode')

      $('.noConnectionMsg').text(
        'The connection is back. Your last, interrupted operation has now been done.',
      )

      setTimeout(function () {
        $('.noConnection').addClass('reConnection')
        setTimeout(() => {
          $('.noConnection, .noConnectionMsg').remove()
          if (this._backupEvents) {
            $._data($('body')[0]).events = this._backupEvents
            this._backupEvents = null
          }
        }, 500)
      }, 3000)
    }
  },

  removeOldConnectionNotification: function () {
    if (typeof this.offlineNotification != 'undefined') {
      APP.removeNotification(this.offlineNotification)
    }
  },

  failedConnection: function (reqArguments, operation) {
    this.startOfflineMode()

    if (operation != 'getWarning') {
      var pendingConnection = {
        operation: operation,
        args: reqArguments,
      }
      this.abortedOperations.push(pendingConnection)
    }
  },

  activateOfflineCountdown: function (message) {
    if (!this.offline) {
      UI.body.find('.noConnection').remove()
      return
    }
    if (UI.body.find('.noConnection').length === 0) {
      UI.body.append('<div class="noConnection"></div>')
    }
    $('.noConnection').html(
      '<div class="noConnectionMsg">' +
        message +
        '<br />' +
        '<span class="reconnect">Trying to reconnect in <span class="countdown">30 seconds</span>.</span><br /><br />' +
        '<input type="button" id="checkConnection" value="Try to reconnect now" /></div>',
    )

    //remove focus from the edit area
    setTimeout(() => {
      $('#checkConnection').focus()
      this._backupEvents = $._data($('body')[0]).events
      $._data($('body')[0]).events = {}
    }, 300)

    $('.noConnection #checkConnection').on('click', function () {
      OfflineUtils.checkConnection('Click from Human Authorized')
    })

    this.removeOldConnectionNotification()
    let timeleft = 30
    let countdown = setInterval(() => {
      if (timeleft === 0) {
        this.checkConnection('Clear countdown authorized')
        this.activateOfflineCountdown('Still no connection.')
        clearInterval(countdown)
        $('.noConnection #checkConnection').off('click')
      } else {
        timeleft--
        $('.noConnectionMsg .countdown').text(timeleft + ' seconds')
      }
    }, 1000)
  },
  checkConnection: function (message) {
    console.log(message)
    console.log('check connection')

    APP.doRequest({
      data: {
        action: 'ajaxUtils',
        exec: 'ping',
      },
      error: function () {
        /**
         * do Nothing there are already a thread running
         * @see UI.startOfflineMode
         * @see UI.endOfflineMode
         */
      },
      success: () => {
        console.log('check connection success')
        //check status completed
        if (!this.restoringAbortedOperations) {
          this.restoringAbortedOperations = true
          this.execAbortedOperations(() => this.endOfflineMode())
          this.restoringAbortedOperations = false
          UI.executingSetTranslation = []
          UI.execSetTranslationTail()

          //reset counter
          this.offlineCacheRemaining = this.offlineCacheSize
        }
      },
    })
  },

  /**
   * If there are some callback to be executed after the function call pass it as callback
   *
   * Note: the function stack is executed when the interpreter exit from the local scope
   * so, UI[operation] will be executed after the call of callback_to_execute.
   *
   * If we put the callback_to_execute out of this scope
   *      ( calling after the return of this function and not from inside it )
   *
   * UI[operation] will be executed before callback_to_execute.
   * Not working as expected because this behaviour affects "UI.offline = false;"
   *
   *
   * @param callback_to_execute
   */
  execAbortedOperations: function (callback_to_execute) {
    callback_to_execute = callback_to_execute || {}
    callback_to_execute.call()
    //console.log(UI.abortedOperations);
    $.each(this.abortedOperations, function () {
      var args = this.args
      var operation = this.operation
      if (operation === 'getSegments') {
        UI[operation]()
      } else if (operation === 'getMoreSegments') {
        UI.loadingMore = false
        UI[operation](args)
      }
    })
    this.abortedOperations = []
  },

  checkOfflineCacheSize: function () {
    if (this.offlineCacheRemaining <= 0) {
      this.activateOfflineCountdown('No connection available.')
    }
  },
  decrementOfflineCacheRemaining: function () {
    if (typeof this.offlineNotification != 'undefined') {
      APP.removeNotification(this.offlineNotification)
    }
    var notification = {
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
    this.offlineNotification = APP.addNotification(notification)

    this.checkOfflineCacheSize()
  },
  incrementOfflineCacheRemaining: function () {
    // reset counter by 1
    this.offlineCacheRemaining += 1
    //$('#messageBar .remainingSegments').text( this.offlineCacheRemaining );
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
