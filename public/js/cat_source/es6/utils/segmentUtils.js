import {isNull} from 'lodash'

import CommonUtils from './commonUtils'
import SegmentStore from '../stores/SegmentStore'
import DraftMatecatUtils from '../components/segments/utils/DraftMatecatUtils'

const SegmentUtils = {
  /**
   * Tag Projection: check if is possible to enable tag projection:
   * Condition: Languages it-IT en-GB en-US, not review
   */
  tpCanActivate: undefined,
  TagProjectionCanActivate: undefined,
  /**
   * Tag Projection: check if is enable the Tag Projection
   */
  checkTPEnabled: function () {
    return !!config.tag_projection_enabled && !!!config.isReview
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
      var segmentNoTags = DraftMatecatUtils.removeTagsFromText(
        currentSegment.segment,
      )
      var tagProjectionEnabled =
        DraftMatecatUtils.hasDataOriginalTags(currentSegment.segment) &&
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
    return !isNull(CommonUtils.getFromStorage('unlocked-' + segment.sid))
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
   * AI assistant
   */
  isAiAssistantAuto: () =>
    JSON.parse(window.localStorage.getItem('aiAssistant')) == true,
  setAiAssistantOptionValue: (isActive) => {
    window.localStorage.setItem('aiAssistant', isActive)
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
      segment.metadata?.length > 0
      ? true
      : false
  },
  /**
   * Check Multi match languages
   */
  checkCrossLanguageSettings: function () {
    const settings = localStorage.getItem('multiMatchLangs')
    if (settings && Object.keys(JSON.parse(settings)).length)
      return JSON.parse(settings)
    return undefined
  },
  /**
   * Retrieve the file id of a segment
   * NOTE: used by plugins
   * @param segment
   */
  getSegmentFileId: (segment) => {
    return segment.id_file
  },
  collectSplittedStatuses: function (sid, splittedSid, status) {
    let statuses = []
    const segments = SegmentStore.getSegmentsInSplit(sid)
    segments.forEach((segment) => {
      if (splittedSid === segment.sid) {
        statuses.push(status)
      } else {
        statuses.push(segment.status)
      }
    })
    return statuses
  },
  createSetTranslationRequest: (segment, status, propagate = false) => {
    let {translation, segment: segmentSource, original_sid: sid} = segment
    const contextBefore = UI.getContextBefore(sid)
    const idBefore = UI.getIdBefore(sid)
    const contextAfter = UI.getContextAfter(sid)
    const idAfter = UI.getIdAfter(sid)
    if (segment.splitted) {
      translation = SegmentUtils.collectSplittedTranslations(sid)
      segmentSource = SegmentUtils.collectSplittedTranslations(sid, '.source')
    }
    if (
      !idBefore &&
      !idAfter &&
      config.project_plugins.indexOf('airbnb') === -1
    ) {
      try {
        const segments = SegmentStore._segments
        const segmentInStore = SegmentStore.getSegmentByIdToJS(sid)
        if (segments.size !== 1) {
          const trackingMessage = `Undefined idBefore and idAfter in setTranslation, Segments length: ${segments.size}, Segment exist ${segmentInStore ? 'true' : 'false'} Segment Id ${sid}`
          CommonUtils.dispatchTrackingError(trackingMessage)
        }
      } catch (e) {
        console.log(e)
      }
    }
    return {
      id_segment: segment.sid,
      id_job: config.id_job,
      password: config.password,
      status: status ? status : segment.status,
      translation: translation,
      segment: segmentSource,
      time_to_edit: UI.editTime ? UI.editTime : new Date() - UI.editStart,
      chosen_suggestion_index: segment.choosenSuggestionIndex,
      propagate: propagate,
      context_before: contextBefore,
      id_before: idBefore,
      context_after: contextAfter,
      id_after: idAfter,
      revision_number: config.revisionNumber,
      current_password: config.currentPassword,
      splitStatuses: segment.splitted
        ? SegmentUtils.collectSplittedStatuses(
            segment.original_sid,
            segment.sid,
            status,
          ).toString()
        : null,
      characters_counter: segment.charactersCounter,
      suggestion_array: segment.contributions
        ? JSON.stringify(segment.contributions.matches)
        : undefined,
    }
  },
  /**
   *
   * @param sid
   * @param selector
   * @returns {string}
   */
  collectSplittedTranslations: function (sid, selector) {
    let totalTranslation = ''
    const segments = SegmentStore.getSegmentsInSplit(sid)
    segments.forEach((segment, index) => {
      totalTranslation +=
        selector === '.source' ? segment.segment : segment.translation
      if (index < segments.length - 1)
        totalTranslation += UI.splittedTranslationPlaceholder
    })
    return totalTranslation
  },
}

export default SegmentUtils
