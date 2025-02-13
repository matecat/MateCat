import CommonUtils from './commonUtils'
import SegmentStore from '../stores/SegmentStore'
import DraftMatecatUtils from '../components/segments/utils/DraftMatecatUtils'
import {SEGMENTS_STATUS} from '../constants/Constants'
import UserStore from '../stores/UserStore'

const SegmentUtils = {
  /**
   * Tag Projection: check if is possible to enable tag projection:
   * Condition: Languages it-IT en-GB en-US, not review
   */
  tpCanActivate: undefined,
  TagProjectionCanActivate: undefined,
  localStorageUnlockedSegments: 'unlocked-segments-' + config.id_job,

  checkTPSupportedLanguage: function () {
    const languagesKey = `${config.source_code.split('-')[0]}-${config.target_code.split('-')[0]}`
    const languagesKeyRev = `${config.target_code.split('-')[0]}-${config.source_code.split('-')[0]}`
    return Object.keys(config.tag_projection_languages).some(
      (key) => key === languagesKey || key === languagesKeyRev,
    )
  },
  /**
   * Tag Projection: check if is enable the Tag Projection
   */
  checkTPEnabled: function () {
    return (
      this.checkTPSupportedLanguage() &&
      UserStore.getUserMetadata()?.guess_tags === 1 &&
      !!!config.isReview
    )
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
  isSecondPassLockedSegment: function (segment) {
    return (
      segment.status?.toUpperCase() === SEGMENTS_STATUS.APPROVED2 &&
      segment.revision_number === 2 &&
      config.revisionNumber !== 2
    )
  },
  isUnlockedSegment: function (segment) {
    // return !isNull(CommonUtils.getFromStorage('unlocked-' + segment.sid))
    if (localStorage.getItem(this.localStorageUnlockedAllSegments)) return true
    let segmentsUnlocked = localStorage.getItem(
      this.localStorageUnlockedSegments,
    )
    let index = -1
    if (segmentsUnlocked) {
      segmentsUnlocked = segmentsUnlocked.split(',')
      index = segmentsUnlocked.indexOf(segment.sid)
    }
    return index !== -1
  },
  addUnlockedSegment: function (sid) {
    let segmentsUnlocked = localStorage.getItem(
      this.localStorageUnlockedSegments,
    )
    if (segmentsUnlocked) {
      segmentsUnlocked = segmentsUnlocked.split(',')
      if (segmentsUnlocked.indexOf(sid) === -1) segmentsUnlocked.push(sid)
    } else {
      segmentsUnlocked = [sid]
    }
    localStorage.setItem(
      this.localStorageUnlockedSegments,
      segmentsUnlocked.join(),
    )
  },
  removeUnlockedSegment(sid) {
    let segmentsUnlocked = localStorage.getItem(
      this.localStorageUnlockedSegments,
    )
    if (segmentsUnlocked) {
      segmentsUnlocked = segmentsUnlocked.split(',')
      const index = segmentsUnlocked.indexOf(sid)
      if (index > -1) {
        segmentsUnlocked.splice(index, 1)
        localStorage.setItem(
          this.localStorageUnlockedSegments,
          segmentsUnlocked.join(),
        )
      }
    }
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
    return !!(
      segment.notes ||
      segment.context_groups?.context_json ||
      segment.metadata?.length > 0
    )
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
  getSegmentContext: (sid) => {
    const segments = SegmentStore.getAllSegments()
    const segmentIndex = SegmentStore.getSegmentIndex(sid)
    if (segmentIndex === -1) {
      throw new Error('Segment not found.')
    }

    const beforeStartIndex = Math.max(0, segmentIndex - 5)
    const beforeElements = segments.slice(beforeStartIndex, segmentIndex)

    const afterEndIndex = Math.min(segments.length, segmentIndex + 3)
    const afterElements = segments.slice(segmentIndex + 1, afterEndIndex)

    return {
      contextListBefore: beforeElements.map((segment) => segment.segment),
      contextListAfter: afterElements.map((segment) => segment.segment),
    }
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
      chosen_suggestion_index: !config.isReview
        ? segment.choosenSuggestionIndex
        : undefined,
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
      suggestion_array:
        segment.contributions && !config.isReview
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
  isReadonlySegment: function (segment) {
    const projectCompletionCheck =
      config.project_completion_feature_enabled &&
      !config.isReview &&
      config.job_completion_current_phase === 'revise'
    return projectCompletionCheck || segment.readonly === 'true'
  },
  getRelativeTransUnitCharactersCounter: ({sid, charactersCounter = 0}) => {
    const segments = SegmentStore.getAllSegments()
    const segmentInStore = SegmentStore.getSegmentByIdToJS(sid)

    const {internal_id: internalId} = segmentInStore

    const segmentsGroupInternalId = segments.filter(
      (segment) => segment.internal_id === internalId,
    )

    const unitCharacters = segmentsGroupInternalId
      .filter((segment) => segment.sid !== sid)
      .reduce((acc, cur) => {
        const cleanTagsTranslation =
          DraftMatecatUtils.decodePlaceholdersToPlainText(
            DraftMatecatUtils.removeTagsFromText(cur.translation),
          )
        return (
          acc + DraftMatecatUtils.getCharactersCounter(cleanTagsTranslation)
        )
      }, charactersCounter)

    return {segmentCharacters: charactersCounter, unitCharacters}
  },
}

export default SegmentUtils
