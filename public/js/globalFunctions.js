import SegmentStore from './stores/SegmentStore'
import SegmentUtils from './utils/segmentUtils'
import SegmentActions from './actions/SegmentActions'

const globalFunctions = {
  /***
   * Overridden by  plugin
   */
  getContextBefore: function (segmentId) {
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
        return this.getContextBefore(segmentBeforeId)
      }
    } else {
      return segmentBefore.segment
    }
  },
  /***
   * Overridden by  plugin
   */
  getContextAfter: function (segmentId) {
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
        return this.getContextAfter(segmentAfterId)
      }
    } else {
      return segmentAfter.segment
    }
  },
  /***
   * Overridden by  plugin
   */
  getIdBefore: function (segmentId) {
    const segmentBefore = SegmentStore.getPrevSegment(segmentId, true)
    // var segmentBefore = findSegmentBefore();
    if (!segmentBefore) {
      return null
    }
    return segmentBefore.original_sid
  },
  /***
   * Overridden by  plugin
   */
  getIdAfter: function (segmentId) {
    const segmentAfter = SegmentStore.getNextSegment({
      current_sid: segmentId,
      alsoMutedSegment: true,
    })
    if (!segmentAfter) {
      return null
    }
    return segmentAfter.original_sid
  },

  /**
   * Register tabs in segment footer
   *
   * Overridden by  plugin
   */
  registerFooterTabs: function () {
    SegmentActions.registerTab('concordances', true, false)

    if (config.translation_matches_enabled) {
      SegmentActions.registerTab('matches', true, true)
    }

    SegmentActions.registerTab('glossary', true, false)
    SegmentActions.registerTab('alternatives', false, false)
  },
}

window.globalFunctions = globalFunctions
export default globalFunctions
