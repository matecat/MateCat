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

  /**
   * Characters counter local storage
   */
  isCharacterCounterEnable: () =>
    !!JSON.parse(window.localStorage.getItem('characterCounter'))?.find(
      (item) => Object.keys(item)[0] === config.id_job,
    ),
  setCharacterCounterOptionValue: (isActive) => {
    const MAX_ITEMS = 2000

    const cachedItems =
      JSON.parse(window.localStorage.getItem('characterCounter')) ?? []
    if (cachedItems.length > MAX_ITEMS) cachedItems.shift()
    const prevValue = cachedItems.filter(
      (item) => Object.keys(item)[0] !== config.id_job,
    )

    window.localStorage.setItem(
      'characterCounter',
      JSON.stringify([
        ...prevValue,
        ...(isActive ? [{[config.id_job]: true}] : []),
      ]),
    )
  },
  /**
   * Selected keys glossary job local storage
   */
  getSelectedKeysGlossary: (keys) => {
    const prevValue = JSON.parse(
      window.localStorage.getItem('selectedKeysGlossary'),
    )?.find((item) => Object.keys(item)[0] === config.id_job)?.[config.id_job]
    const result =
      prevValue?.flatMap((item) =>
        keys.find(({id}) => id === item)
          ? [keys.find(({id}) => id === item)]
          : [],
      ) ?? []
    SegmentUtils.setSelectedKeysGlossary(result)
    return result
  },
  setSelectedKeysGlossary: (keys) => {
    const MAX_ITEMS = 100

    const cachedItems =
      JSON.parse(window.localStorage.getItem('selectedKeysGlossary')) ?? []
    if (cachedItems.length > MAX_ITEMS) cachedItems.shift()
    const prevValue = cachedItems.filter(
      (item) => Object.keys(item)[0] !== config.id_job,
    )

    window.localStorage.setItem(
      'selectedKeysGlossary',
      JSON.stringify([...prevValue, {[config.id_job]: keys.map(({id}) => id)}]),
    )
  },
  segmentHasNote: (segment) => {
    return segment.notes ||
      segment.context_groups?.context_json ||
      segment.metadata?.lenght > 0
      ? true
      : false
  },
  /**
   * Retrieve the file id of a segment
   * NOTE: used by plugins
   * @param segment
   */
  getSegmentFileId: (segment) => {
    return segment.id_file
  },
}

export default SegmentUtils
