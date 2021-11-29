import _ from 'lodash'

import CommonUtils from './commonUtils'
import TagUtils from './tagUtils'
import SegmentStore from '../stores/SegmentStore'

const SegmentUtils = {
  /**
   * Tag Projection: check if is possible to enable tag projection:
   * Condition: Languages it-IT en-GB en-US, not review
   */
  tpCanActivate: undefined,
  tagProjectionEnabled: undefined,
  TagProjectionCanActivate: undefined,
  checkTpCanActivate: function () {
    if (_.isUndefined(this.tpCanActivate)) {
      var acceptedLanguages = config.tag_projection_languages
      var elemST =
        config.source_rfc.split('-')[0] + '-' + config.target_rfc.split('-')[0]
      var elemTS =
        config.target_rfc.split('-')[0] + '-' + config.source_rfc.split('-')[0]
      var supportedPair =
        typeof acceptedLanguages[elemST] !== 'undefined' ||
        typeof acceptedLanguages[elemTS] !== 'undefined'
      this.tpCanActivate = supportedPair && !config.isReview
    }
    return this.tpCanActivate
  },
  /**
   * Tag Projection: check if is enable the Tag Projection
   */
  checkTPEnabled: function () {
    this.tagProjectionEnabled =
      this.checkTpCanActivate() && !!config.tag_projection_enabled
    return this.tagProjectionEnabled
  },
  /**
   * Check if the  the Tag Projection in the current segment is enabled and still not tagged
   * @returns {boolean}
   */
  checkCurrentSegmentTPEnabled: function (segmentObj) {
    var currentSegment = segmentObj
      ? segmentObj
      : SegmentStore.getCurrentSegment()
    if (currentSegment && this.checkTPEnabled()) {
      // If the segment has tag projection enabled (has tags and has the enableTP class)
      var segmentNoTags = TagUtils.removeAllTags(currentSegment.segment)
      var tagProjectionEnabled =
        TagUtils.hasDataOriginalTags(currentSegment.segment) &&
        !currentSegment.tagged &&
        segmentNoTags !== ''
      // If the segment has already be tagged
      var isCurrentAlreadyTagged = currentSegment.tagged
      return tagProjectionEnabled && !isCurrentAlreadyTagged
    }
    return false
  },
  //********** Tag Projection code end ******************/

  isIceSegment: function (segment) {
    return segment.ice_locked === '1'
  },
  isUnlockedSegment: function (segment) {
    return !_.isNull(CommonUtils.getFromStorage('unlocked-' + segment.sid))
  },
}

export default SegmentUtils
