// Core's default implementations for the segment-editor extension points
// declared in extensionManifest.js.
//
// These were methods on a mutable module-level object that also published
// itself as `window.globalFunctions`. They are plain functions of their
// arguments now: the recursive cases call the named function directly rather
// than dispatching through `this`, which is what made replacing one of these
// require replacing the object property.

import SegmentStore from '../stores/SegmentStore'
import SegmentUtils from '../utils/segmentUtils'
import SegmentActions from '../actions/SegmentActions'

export function getContextBefore(segmentId) {
  const segmentBefore = SegmentStore.getPrevSegment(segmentId, true)
  if (!segmentBefore) {
    return null
  }
  var segmentBeforeId = segmentBefore.splitted
  var isSplitted = segmentBefore.splitted
  if (isSplitted) {
    if (segmentBefore.original_sid !== segmentId.split('-')[0]) {
      return SegmentUtils.collectSplittedTranslations(
        segmentBefore.original_sid,
        '.source',
      )
    } else {
      return getContextBefore(segmentBeforeId)
    }
  } else {
    return segmentBefore.segment
  }
}

export function getContextAfter(segmentId) {
  const segmentAfter = SegmentStore.getNextSegment({
    current_sid: segmentId,
    alsoMutedSegment: true,
  })
  if (!segmentAfter) {
    return null
  }
  var segmentAfterId = segmentAfter.sid
  var isSplitted = segmentAfter.splitted
  if (isSplitted) {
    if (segmentAfter.firstOfSplit) {
      return SegmentUtils.collectSplittedTranslations(
        segmentAfter.original_sid,
        '.source',
      )
    } else {
      return getContextAfter(segmentAfterId)
    }
  } else {
    return segmentAfter.segment
  }
}

export function getIdBefore(segmentId) {
  const segmentBefore = SegmentStore.getPrevSegment(segmentId, true)
  if (!segmentBefore) {
    return null
  }
  return segmentBefore.original_sid
}

export function getIdAfter(segmentId) {
  const segmentAfter = SegmentStore.getNextSegment({
    current_sid: segmentId,
    alsoMutedSegment: true,
  })
  if (!segmentAfter) {
    return null
  }
  return segmentAfter.original_sid
}

/**
 * Register tabs in segment footer
 */
export function registerFooterTabs() {
  SegmentActions.registerTab('concordances', true, false)

  if (config.translation_matches_enabled) {
    SegmentActions.registerTab('matches', true, true)
  }

  SegmentActions.registerTab('glossary', true, false)
  SegmentActions.registerTab('alternatives', false, false)
}
