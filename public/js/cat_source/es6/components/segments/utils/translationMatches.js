import _ from 'lodash'

import SegmentUtils from '../../../utils/segmentUtils'
import CommonUtils from '../../../utils/commonUtils'
import OfflineUtils from '../../../utils/offlineUtils'
import Speech2Text from '../../../utils/speech2text'
import DraftMatecatUtils from './DraftMatecatUtils'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import {getContributions} from '../../../api/getContributions'
import {deleteContribution} from '../../../api/deleteContribution'
import TagUtils from '../../../utils/tagUtils'

let TranslationMatches = {
  copySuggestionInEditarea: function (segment, index, translation) {
    if (!config.translation_matches_enabled) return
    let matchToUse = segment.contributions.matches[index - 1] ?? {}

    translation = translation ? translation : matchToUse.translation
    var percentageClass = this.getPercentuageClass(matchToUse.match)
    if ($.trim(translation) !== '') {
      SegmentActions.replaceEditAreaTextContent(
        segment.sid,
        TagUtils.transformTextFromBe(translation),
      )
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
    if (_.isUndefined(segmentObj)) return

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
      _.isUndefined(matches[0].error) &&
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
            translation = DraftMatecatUtils.cleanSegmentString(translation)
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
  getContribution: function (segmentSid, next, force) {
    const segment = SegmentStore.getSegmentByIdToJS(segmentSid)
    if (!config.translation_matches_enabled) {
      SegmentActions.addClassToSegment(segment.sid, 'loaded')
      SegmentActions.getSegmentsQa(segment)
      var deferred = new jQuery.Deferred()
      return deferred.resolve()
    }
    var currentSegment =
      next === 0
        ? segment
        : next == 1
        ? SegmentStore.getNextSegment(segmentSid)
        : SegmentStore.getNextSegment(segmentSid, null, 8)

    if (!currentSegment) return

    if (SegmentUtils.isIceSegment(currentSegment) && !currentSegment.unlocked) {
      SegmentActions.addClassToSegment(currentSegment.sid, 'loaded')
      const deferred = new jQuery.Deferred()
      return deferred.resolve()
    }

    /* If the segment just translated is equal or similar (Levenshtein distance) to the
     * current segment force to reload the matches
     **/
    var s1 = $('#segment-' + UI.lastTranslatedSegmentId + ' .source').text()
    var s2 = currentSegment.segment
    var areSimilar =
      (CommonUtils.levenshteinDistance(s1, s2) /
        Math.max(s1.length, s2.length)) *
        100 <
      50
    var isEqual = s1 == s2 && s1 !== ''

    var callNewContributions = areSimilar || isEqual || force

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
      return $.Deferred().resolve()
    }
    if (!currentSegment && next) {
      return $.Deferred().resolve()
    }
    var id = currentSegment.original_sid
    var id_segment_original = id.split('-')[0]

    // `next` and `untranslated next` are the same
    if (next === 2 && UI.nextSegmentId === UI.nextUntranslatedSegmentId) {
      return $.Deferred().resolve()
    }

    if (_.isUndefined(config.id_client)) {
      setTimeout(function () {
        TranslationMatches.getContribution(segmentSid, next)
      }, 3000)
      console.log('SSE: ID_CLIENT not found')
      return $.Deferred().resolve()
    }

    return getContributions({
      idSegment: id_segment_original,
      target: currentSegment.segment,
      crossLanguages: UI.crossLanguageSettings
        ? [UI.crossLanguageSettings.primary, UI.crossLanguageSettings.secondary]
        : [],
    }).catch((errors) => {
      UI.processErrors(errors, 'getContribution')
      TranslationMatches.renderContributionErrors(errors, $('#segment-' + id))
    })
  },

  processContributions: function (data, sid) {
    if (config.translation_matches_enabled && data) {
      if (!data) return true
      this.renderContributions(data, sid)
    }
  },

  autoCopySuggestionEnabled: function () {
    return !!config.translation_matches_enabled
  },

  renderContributionErrors: function (errors, segment) {
    SegmentActions.setSegmentContributions(UI.getSegmentId(segment), [], errors)
  },

  setDeleteSuggestion: function (source, target, id) {
    return deleteContribution({
      source,
      target,
      id,
    }).catch(() => {
      OfflineUtils.failedConnection(0, 'deleteContribution')
    })
  },
  setDeleteSuggestion_success: function (d) {
    if (d.errors.length) UI.processErrors(d.errors, 'setDeleteSuggestion')
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
      case match == 'MT':
        percentageClass = 'per-yellow'
        break
      default:
        percentageClass = ''
    }
    return percentageClass
  },
}

module.exports = TranslationMatches
