import React from 'react'
import SegmentUtils from './utils/segmentUtils'
import {SEGMENTS_STATUS} from './constants/Constants'
import {isUndefined} from 'lodash'
import ModalsActions from './actions/ModalsActions'
import ConfirmMessageModal from './components/modals/ConfirmMessageModal'
import {
  UnmodifiedFuzzyMatchModal,
  HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE,
} from './components/modals/UnmodifiedFuzzyMatchModal'
import SegmentActions from './actions/SegmentActions'
import OfflineUtils from './utils/offlineUtils'
import SegmentStore from './stores/SegmentStore'
import CatToolActions from './actions/CatToolActions'
import CommonUtils from './utils/commonUtils'
import {setTranslation} from './api/setTranslation'

// Saves not sent yet, in the order they were made; a segment has at most one
let setTranslationQueue = []
// The save whose request is on its way, if any
let inFlightItem = null
export const segmentTranslation = (
  segment,
  status,
  callback,
  propagateIfNeeded = true,
  errorCallback,
) => {
  if (!segment) return
  const segmentIsToPropagate =
    segment.propagable && shouldSegmentAutoPropagate(segment, status)

  const execChangeStatus = ({propagate, autoPropagate}) => {
    SegmentActions.hideSegmentHeader(segment.sid)
    setTranslationTail({
      segment,
      status,
      propagate,
      autoPropagate,
      callback,
      errorCallback,
    })
    // Remove the modified class only if the segment is not in draft or new status
    if (
      [
        SEGMENTS_STATUS.DRAFT.toUpperCase(),
        SEGMENTS_STATUS.NEW.toUpperCase(),
      ].indexOf(status.toUpperCase()) === -1
    ) {
      SegmentActions.modifiedTranslation(segment.sid, false)
    }
  }

  const proceedWithTranslation = () => {
    if (
      propagateIfNeeded &&
      segmentIsToPropagate &&
      autoPropagateConfirmNeeded(segment)
    ) {
      const text =
        'The translation you are confirming for this segment is different from the versions confirmed for other identical segments</b>. <br><br>Would you like ' +
        'to propagate this translation to all other identical segments and replace the other versions or keep it only for this segment?'
      const props = {
        text: text,
        successText: 'Only this segment',
        successCallback: function () {
          execChangeStatus({propagate: false, autoPropagate: false})
          ModalsActions.onCloseModal()
        },
        cancelText: 'Propagate to All',
        cancelCallback: function () {
          execChangeStatus({propagate: true, autoPropagate: false})
          ModalsActions.onCloseModal()
        },
      }
      ModalsActions.showModalComponent(
        ConfirmMessageModal,
        props,
        'Confirmation required ',
      )
    } else {
      execChangeStatus({
        propagate: propagateIfNeeded && segmentIsToPropagate,
        autoPropagate: true,
      })
    }
  }

  // Warn the translator when a fuzzy match is being confirmed without any edit.
  if (unmodifiedFuzzyMatchWarningNeeded(segment, status)) {
    ModalsActions.showModalComponent(
      UnmodifiedFuzzyMatchModal,
      {
        successCallback: proceedWithTranslation,
      },
      'Confirm unedited fuzzy match?',
    )
    return
  }

  proceedWithTranslation()
}

/**
 * Determines whether to warn the translator before confirming a fuzzy TM match
 * that has not been modified.
 *
 * The check works even when a segment is opened, left untouched, navigated away
 * from, and reopened already populated: it compares the current translation with
 * the chosen (or best) suggestion text rather than relying on transient state.
 */
const unmodifiedFuzzyMatchWarningNeeded = (segment, status) => {
  if (config.isReview) return false

  const confirming =
    [
      SEGMENTS_STATUS.TRANSLATED,
      SEGMENTS_STATUS.APPROVED,
      SEGMENTS_STATUS.APPROVED2,
    ].indexOf((status || '').toUpperCase()) !== -1
  if (!confirming) return false

  if (localStorage.getItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE))
    return false

  const matches = segment.contributions?.matches ?? []
  if (!matches.length) return false

  const index =
    segment.choosenSuggestionIndex > 0 ? segment.choosenSuggestionIndex - 1 : 0
  const match = matches[index]
  if (!match || match.error) return false

  // Fuzzy band only: exclude MT, ICE (101%) and 100% exact matches.
  const percentage = parseInt(match.match)
  if (isNaN(percentage) || percentage <= 0 || percentage >= 100) return false

  // The user did not edit the suggestion that was copied into the edit area.
  return !segment.modified && segment.translation === match.translation
}
const autoPropagateConfirmNeeded = (segment) => {
  const segmentModified = segment.modified
  const segmentStatus = segment.status.toLowerCase()
  const statusNotConfirmationNeeded = [
    SEGMENTS_STATUS.NEW,
    SEGMENTS_STATUS.DRAFT,
  ]
  if (config.isReview) {
    return segmentModified || !isUndefined(segment.alternatives)
  } else {
    return (
      statusNotConfirmationNeeded.indexOf(segmentStatus.toUpperCase()) === -1 &&
      (segmentModified || !isUndefined(segment.alternatives))
    )
  }
}

const setTranslationTail = ({
  segment,
  status,
  propagate,
  autoPropagate,
  callback,
  errorCallback,
}) => {
  // An empty target is never sent. Refuse it here, before the segment is marked as saving: only the
  // answer to a request clears that state, so a segment marked and never sent stays saving forever.
  if (!segment.translation) {
    errorCallback?.()
    return
  }
  // Built now, while this is still the segment being edited: the request carries the editing time
  // and the context of this moment, and it may be sent after the translator has moved on
  const request = SegmentUtils.createSetTranslationRequest(
    segment,
    status,
    propagate,
  )
  SegmentStore.setLastTranslatedSegmentId(segment.sid)
  const queueItem = {
    segment,
    status,
    propagate,
    autoPropagate,
    request,
    errorCallback,
  }
  // A segment saved again while its previous save is still queued replaces it: only the latest
  // version is sent, and offline it does not take another slot of the cache. It goes to the end, as
  // the latest save: a save of a repetition made earlier must not propagate over it.
  const alreadyQueued = alreadyInSetTranslationTail(segment.sid)
  setTranslationQueue = setTranslationQueue.filter(
    (current) => current.segment.sid !== segment.sid,
  )
  setTranslationQueue.push(queueItem)
  SegmentActions.setSegmentSaving(segment.sid, true)
  const offline = OfflineUtils.offline && config.offlineModeEnabled
  if (offline) {
    if (!alreadyQueued) {
      OfflineUtils.decrementOfflineCacheRemaining()
      OfflineUtils.failedConnection()
    }
    OfflineUtils.changeStatusOffline(segment.sid)
    OfflineUtils.checkConnection()
  }
  try {
    // The translator moves on as soon as the save is queued and keeps working while it is sent; a
    // save that fails is reported through errorCallback
    callback?.()
  } finally {
    // Offline, the queue is sent when the connection is back (OfflineUtils.checkConnection)
    if (!offline) execSetTranslationTail()
  }
}

// Shown for a save the server did not refuse but did not complete either
const SAVE_FAILED_ERRORS = [
  {code: -1, message: 'The translation was not saved'},
]
// A save that got no answer is sent again, this many times in all, then reported as not saved. The
// same request failing again and again is not a lost connection but something on the way (a proxy
// dropping it), and it must not hold back the saves queued after it.
const MAX_CONNECTION_ATTEMPTS = 3
const CONNECTION_RETRY_DELAY_MS = 2000

/**
 * Sends the next queued save, unless one is already on its way. Saves go out one at a time, so two
 * saves of the same segment reach the server in the order they were made; each answer sends the
 * next one.
 */
export const execSetTranslationTail = () => {
  if (inFlightItem || !setTranslationQueue.length) return
  const item = setTranslationQueue.shift()
  inFlightItem = item
  const {segment, request, errorCallback} = item
  const idSegment = segment.sid

  const sendNext = () => {
    inFlightItem = null
    execSetTranslationTail()
  }
  const reportFailure = (errors) => {
    CatToolActions.processErrors(errors, 'setTranslation')
    errorCallback?.(errors)
  }

  // The segment stops showing as saving only once no newer save of it is waiting
  const doneSaving = () => {
    if (!alreadyInSetTranslationTail(idSegment)) {
      SegmentActions.setSegmentSaving(idSegment, false)
    }
  }

  // Returned so a throw while handling an answer stays visible: it rejects this promise and nothing
  // handles it, so it is reported as any unhandled error is. Whatever throws, the next save is sent
  // (finally): a throw must never stop the queue.
  return setTranslation(request).then(
    (data) => {
      try {
        doneSaving()
        if (data?.data !== 'OK') {
          reportFailure(SAVE_FAILED_ERRORS)
          return
        }
        // The server has the save: from here a throw is the page failing to show it, not a failed save
        SegmentActions.setChoosenSuggestion(idSegment, null)
        setTranslation_success(data, item)
        if (config.isReview) {
          SegmentActions.getSegmentVersionsIssues(idSegment)
          CatToolActions.reloadQualityReport()
        }
        CommonUtils.dispatchCustomEvent('setTranslation:success', {segment})
        if (config.alternativesEnabled) {
          SegmentActions.getTranslationMismatches(idSegment)
        }
      } finally {
        sendNext()
      }
    },
    (error) => {
      item.attempts = (item.attempts ?? 0) + 1
      if (isConnectionLost(error) && item.attempts < MAX_CONNECTION_ATTEMPTS) {
        retryAfterConnectionLoss(item)
        return
      }
      try {
        doneSaving()
        // The server refused the save (its errors), failed it, or it never got an answer however
        // many times it was sent. None is retried: the translator saves the segment again.
        reportFailure(
          Array.isArray(error?.errors) && error.errors.length
            ? error.errors
            : SAVE_FAILED_ERRORS,
        )
      } finally {
        sendNext()
      }
    },
  )
}

const retryAfterConnectionLoss = (item) => {
  const idSegment = item.segment.sid
  inFlightItem = null
  // Back at the head of the queue, so the saves still go out in the order they were made, unless
  // the segment was saved again meanwhile: the newer save replaces it. Still saving either way.
  if (!alreadyInSetTranslationTail(idSegment)) {
    setTranslationQueue.unshift(item)
  }
  OfflineUtils.changeStatusOffline(idSegment)
  // Pings the server: when it is unreachable this turns offline mode on, and the queue is sent when
  // the connection is back (OfflineUtils.checkConnection)
  OfflineUtils.startOfflineMode()
  // When it is reachable nothing else would send the queue again before the next save
  setTimeout(() => {
    if (!(OfflineUtils.offline && config.offlineModeEnabled)) {
      execSetTranslationTail()
    }
  }, CONNECTION_RETRY_DELAY_MS * item.attempts)
}

// The request never got an answer: setTranslation rejects with the response whenever the server
// answered, and fetch rejects without one when the connection is lost
const isConnectionLost = (error) => !error?.response && !error?.errors

const setTranslation_success = (response, item) => {
  const {segment, status} = item

  SegmentActions.setStatus(segment.sid, null, status)
  SegmentActions.setSavedTranslation(segment.sid, segment.translation)
  CatToolActions.setProgress(response)
  CatToolActions.checkWarnings(false)
  checkSegmentsPropagation(item, response.propagation)
}

const checkSegmentsPropagation = (item, propagationData) => {
  const {segment, status, propagate, autoPropagate} = item
  const idSegment = segment.sid
  if (propagate && propagationData?.propagated_ids) {
    if (
      propagationData.propagated_ids &&
      propagationData.propagated_ids.length > 0
    ) {
      SegmentActions.propagateTranslation(
        idSegment,
        propagationData.propagated_ids,
        status,
      )
    }
    if (!autoPropagate && propagationData.segments_for_propagation) {
      let text = (
        <>
          The segment translation has been propagated to the other repetitions.
        </>
      )
      if (
        propagationData.segments_for_propagation.not_propagated &&
        propagationData.segments_for_propagation.not_propagated.ice.id &&
        propagationData.segments_for_propagation.not_propagated.ice.id.length >
          0
      ) {
        text = (
          <>
            The segment translation has been{' '}
            <b>propagated to the other repetitions</b>.
            <br />
            Repetitions in <b>locked segments have been excluded</b> from the
            propagation.
          </>
        )
      } else if (
        propagationData.segments_for_propagation.not_propagated &&
        propagationData.segments_for_propagation.not_propagated.not_ice.id &&
        propagationData.segments_for_propagation.not_propagated.not_ice.id
          .length > 0
      ) {
        text = (
          <>
            The segment translation has been{' '}
            <b>propagated to the other repetitions in locked segments</b>.
            <br />
            Repetitions in <b>non-locked segments have been excluded</b> from
            the propagation.
          </>
        )
      }

      const notification = {
        title: 'Segment propagated',
        text: text,
        type: 'info',
        autoDismiss: true,
        timer: 5000,
        position: 'bl',
      }
      CatToolActions.removeAllNotifications()
      CatToolActions.addNotification(notification)
    }
  } else {
    SegmentActions.setSegmentPropagation(idSegment, null, false)
  }
}
/**
 * shouldSegmentAutoPropagate
 *
 * Returns whether or not the segment should be propagated. Default is true.
 *
 * @returns {boolean}
 */
const shouldSegmentAutoPropagate = (segment, status) => {
  const segmentStatus = segment.status.toLowerCase()
  const statusAcceptedNotModified = ['new', 'draft']
  const segmentModified = segment.modified
  return (
    segmentModified ||
    statusAcceptedNotModified.indexOf(segmentStatus) !== -1 ||
    (!segmentModified && status.toLowerCase() !== segmentStatus)
  )
}
export const translationIsToSaveBeforeClose = (segment) => {
  // add to setTranslation tail
  const alreadySet = alreadyInSetTranslationTail(segment.sid)
  const emptyTranslation = segment && segment.translation.length === 0

  return (
    !alreadySet &&
    !emptyTranslation &&
    segment.modified &&
    (segment.status === config.status_labels.NEW.toUpperCase() ||
      segment.status === config.status_labels.DRAFT.toUpperCase())
  )
}
const alreadyInSetTranslationTail = (sid) =>
  setTranslationQueue.some((item) => item.segment.sid === sid)
export const isTranslationTailEmpty = () => {
  // A save on its way is not saved yet: leaving the page now would lose it
  return !inFlightItem && setTranslationQueue.length === 0
}
