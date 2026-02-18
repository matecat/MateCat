import {isUndefined} from 'lodash'
import $ from 'jquery'

import SegmentUtils from '../../../utils/segmentUtils'
import CommonUtils from '../../../utils/commonUtils'
import OfflineUtils from '../../../utils/offlineUtils'
import Speech2Text from '../../../utils/speech2text'
import DraftMatecatUtils from './DraftMatecatUtils'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import {getContributions} from '../../../api/getContributions'
import {deleteContribution} from '../../../api/deleteContribution'
import {SEGMENTS_STATUS} from '../../../constants/Constants'
import CatToolActions from '../../../actions/CatToolActions'
import {laraAuth} from '../../../api/laraAuth'
import {laraTranslate} from '../../../api/laraTranslate'
import CatToolStore from '../../../stores/CatToolStore'
import {
  decodePlaceholdersToPlainText,
  encodePlaceholdersToTags,
} from './DraftMatecatUtils/tagUtils'

let TranslationMatches = {
  copySuggestionInEditarea: function (segment, index, translation) {
    if (!config.translation_matches_enabled) return
    let matchToUse = segment.contributions.matches[index - 1] ?? {}

    translation = translation ? translation : matchToUse.translation
    var percentageClass = this.getPercentageClass(matchToUse)
    if ($.trim(translation) !== '') {
      SegmentActions.replaceEditAreaTextContent(segment.sid, translation)
      SegmentActions.setHeaderPercentage(
        segment.sid,
        segment.id_file,
        matchToUse,
        percentageClass,
        matchToUse.created_by,
      )
      SegmentActions.startSegmentQACheck()
      CommonUtils.dispatchCustomEvent('contribution:copied', {
        translation: translation,
        segment: segment,
      })

      SegmentActions.modifiedTranslation(segment.sid, true)
    }
  },

  renderContributions: function (data, sid) {
    if (!data) return true
    var segmentObj = SegmentStore.getSegmentByIdToJS(sid)
    if (isUndefined(segmentObj)) return

    SegmentActions.setSegmentContributions(
      segmentObj.sid,
      data.matches,
      data.errors,
    )

    this.useSuggestionInEditArea(sid)

    SegmentActions.addClassToSegment(sid, 'loaded')
  },
  useSuggestionInEditArea: function (sid) {
    let segmentObj = SegmentStore.getSegmentByIdToJS(sid)
    let matches = segmentObj.contributions
      ? segmentObj.contributions.matches
      : []
    const match = matches.length ? matches[0].match : undefined
    if (
      matches &&
      matches.length > 0 &&
      isUndefined(matches[0].error) &&
      (parseInt(match) > 70 || match === 'MT' || match === 'ICE_MT')
    ) {
      var editareaLength = segmentObj.translation.length
      var translation = matches[0].translation

      if (editareaLength === 0) {
        SegmentActions.setChoosenSuggestion(segmentObj.sid, 1)

        /*If Tag Projection is enable and the current contribution is 100% match I leave the tags and replace
         * the source with the text with tags, the segment is tagged
         */
        var currentContribution = matches[0]
        translation = currentContribution.translation
        if (SegmentUtils.checkCurrentSegmentTPEnabled(segmentObj)) {
          if (parseInt(match) !== 100) {
            translation = DraftMatecatUtils.removeTagsFromText(translation)
          } else {
            SegmentActions.disableTPOnSegment(segmentObj)
          }
        }

        var copySuggestion = function () {
          TranslationMatches.copySuggestionInEditarea(
            segmentObj,
            1,
            translation,
          )
        }
        if (
          segmentObj.opened &&
          TranslationMatches.autoCopySuggestionEnabled() &&
          ((Speech2Text.enabled() &&
            Speech2Text.isContributionToBeAllowed(match)) ||
            !Speech2Text.enabled())
        ) {
          copySuggestion()
        }
      }
    }
  },
  segmentsWaitingForContributions: [],
  /**
   * Get contributions for the current segment and prefetch for the next ones
   * @param sid Segment ID
   * @param crossLanguageSettings Cross language settings
   * @param force Force to fetch new contributions
   * @param prefetch Number of segments to prefetch contributions for
   */
  getContributionsWithPrefetch: function ({
    sid,
    crossLanguageSettings,
    force = false,
    prefetch = 3,
  }) {
    const segment = SegmentStore.getSegmentByIdToJS(sid)
    if (!segment) return
    const nextSegment = SegmentStore.getNextSegment({
      current_sid: sid,
      lockedSegments: false,
    })
    let segmentsToFetch = [segment.sid]
    if (nextSegment) {
      segmentsToFetch = [...segmentsToFetch, nextSegment.sid]
    }
    let currentSid = nextSegment ? nextSegment.sid : sid
    for (let i = 1; i < prefetch; i++) {
      const followingSegment = SegmentStore.getNextSegment({
        status: SEGMENTS_STATUS.UNTRANSLATED,
        current_sid: currentSid,
      })
      if (followingSegment) {
        segmentsToFetch = [...segmentsToFetch, followingSegment.sid]
        currentSid = followingSegment.sid
      } else {
        // no untranslated segments after
        break
      }
    }
    if (segmentsToFetch.length < prefetch + 1) {
      // fill up with next segments
      currentSid = segmentsToFetch[segmentsToFetch.length - 1]
      while (segmentsToFetch.length < prefetch + 1) {
        const followingSegment = SegmentStore.getNextSegment({
          current_sid: currentSid,
        })
        if (followingSegment) {
          segmentsToFetch = [...segmentsToFetch, followingSegment.sid]
          currentSid = followingSegment.sid
        } else {
          // no more segments after
          break
        }
      }
    }
    segmentsToFetch.forEach((segmentSid, index) => {
      this.getContribution({
        sid: segmentSid,
        crossLanguageSettings,
        force,
        fastFetch: index === 0,
      })
    })
  },
  /**
   * Get contribution for a segment
   * @param sid Segment ID
   * @param crossLanguageSettings Cross language settings
   * @param force Force to fetch new contributions
   * @param fastFetch If true, skip advanced MT engines like Lara
   * @returns {Promise<Object | void>|Promise<void>}
   */
  getContribution: function ({
    sid,
    crossLanguageSettings,
    force,
    fastFetch = false,
  }) {
    const currentSegment = SegmentStore.getSegmentByIdToJS(sid)
    if (!currentSegment) return Promise.resolve()
    if (!config.translation_matches_enabled) {
      SegmentActions.addClassToSegment(currentSegment.sid, 'loaded')
      SegmentActions.getSegmentsQa(currentSegment)
      return Promise.resolve()
    }

    let callNewContributions = force
    //Check similar segments
    if (
      SegmentStore.lastTranslatedSegmentId &&
      SegmentStore.getSegmentByIdToJS(SegmentStore.lastTranslatedSegmentId)
    ) {
      /* If the segment just translated is equal or similar (Levenshtein distance) to the
       * current segment force to reload the matches
       **/
      const lastTranslatedSegment = SegmentStore.getSegmentByIdToJS(
        SegmentStore.lastTranslatedSegmentId,
      )
      const s1 = lastTranslatedSegment.segment
      const s2 = currentSegment.segment
      const areSimilar =
        (CommonUtils.levenshteinDistance(s1, s2) /
          Math.max(s1.length, s2.length)) *
          100 <
        50
      const isEqual = s1 === s2 && s1 !== ''

      callNewContributions = areSimilar || isEqual || force
    }
    //If the segment already has contributions and is not similar to the last translated
    if (
      currentSegment.contributions &&
      currentSegment.contributions.matches.length > 0 &&
      !callNewContributions
    ) {
      if (
        currentSegment.status === 'NEW' &&
        currentSegment.translation === '' &&
        currentSegment.opened
      ) {
        setTimeout(() => this.useSuggestionInEditArea(currentSegment.sid))
      }
      return Promise.resolve()
    }
    const id_segment_original = currentSegment.original_sid

    if (isUndefined(config.id_client)) {
      setTimeout(function () {
        TranslationMatches.getContribution({
          sid,
          crossLanguageSettings,
          force,
          fastFetch,
        })
      }, 3000)
      return Promise.resolve()
    }
    const {contextListBefore, contextListAfter} =
      SegmentUtils.getSegmentContext(id_segment_original)

    const isLaraEngine = config.active_engine?.engine_type === 'Lara'

    const getContributionRequest = (translation = null) => {
      if (!translation) {
        console.log(
          'Call classic matches for segment:',
          id_segment_original,
          this.segmentsWaitingForContributions,
        )
      }

      return getContributions({
        idSegment: id_segment_original,
        target: currentSegment.segment,
        translation: translation,
        crossLanguages: crossLanguageSettings
          ? [crossLanguageSettings.primary, crossLanguageSettings.secondary]
          : [],
        contextListBefore,
        contextListAfter,
      })
        .then(() => {
          // Remove from waiting list
          if (
            this.segmentsWaitingForContributions.indexOf(id_segment_original) >
            -1
          ) {
            this.segmentsWaitingForContributions.splice(
              this.segmentsWaitingForContributions.indexOf(id_segment_original),
              1,
            )
          }
        })
        .catch((errors) => {
          CatToolActions.processErrors(errors, 'getContribution')
          TranslationMatches.renderContributionErrors(
            errors,
            id_segment_original,
          )
        })
    }

    const jobLanguages = [config.source_code, config.target_code]
    let allowed =
      jobLanguages.filter((x) => ['en', 'it'].includes(x.split('-')[0]))
        .length === 2

    if (
      this.segmentsWaitingForContributions.indexOf(id_segment_original) > -1
    ) {
      return Promise.resolve()
    }
    if (isLaraEngine && allowed && !fastFetch && !callNewContributions) {
      this.segmentsWaitingForContributions.push(id_segment_original)
      laraAuth({idJob: config.id_job, password: config.password})
        .then((response) => {
          const jobMetadata = CatToolStore.getJobMetadata()
          const glossaries =
            jobMetadata?.project?.mt_extra?.lara_glossaries || []
          const decodedSource = decodePlaceholdersToPlainText(
            currentSegment.segment,
          )
          laraTranslate({
            token: response.token,
            source: decodedSource,
            contextListBefore: contextListBefore.map((t) =>
              decodePlaceholdersToPlainText(t),
            ),
            contextListAfter: contextListAfter.map((t) =>
              decodePlaceholdersToPlainText(t),
            ),
            sid: id_segment_original,
            jobId: config.id_job,
            glossaries,
          })
            .then((response) => {
              const translation =
                response.translation.find((item) => item.translatable)?.text ||
                ''
              return getContributionRequest(
                encodePlaceholdersToTags(translation),
              )
            })
            .catch((e) => {
              return getContributionRequest()
            })
        })
        .catch(() => {
          return getContributionRequest()
        })
    } else {
      this.segmentsWaitingForContributions.push(id_segment_original)
      return getContributionRequest()
    }
  },

  processContributions: function (data, sid) {
    if (config.translation_matches_enabled && data) {
      if (!data) return true
      const validMatches = data.matches.filter(
        ({segment, translation}) => segment && translation,
      )
      this.renderContributions({...data, matches: validMatches}, sid)
    }
  },

  autoCopySuggestionEnabled: function () {
    return !!config.translation_matches_enabled
  },

  renderContributionErrors: function (errors, segmentId) {
    SegmentActions.setSegmentContributions(segmentId, [], errors)
  },

  setDeleteSuggestion: function (source, target, id, sid) {
    return deleteContribution({
      source,
      target,
      id,
      sid,
    }).catch(() => {
      OfflineUtils.failedConnection()
    })
  },
  getPercentageClass: function (matchArray) {
    let percentageClass

    const match = this.getNumericMatchBaseOrMTString(matchArray.match)

    switch (true) {
      case match === 100 && !matchArray.ICE: //'100%'
        percentageClass = 'per-green'
        break
      case match === 100 && matchArray.ICE: //'101%'
        percentageClass = 'per-blue'
        break
      case match > 0 && match <= 99: //Ex: '75%-84%'
        percentageClass = 'per-orange'
        break
      case match === 'MT':
        percentageClass = 'per-yellow'
        break
      case match === 'ICE_MT':
        percentageClass = 'per-green'
        break
      default:
        percentageClass = ''
    }
    return percentageClass
  },

  /**
   * Get the match value from the match string or return the match value as is if it is not a number
   * Ex: '75%-84%', '101%', '100%', 'MT', 'ICE_MT' -> 75, 101, 100, NaN, NaN
   *
   * @param matchValue
   * @returns {number|string}
   */
  getNumericMatchBaseOrMTString: function (matchValue) {
    const m_parse = parseInt(matchValue) //Ex: '75%-84%', '101%', '100%', 'MT', 'ICE_MT' -> 75, 101, 100, NaN, NaN

    if (!isNaN(m_parse)) {
      matchValue = m_parse
    }

    return matchValue
  },

  /**
   * Get the percent string for value rewriting the ICE match as 101% and ICE_MT as TQMT
   * @param matchArray
   * @returns {string}
   */
  getPercentTextForMatch: function (matchArray) {
    if (
      TranslationMatches.getNumericMatchBaseOrMTString(matchArray.match) ===
        100 &&
      matchArray.ICE
    ) {
      return '101%'
    } else {
      return matchArray.match === 'ICE_MT' ? 'TQMT' : matchArray.match
    }
  },
}

export default TranslationMatches
