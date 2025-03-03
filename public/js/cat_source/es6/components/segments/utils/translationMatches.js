import {isUndefined} from 'lodash'

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

let TranslationMatches = {
  copySuggestionInEditarea: function (segment, index, translation) {
    if (!config.translation_matches_enabled) return
    let matchToUse = segment.contributions.matches[index - 1] ?? {}

    translation = translation ? translation : matchToUse.translation
    var percentageClass = this.getPercentuageClass(matchToUse.match)
    if ($.trim(translation) !== '') {
      SegmentActions.replaceEditAreaTextContent(segment.sid, translation)
      SegmentActions.setHeaderPercentage(
        segment.sid,
        segment.id_file,
        matchToUse.match,
        percentageClass,
        matchToUse.created_by,
      )
      UI.registerQACheck()
      $(document).trigger('contribution:copied', {
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
      (parseInt(match) > 70 || match === 'MT')
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
  getContribution: function (segmentSid, next, crossLanguageSettings, force) {
    const segment = SegmentStore.getSegmentByIdToJS(segmentSid)
    if (!config.translation_matches_enabled) {
      SegmentActions.addClassToSegment(segment.sid, 'loaded')
      SegmentActions.getSegmentsQa(segment)
      return Promise.resolve()
    }
    const currentSegment =
      next === 0
        ? segment
        : next == 1
          ? SegmentStore.getNextSegment({current_sid: segmentSid})
          : SegmentStore.getNextSegment({
              current_sid: segmentSid,
              status: SEGMENTS_STATUS.UNTRANSLATED,
            })

    if (!currentSegment) return
    //If segment locked or ICE
    if (SegmentUtils.isIceSegment(currentSegment) && !currentSegment.unlocked) {
      SegmentActions.addClassToSegment(currentSegment.sid, 'loaded')
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
    if (!currentSegment && next) {
      return Promise.resolve()
    }
    const id_segment_original = currentSegment.original_sid
    const nextSegment = SegmentStore.getNextSegment({
      current_sid: segmentSid,
    })
    // `next` and `untranslated next` are the same
    if (
      next === 2 &&
      currentSegment &&
      nextSegment &&
      id_segment_original === nextSegment.sid
    ) {
      return Promise.resolve()
    }

    if (isUndefined(config.id_client)) {
      setTimeout(function () {
        TranslationMatches.getContribution(segmentSid, next)
      }, 3000)
      // console.log('SSE: ID_CLIENT not found')
      return Promise.resolve()
    }
    const {contextListBefore, contextListAfter} =
      SegmentUtils.getSegmentContext(id_segment_original)
    return getContributions({
      idSegment: id_segment_original,
      target: currentSegment.segment,
      crossLanguages: crossLanguageSettings
        ? [crossLanguageSettings.primary, crossLanguageSettings.secondary]
        : [],
      contextListBefore,
      contextListAfter,
    }).catch((errors) => {
      UI.processErrors(errors, 'getContribution')
      TranslationMatches.renderContributionErrors(errors, id_segment_original)
    })
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
  getPercentuageClass: function (match) {
    var percentageClass = ''
    var m_parse = parseInt(match)

    if (!isNaN(m_parse)) {
      match = m_parse
    }

    switch (true) {
      case match == 100:
        percentageClass = 'per-green'
        break
      case match == 101:
        percentageClass = 'per-blue'
        break
      case match > 0 && match <= 99:
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
}

export default TranslationMatches
