import SegmentUtils from './utils/segmentUtils'
import {SEGMENTS_STATUS} from './constants/Constants'
import {isUndefined} from 'lodash'
import ModalsActions from './actions/ModalsActions'
import ConfirmMessageModal from './components/modals/ConfirmMessageModal'
import SegmentActions from './actions/SegmentActions'
import OfflineUtils from './utils/offlineUtils'
import SegmentStore from './stores/SegmentStore'
import CatToolActions from './actions/CatToolActions'
import CommonUtils from './utils/commonUtils'
import {setTranslation} from './api/setTranslation'

let setTranslationQueue = []
export const segmentTranslation = (
  segment,
  status,
  callback,
  propagateIfNeeded = true,
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
    })
  }

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
}) => {
  const queueItem = {
    segment,
    status,
    propagate,
    autoPropagate,
    callback,
  }
  //Check if the translation is not already in the tail
  const saveTranslation = translationIsToSave(segment)
  // If not save it or update
  if (saveTranslation) {
    SegmentActions.addClassToSegment(
      queueItem.segment.sid,
      'setTranslationPending',
    )
    setTranslationQueue.push(queueItem)
  } else {
    SegmentActions.addClassToSegment(
      queueItem.segment.sid,
      'setTranslationPending',
    )
    setTranslationQueue[
      setTranslationQueue.findIndex(
        (current) => current.segment.sid === queueItem.segment.sid,
      )
    ] = queueItem
  }
  SegmentActions.setSegmentSaving(segment.sid, true)
  // If is offline and is in the tail I decrease the counter
  // else I execute the tail
  if (OfflineUtils.offline && config.offlineModeEnabled) {
    if (saveTranslation) {
      OfflineUtils.decrementOfflineCacheRemaining()
      OfflineUtils.failedConnection()
    }
    OfflineUtils.changeStatusOffline(segment.sid)
    OfflineUtils.checkConnection()
    if (callback) {
      callback.call(this)
    }
  } else {
    return execSetTranslationTail()
  }
}

export const execSetTranslationTail = () => {
  if (!setTranslationQueue.length) return
  const item = setTranslationQueue.shift()
  const {segment, status, propagate, callback} = item
  const idSegment = segment.sid
  SegmentStore.setLastTranslatedSegmentId(segment.sid)
  const translateRequest = SegmentUtils.createSetTranslationRequest(
    segment,
    status,
    propagate,
  )
  if (callback) {
    callback.call(this)
  }
  setTranslation(translateRequest)
    .then((data) => {
      SegmentActions.setChoosenSuggestion(idSegment, null)
      SegmentActions.setSegmentSaving(idSegment, false)
      setTranslation_success(data, item)
      //Review
      if (config.isReview) {
        SegmentActions.getSegmentVersionsIssues(idSegment)
        CatToolActions.reloadQualityReport()
      }
      CommonUtils.dispatchCustomEvent('setTranslation:success', {segment})
      if (config.alternativesEnabled) {
        SegmentActions.getTranslationMismatches(idSegment)
      }
      execSetTranslationTail()
    })
    .catch(({errors}) => {
      if (errors && errors.length) {
        CatToolActions.processErrors(errors, 'setTranslation')
      } else {
        //Add to setTranslation tail
        setTranslationQueue.push(item)
        OfflineUtils.changeStatusOffline(idSegment)
        OfflineUtils.startOfflineMode()
      }
      SegmentActions.setSegmentSaving(idSegment, true)
    })
}
const setTranslation_success = (response, item) => {
  const {segment, status} = item

  if (response.data === 'OK') {
    SegmentActions.setStatus(segment.sid, null, status)
    CatToolActions.setProgress(response)
    SegmentActions.removeClassToSegment(segment.sid, 'setTranslationPending')

    CatToolActions.checkWarnings(false)

    checkSegmentsPropagation(item, response.propagation)
  }
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
      let text =
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

      const notification = {
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
const translationIsToSave = (segment) => {
  // add to setTranslation tail
  const alreadySet = alreadyInSetTranslationTail(segment.sid)
  const emptyTranslation = segment && segment.translation.length === 0

  return !alreadySet && !emptyTranslation
}
const alreadyInSetTranslationTail = (sid) => {
  let alreadySet = false
  setTranslationQueue.forEach((item) => {
    if (item.id_segment === sid) alreadySet = true
  })
  return alreadySet
}
export const isTranslationTailEmpty = () => {
  return setTranslationQueue.length === 0
}
