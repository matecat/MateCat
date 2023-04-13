import _ from 'lodash'
import Cookies from 'js-cookie'

import CatToolActions from './es6/actions/CatToolActions'
import CommonUtils from './es6/utils/commonUtils'
import ConfirmMessageModal from './es6/components/modals/ConfirmMessageModal'
import TagUtils from './es6/utils/tagUtils'
import TextUtils from './es6/utils/textUtils'
import OfflineUtils from './es6/utils/offlineUtils'
import SegmentActions from './es6/actions/SegmentActions'
import SegmentStore from './es6/stores/SegmentStore'
import {getTranslationMismatches} from './es6/api/getTranslationMismatches'
import {getGlobalWarnings} from './es6/api/getGlobalWarnings'
import {setTranslation} from './es6/api/setTranslation'
import AlertModal from './es6/components/modals/AlertModal'
import ModalsActions from './es6/actions/ModalsActions'

window.UI = {
  cacheObjects: function (editarea_or_segment) {
    var segment, $segment

    this.editarea = $('.targetarea', $(editarea_or_segment).closest('section'))
    $segment = $(editarea_or_segment).closest('section')
    segment = SegmentStore.getSegmentByIdToJS(UI.getSegmentId($segment))

    if (!$segment.length || !segment) {
      return
    }

    this.currentSegmentId = segment.sid
    this.currentSegment = $segment
  },

  removeCacheObjects: function () {
    this.editarea = ''
    this.currentSegmentId = undefined
    this.currentSegment = undefined
  },
  /**
   * shouldSegmentAutoPropagate
   *
   * Returns whether or not the segment should be propagated. Default is true.
   *
   * @returns {boolean}
   */
  shouldSegmentAutoPropagate: function (segment, status) {
    var segmentStatus = segment.status.toLowerCase()
    var statusAcceptedNotModified = ['new', 'draft']
    var segmentModified = segment.modified
    return (
      segmentModified ||
      statusAcceptedNotModified.indexOf(segmentStatus) !== -1 ||
      (!segmentModified && status.toLowerCase() !== segmentStatus) ||
      (!segmentModified &&
        status.toLowerCase() === segmentStatus &&
        segmentStatus === 'approved' &&
        config.revisionNumber !== segment.revision_number)
    ) // from R1 to R2 and reverse
  },

  /**
   *
   * @param segment
   * @param status
   * @param callback
   */
  changeStatus: function (segment, status, callback) {
    var segment_id = segment.sid
    var opts = {
      segment_id: segment_id,
      status: status,
      propagation:
        segment.propagable && UI.shouldSegmentAutoPropagate(segment, status),
      callback: callback,
    }

    // ask if the user wants propagation or this is valid only
    // for this segment

    if (this.autopropagateConfirmNeeded(segment, opts.propagation)) {
      var text = !_.isUndefined(segment.alternatives)
        ? 'The translation you are confirming for this segment is different from the versions confirmed for other identical segments</b>. <br><br>Would you like ' +
          'to propagate this translation to all other identical segments and replace the other versions or keep it only for this segment?'
        : 'The translation you are confirming for this segment is different from the version confirmed for other identical segments. <br><br>Would you ' +
          'like to propagate this translation to all other identical segments and replace the other version or keep it only for this segment?'
      // var optionsStr = opts;
      var props = {
        text: text,
        successText: 'Only this segment',
        successCallback: function () {
          opts.propagation = false
          opts.autoPropagation = false
          UI.preExecChangeStatus(opts)
          ModalsActions.onCloseModal()
        },
        cancelText: 'Propagate to All',
        cancelCallback: function () {
          opts.propagation = true
          opts.autoPropagation = false
          UI.execChangeStatus(opts)
          ModalsActions.onCloseModal()
        },
        onClose: function () {
          UI.preExecChangeStatus(opts)
        },
      }
      ModalsActions.showModalComponent(
        ConfirmMessageModal,
        props,
        'Confirmation required ',
      )
    } else {
      opts.autoPropagation = true
      this.execChangeStatus(opts) // autopropagate
    }
  },

  autopropagateConfirmNeeded: function (segment, propagation) {
    var segmentModified = segment.modified
    var segmentStatus = segment.status.toLowerCase()
    var statusNotConfirmationNeeded = ['new', 'draft']
    if (propagation) {
      if (config.isReview) {
        return segmentModified || !_.isUndefined(segment.alternatives)
      } else {
        return (
          statusNotConfirmationNeeded.indexOf(segmentStatus) === -1 &&
          (segmentModified || !_.isUndefined(segment.alternatives))
        )
      }
    }
    return false
  },
  preExecChangeStatus: function (optStr) {
    var opt = optStr
    opt.propagation = false
    this.execChangeStatus(opt)
  },
  execChangeStatus: function (optStr) {
    var options = optStr

    var propagation = options.propagation
    var status = options.status

    SegmentActions.hideSegmentHeader(options.segment_id)

    this.setTranslation(
      {
        id_segment: options.segment_id,
        status: status,
        propagate: propagation,
        autoPropagation: options.autoPropagation,
      },
      optStr.callback,
    )

    SegmentActions.modifiedTranslation(options.segment_id, false)
  },

  getSegmentId: function (segment) {
    if (typeof segment == 'undefined') return false
    if (segment.el) {
      return segment.el.attr('id').replace('segment-', '')
    }
    try {
      segment = segment.closest('section')
      return $(segment).attr('id').replace('segment-', '')
    } catch (e) {
      return false
    }
  },

  getTranslationMismatches: function (id_segment) {
    getTranslationMismatches({
      password: config.password,
      id_segment: id_segment.toString(),
      id_job: config.id_job,
    })
      .then((data) => {
        UI.detectTranslationAlternatives(data, id_segment)
      })
      .catch((errors) => {
        if (errors.length) {
          UI.processErrors(errors, 'setTranslation')
        } else {
          OfflineUtils.failedConnection(id_segment, 'getTranslationMismatches')
        }
      })
  },

  detectTranslationAlternatives: function (d, id_segment) {
    var sameContentIndex = -1
    var segmentObj = SegmentStore.getSegmentByIdToJS(id_segment)
    $.each(d.data.editable, function (ind) {
      if (this.translation === segmentObj.translation) {
        sameContentIndex = ind
      }
    })
    if (sameContentIndex != -1) d.data.editable.splice(sameContentIndex, 1)

    let sameContentIndex1 = -1
    $.each(d.data.not_editable, function (ind) {
      //Remove trailing spaces for string comparison
      if (this.translation === segmentObj.translation) {
        sameContentIndex1 = ind
      }
    })
    if (sameContentIndex1 != -1)
      d.data.not_editable.splice(sameContentIndex1, 1)

    var numAlt = d.data.editable.length + d.data.not_editable.length

    if (numAlt) {
      // UI.renderAlternatives(d);
      SegmentActions.setAlternatives(id_segment, d.data)
      SegmentActions.activateTab(id_segment, 'alternatives')
      SegmentActions.setTabIndex(id_segment, 'alternatives', numAlt)
    }
  },

  setTimeToEdit: function (sid) {
    let $segment = UI.getSegmentById(sid)
    this.editStop = new Date()
    var tte = $('.timetoedit', $segment)
    this.editTime = this.editStop - this.editStart
    this.totalTime = this.editTime + tte.data('raw-time-to-edit')
    var editedTime = CommonUtils.millisecondsToTime(this.totalTime)
    if (config.time_to_edit_enabled) {
      var editSec = $('.timetoedit .edit-sec', $segment)
      var editMin = $('.timetoedit .edit-min', $segment)
      editMin.text(
        editedTime[0].length > 1 ? editedTime[0] : '0' + editedTime[0],
      )
      editSec.text(
        editedTime[1].length > 1 ? editedTime[1] : '0' + editedTime[1],
      )
    }
    tte.data('raw-time-to-edit', this.totalTime)
  },
  goToFirstError: function () {
    CatToolActions.toggleQaIssues()
    setTimeout(function () {
      $('.button.qa-issue').first().click()
    }, 300)
  },
  setDownloadStatus: function (stats) {
    var t = CommonUtils.getTranslationStatus(stats)

    var downloadable = t === 'translated' || t.indexOf('approved') > -1

    var isGDriveFile = false

    if (config.isGDriveProject && config.isGDriveProject !== 'false') {
      isGDriveFile = true
    }

    var label = ''

    if (downloadable) {
      if (isGDriveFile) {
        label = 'Open in Google Drive'
      } else {
        label = 'Download Translation'
      }
      $('#action-download').addClass('job-completed')
    } else {
      if (isGDriveFile) {
        label = 'Preview in Google Drive'
      } else {
        label = 'Draft'
      }
      $('#action-download').removeClass('job-completed')
    }

    $('#action-download .downloadTranslation a').text(label)
    $('#action-download .previewLink a').text(label)
  },
  disableDownloadButtonForDownloadStart: function () {
    $('#action-download').addClass('disabled')
  },

  reEnableDownloadButton: function () {
    $('#action-download').removeClass('disabled')
  },

  continueDownloadWithGoogleDrive: function (openOriginalFiles) {
    if ($('#downloadProject').hasClass('disabled')) {
      return
    }
    UI.disableDownloadButtonForDownloadStart(openOriginalFiles)

    APP.downloadGDriveFile(
      openOriginalFiles,
      config.id_job,
      config.password,
      UI.reEnableDownloadButton,
    )
  },

  continueDownload: function () {
    if ($('#downloadProject').hasClass('disabled')) {
      return
    }

    //UI.showDownloadCornerTip();

    UI.disableDownloadButtonForDownloadStart()

    APP.downloadFile(
      config.id_job,
      config.password,
      UI.reEnableDownloadButton.bind(this),
    )
  },

  runDownload: function () {
    const globalWarnings = SegmentStore.getGlobalWarnings()
    var continueDownloadFunction

    if ($('#downloadProject').hasClass('disabled')) return false

    if (config.isGDriveProject) {
      continueDownloadFunction = UI.continueDownloadWithGoogleDrive
    } else {
      continueDownloadFunction = UI.continueDownload
    }

    //the translation mismatches are not a severe Error, but only a warn, so don't display Error Popup
    if (
      globalWarnings.matecat.ERROR &&
      globalWarnings.matecat.ERROR.total > 0
    ) {
      ModalsActions.showDownloadWarningsModal(
        continueDownloadFunction,
        UI.goToFirstError,
      )
    } else {
      continueDownloadFunction()
    }
  },
  startWarning: function () {
    clearTimeout(UI.startWarningTimeout)
    UI.startWarningTimeout = setTimeout(function () {
      // If the tab is not active avoid to make the warnings call
      if (document.visibilityState === 'hidden') {
        UI.startWarning()
      } else {
        UI.checkWarnings(false)
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
        UI.startWarning()

        //check for errors
        if (data.details) {
          SegmentActions.updateGlobalWarnings(data.details)
        }

        // check for messages
        if (data.messages) {
          var msgArray = $.parseJSON(data.messages)
          if (msgArray.length > 0) {
            UI.displayMessage(msgArray)
          }
        }

        $(document).trigger('getWarning:global:success', {resp: data})
      })
      .catch((errors) => {
        UI.warningStopped = true
        OfflineUtils.failedConnection(0, 'getWarning')
      })
  },
  displayMessage: function (messages) {
    var self = this
    if ($('body').hasClass('incomingMsg')) return false
    $.each(messages, function () {
      var elem = this
      if (
        typeof Cookies.get('msg-' + this.token) == 'undefined' &&
        new Date(this.expire) > new Date() &&
        typeof self.displayedMessages !== 'undefined' &&
        self.displayedMessages.indexOf(this.token) < 0
      ) {
        var notification = {
          title: 'Notice',
          text: this.msg,
          type: 'warning',
          autoDismiss: false,
          position: 'bl',
          allowHtml: true,
          closeCallback: function () {
            var expireDate = new Date(elem.expire)
            Cookies.set('msg-' + elem.token, '', {
              expires: expireDate,
              secure: true,
            })
          },
        }
        CatToolActions.addNotification(notification)
        self.displayedMessages.push(elem.token)
        return false
      }
    })
  },
  checkVersion: function () {
    if (this.version != config.build_number) {
      var notification = {
        uid: 'checkVersion',
        title: 'New version of Matecat',
        text: 'A new version of Matecat has been released. Please <a href="#" class="reloadPage">click here</a> or press CTRL+F5 (or CMD+R on Mac) to update.',
        type: 'warning',
        allowHtml: true,
        position: 'bl',
      }
      CatToolActions.addNotification(notification)
    }
  },
  registerQACheck: function () {
    clearTimeout(UI.pendingQACheck)
    UI.pendingQACheck = setTimeout(function () {
      SegmentActions.getSegmentsQa(SegmentStore.getCurrentSegment())
    }, config.segmentQACheckInterval)
  },

  translationIsToSave: function (segment) {
    // add to setTranslation tail
    var alreadySet = this.alreadyInSetTranslationTail(segment.sid)
    var emptyTranslation = segment && segment.translation.length === 0

    return !alreadySet && !emptyTranslation
  },

  translationIsToSaveBeforeClose: function (segment) {
    // add to setTranslation tail
    var alreadySet = this.alreadyInSetTranslationTail(segment.sid)
    var emptyTranslation = segment && segment.translation.length === 0

    return (
      !alreadySet &&
      !emptyTranslation &&
      segment.modified &&
      (segment.status === config.status_labels.NEW.toUpperCase() ||
        segment.status === config.status_labels.DRAFT.toUpperCase())
    )
  },

  setTranslation: function (options, callback) {
    var id_segment = options.id_segment
    var status = options.status
    var propagate = options.propagate || false

    var segment = SegmentStore.getSegmentByIdToJS(id_segment)

    if (!segment) {
      return
    }

    //REMOVED Check for to save
    //Send ALL to the queue
    var item = {
      id_segment: id_segment,
      status: status,
      propagate: propagate,
      autoPropagation: options.autoPropagation,
    }
    //Check if the traslation is not already in the tail
    var saveTranslation = this.translationIsToSave(segment)
    // If not i save it or update
    if (saveTranslation) {
      this.addToSetTranslationTail(item)
    } else {
      this.updateToSetTranslationTail(item)
    }
    SegmentActions.setSegmentSaving(id_segment, true)
    // If is offline and is in the tail I decrease the counter
    // else I execute the tail
    if (OfflineUtils.offline && config.offlineModeEnabled) {
      if (saveTranslation) {
        OfflineUtils.decrementOfflineCacheRemaining()
        options.callback = OfflineUtils.incrementOfflineCacheRemaining
        OfflineUtils.failedConnection(options, 'setTranslation')
      }
      OfflineUtils.changeStatusOffline(id_segment)
      OfflineUtils.checkConnection('Set Translation check Authorized')
      if (callback) {
        callback.call(this)
      }
    } else {
      if (this.executingSetTranslation.indexOf(id_segment) === -1) {
        return this.execSetTranslationTail(callback)
      }
    }
  },
  alreadyInSetTranslationTail: function (sid) {
    var alreadySet = false
    $.each(UI.setTranslationTail, function () {
      if (this.id_segment == sid) alreadySet = true
    })
    return alreadySet
  },

  addToSetTranslationTail: function (item) {
    SegmentActions.addClassToSegment(item.id_segment, 'setTranslationPending')
    this.setTranslationTail.push(item)
  },
  updateToSetTranslationTail: function (item) {
    SegmentActions.addClassToSegment(item.id_segment, 'setTranslationPending')

    $.each(UI.setTranslationTail, function () {
      if (this.id_segment == item.id_segment) {
        this.status = item.status
        this.callback = item.callback
        this.propagate = item.propagate
      }
    })
  },
  execSetTranslationTail: function (callback_to_execute) {
    if (UI.setTranslationTail.length) {
      var item = UI.setTranslationTail[0]
      UI.setTranslationTail.shift() // to move on ajax callback
      return UI.execSetTranslation(item, callback_to_execute)
    }
  },

  execSetTranslation: function (options, callback_to_execute) {
    var id_segment = options.id_segment
    var status = options.status
    var propagate = options.propagate
    var sourceSegment, translation
    this.executingSetTranslation.push(id_segment)
    var reqArguments = arguments
    let segment = SegmentStore.getSegmentByIdToJS(id_segment)

    this.lastTranslatedSegmentId = id_segment

    try {
      // Attention, to be modified when we will lock tags
      translation = TagUtils.prepareTextToSend(segment.translation)
      sourceSegment = TagUtils.prepareTextToSend(segment.updatedSource)
    } catch (e) {
      var indexSegment = UI.executingSetTranslation.indexOf(id_segment)
      if (indexSegment > -1) {
        UI.executingSetTranslation.splice(indexSegment, 1)
      }
      return false
    }
    if (translation === '') {
      this.unsavedSegmentsToRecover.push(this.currentSegmentId)
      var index = this.executingSetTranslation.indexOf(id_segment)
      if (index > -1) {
        this.executingSetTranslation.splice(index, 1)
      }
      return false
    }

    var isSplitted = segment.splitted
    if (isSplitted) {
      translation = this.collectSplittedTranslations(segment.original_sid)
      sourceSegment = this.collectSplittedTranslations(
        segment.original_sid,
        '.source',
      )
    }
    let requestArgs = {
      segment,
      status: status,
      translation: translation,
      source: sourceSegment,
      chosenSuggestionIndex: segment.choosenSuggestionIndex,
      propagate: propagate,
    }
    if (isSplitted) {
      SegmentActions.setStatus(segment.original_sid, null, status)
      requestArgs.splitStatuses = this.collectSplittedStatuses(
        segment.original_sid,
        segment.sid,
        status,
      ).toString()
    }

    if (callback_to_execute) {
      callback_to_execute.call(this)
    }

    setTranslation(requestArgs)
      .then((data) => {
        const idSegment = options.id_segment
        const index = UI.executingSetTranslation.indexOf(idSegment)
        if (index > -1) {
          UI.executingSetTranslation.splice(index, 1)
        }
        if (typeof callback == 'function') {
          callback(data)
        }
        UI.execSetTranslationTail()
        UI.setTranslation_success(data, options)
        SegmentActions.setSegmentSaving(id_segment, false)
        data.translation.segment = segment
        $(document).trigger('translation:change', data.translation)
        data.segment = segment
        $(document).trigger('setTranslation:success', data)
        if (config.alternativesEnabled) {
          UI.getTranslationMismatches(id_segment)
        }
      })
      .catch(({errors}) => {
        const idSegment = options.id_segment
        const index = UI.executingSetTranslation.indexOf(idSegment)
        if (index > -1) {
          UI.executingSetTranslation.splice(index, 1)
        }
        if (errors && errors.length) {
          this.processErrors(errors, 'setTranslation')
        } else {
          UI.addToSetTranslationTail(options)
          OfflineUtils.changeStatusOffline(idSegment)
          OfflineUtils.failedConnection(reqArguments, 'setTranslation')
          OfflineUtils.decrementOfflineCacheRemaining()
        }
        SegmentActions.setSegmentSaving(id_segment, false)
      })
  },

  collectSplittedStatuses: function (sid, splittedSid, status) {
    var statuses = []
    var segments = SegmentStore.getSegmentsInSplit(sid)
    $.each(segments, function () {
      var segment = SegmentStore.getSegmentByIdToJS(this.sid)
      if (splittedSid === this.sid) {
        statuses.push(status)
      } else {
        statuses.push(segment.status)
      }
    })
    return statuses
  },
  /**
   *
   * @param sid
   * @param selector
   * @returns {string}
   */
  collectSplittedTranslations: function (sid, selector) {
    var totalTranslation = ''
    var segments = SegmentStore.getSegmentsInSplit(sid)
    $.each(segments, function (index) {
      var segment = this
      totalTranslation +=
        selector === '.source'
          ? segment.segment
          : TagUtils.prepareTextToSend(segment.translation)
      if (index < segments.length - 1)
        totalTranslation += UI.splittedTranslationPlaceholder
    })
    return totalTranslation
  },

  processErrors: function (err, operation) {
    $.each(err, function () {
      var codeInt = parseInt(this.code)

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

      if (codeInt <= -2000 && !_.isUndefined(this.message)) {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text:
              'You cannot change the status of an ICE segment to "Translated" without editing it first.</br>' +
              'Please edit the segment first if you want to change its status to "Translated".',
          },
          'Error',
        )
      }
    })
  },
  setTranslation_success: function (response, options) {
    var id_segment = options.id_segment
    var status = options.status
    var propagate = options.propagate
    var segment = $('#segment-' + id_segment)

    if (response.data == 'OK') {
      SegmentActions.setStatus(id_segment, null, status)
      this.setDownloadStatus(response.stats)
      CatToolActions.setProgress(response.stats)
      SegmentActions.removeClassToSegment(id_segment, 'setTranslationPending')

      this.checkWarnings(false)
      $(segment).attr('data-version', response.version)

      UI.checkSegmentsPropagation(
        propagate,
        options.autoPropagation,
        id_segment,
        response.propagation,
        status,
      )
    }
    this.resetRecoverUnsavedSegmentsTimer()
  },
  checkSegmentsPropagation: function (
    propagate,
    autoPropagate,
    id_segment,
    propagationData,
    status,
  ) {
    if (propagate) {
      if (
        propagationData.propagated_ids &&
        propagationData.propagated_ids.length > 0
      ) {
        SegmentActions.propagateTranslation(
          id_segment,
          propagationData.propagated_ids,
          status,
        )
      }
      if (autoPropagate) {
        return
      }
      var text =
        'The segment translation has been propagated to the other repetitions.'
      if (
        propagationData.segments_for_propagation.not_propagated &&
        propagationData.segments_for_propagation.not_propagated.ice.id &&
        propagationData.segments_for_propagation.not_propagated.ice.id.length >
          0
      ) {
        text =
          'The segment translation has been <b>propagated to the other repetitions</b>.</br> Repetitions in <b>locked segments have been excluded</b> from the propagation.'
      } else if (
        propagationData.segments_for_propagation.not_propagated &&
        propagationData.segments_for_propagation.not_propagated.not_ice.id &&
        propagationData.segments_for_propagation.not_propagated.not_ice.id
          .length > 0
      ) {
        text =
          'The segment translation has been <b>propagated to the other repetitions in locked segments</b>. </br> Repetitions in <b>non-locked segments have been excluded</b> from the' +
          ' propagation.'
      }

      var notification = {
        title: 'Segment propagated',
        text: text,
        type: 'info',
        autoDismiss: true,
        timer: 5000,
        allowHtml: true,
        position: 'bl',
      }
      CatToolActions.removeAllNotifications()
      CatToolActions.addNotification(notification)
    } else {
      SegmentActions.setSegmentPropagation(id_segment, null, false)
    }
  },
  recoverUnsavedSetTranslations: function () {
    $.each(UI.unsavedSegmentsToRecover, function (index) {
      if ($('#segment-' + this + ' .editarea').text() === '') {
        UI.resetRecoverUnsavedSegmentsTimer()
      } else {
        UI.setTranslation({
          id_segment: this.toString(),
          status: 'translated',
        })
        UI.unsavedSegmentsToRecover.splice(index, 1)
      }
    })
  },
  resetRecoverUnsavedSegmentsTimer: function () {
    clearTimeout(this.recoverUnsavedSegmentsTimer)
    this.recoverUnsavedSegmentsTimer = setTimeout(function () {
      UI.recoverUnsavedSetTranslations()
    }, 1000)
  },
  setTagLockCustomizeCookie: function (first) {
    if (first && !config.tagLockCustomizable) {
      UI.tagLockEnabled = true
      return true
    }
    var cookieName = 'tagLockDisabled'

    if (typeof Cookies.get(cookieName + '-' + config.id_job) != 'undefined') {
      if (first) {
        if (Cookies.get(cookieName + '-' + config.id_job) == 'true') {
          this.tagLockEnabled = false
          setTimeout(function () {
            $('.editor .tagLockCustomize').addClass('unlock')
          }, 100)
        } else {
          this.tagLockEnabled = true
        }
      } else {
        Cookies.set(cookieName + '-' + config.id_job, !this.tagLockEnabled, {
          expires: 30,
          secure: true,
        })
      }
    } else {
      Cookies.set(cookieName + '-' + config.id_job, !this.tagLockEnabled, {
        expires: 30,
        secure: true,
      })
    }
  },
  /**
   * After User click on Translated or T+>> Button
   * @param segment
   * @param goToNextUntranslated
   */
  clickOnTranslatedButton: function (segment, goToNextUntranslated) {
    var sid = UI.currentSegmentId
    //??
    $('.temp-highlight-tags').remove()

    SegmentActions.removeClassToSegment(sid, 'modified')

    UI.setTimeToEdit(segment.sid)

    var afterTranslateFn = function () {
      if (!goToNextUntranslated) {
        UI.gotoNextSegment() //Others functionality override this function
        // SegmentActions.openSegment(UI.nextSegmentId);
      } else {
        SegmentActions.gotoNextUntranslatedSegment()
      }
    }

    UI.changeStatus(segment, 'translated', afterTranslateFn)
  },

  // Project completion override this method
  handleClickOnReadOnly: function (section) {
    console.log(section)
    const projectCompletionCheck =
      config.project_completion_feature_enabled &&
      !config.isReview &&
      config.job_completion_current_phase == 'revise'
    if (projectCompletionCheck) {
      let message =
        'All segments are in <b>read-only mode</b> because this job is under review.'

      if (config.chunk_completion_undoable && config.last_completion_event_id) {
        message =
          message +
          '<p class=\'warning-call-to\'><a href="javascript:void(0);" id="showTranslateWarningMessageUndoLink" >Re-Open Job</a></p>'
      }

      CatToolActions.addNotification({
        uid: 'translate-warning',
        autoDismiss: false,
        dismissable: true,
        position: 'tc',
        text: message,
        title: 'Warning',
        type: 'warning',
        allowHtml: true,
      })
    }
    if (TextUtils.justSelecting('readonly')) return
    clearTimeout(UI.selectingReadonly)
    if (section.hasClass('ice-locked') || section.hasClass('ice-unlocked')) {
      UI.selectingReadonly = setTimeout(function () {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: UI.messageForClickOnIceMatch(),
          },
          'Ice Match',
        )
      }, 200)
      return
    }

    UI.selectingReadonly = setTimeout(function () {
      UI.readonlyClickDisplay()
    }, 200)
  },
  readonlyClickDisplay: function () {
    ModalsActions.showModalComponent(AlertModal, {
      text: UI.messageForClickOnReadonly(),
    })
  },
  messageForClickOnReadonly: function () {
    const projectCompletionCheck =
      config.project_completion_feature_enabled &&
      !config.isReview &&
      config.job_completion_current_phase == 'revise'
    if (projectCompletionCheck) {
      return 'This job is currently under review. Segments are in read-only mode.'
    }
    const msgArchived = 'Job has been archived and cannot be edited.'
    const msgOther = 'This part has not been assigned to you.'
    return UI.body.hasClass('archived') ? msgArchived : msgOther
  },
  messageForClickOnIceMatch: function () {
    return (
      'Segment is locked (in-context exact match) and shouldn’t be edited. ' +
      'If you must edit it, click on the padlock icon to the left of the segment. ' +
      'The owner of the project will be notified of any edits.'
    )
  },
  openOptionsPanel: function () {
    if ($('.popup-tm').hasClass('open')) {
      return false
    }
    var tab = 'opt'
    $('body').addClass('side-popup')
    $('.popup-tm').addClass('open').show().animate({right: '0px'}, 400)
    $('.outer-tm').show()
    $('.mgmt-panel-tm .nav-tabs .mgmt-' + tab).click()
  },

  closeAllMenus: function () {
    CatToolActions.closeSubHeader()
  },
  // overridden by plugin
  inputEditAreaEventHandler: function () {
    UI.currentSegment.trigger('modified')
  },
}

$(document).ready(function () {
  UI.start()
})
