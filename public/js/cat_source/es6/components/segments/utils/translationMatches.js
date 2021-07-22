import _ from 'lodash'
import TagUtils from '../../../utils/tagUtils'
import TextUtils from '../../../utils/textUtils'
import SegmentUtils from '../../../utils/segmentUtils'
import CommonUtils from '../../../utils/commonUtils'
import OfflineUtils from '../../../utils/offlineUtils'
import Speech2Text from '../../../utils/speech2text'
import DraftMatecatUtils from './DraftMatecatUtils'

let TranslationMatches = {
  copySuggestionInEditarea: function (segment, index, translation) {
    if (!config.translation_matches_enabled) return
    let matchToUse = segment.contributions.matches[index - 1]
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
    if (_.isUndefined(segmentObj)) return

    SegmentActions.setSegmentContributions(
      segmentObj.sid,
      segmentObj.id_file,
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
    if (matches && matches.length > 0 && _.isUndefined(matches[0].error)) {
      var editareaLength = segmentObj.translation.length
      var translation = matches[0].translation

      var match = matches[0].match

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
    if (!config.translation_matches_enabled) {
      SegmentActions.addClassToSegment(UI.getSegmentId(segment), 'loaded')
      this.segmentQA(segment)
      var deferred = new jQuery.Deferred()
      return deferred.resolve()
    }
    var txt
    var currentSegment =
      next === 0
        ? SegmentStore.getSegmentByIdToJS(segmentSid)
        : next == 1
        ? SegmentStore.getNextSegment(segmentSid)
        : SegmentStore.getNextSegment(segmentSid, null, 8)

    if (!currentSegment) return

    if (currentSegment.ice_locked === '1' && !currentSegment.unlocked) {
      SegmentActions.addClassToSegment(currentSegment.sid, 'loaded')
      var deferred = new jQuery.Deferred()
      return deferred.resolve()
      return
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

    txt = TagUtils.prepareTextToSend(currentSegment.segment)

    txt = TextUtils.view2rawxliff(txt)
    // Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;

    // `next` and `untranslated next` are the same
    if (next === 2 && UI.nextSegmentId === UI.nextUntranslatedSegmentId) {
      return $.Deferred().resolve()
    }

    var contextBefore = UI.getContextBefore(id)
    var idBefore = UI.getIdBefore(id)
    var contextAfter = UI.getContextAfter(id)
    var idAfter = UI.getIdAfter(id)

    if (_.isUndefined(config.id_client)) {
      setTimeout(function () {
        TranslationMatches.getContribution(segmentSid, next)
      }, 3000)
      console.log('SSE: ID_CLIENT not found')
      return $.Deferred().resolve()
    }

    //Cross language matches
    if (UI.crossLanguageSettings) {
      var crossLangsArray = [
        UI.crossLanguageSettings.primary,
        UI.crossLanguageSettings.secondary,
      ]
    }

    return APP.doRequest({
      data: {
        action: 'getContribution',
        password: config.password,
        is_concordance: 0,
        id_segment: id_segment_original,
        text: txt,
        id_job: config.id_job,
        num_results: UI.numContributionMatchesResults,
        id_translator: config.id_translator,
        context_before: contextBefore,
        id_before: idBefore,
        context_after: contextAfter,
        id_after: idAfter,
        id_client: config.id_client,
        cross_language: crossLangsArray,
        current_password: config.currentPassword,
      },
      context: $('#segment-' + id),
      error: function () {
        OfflineUtils.failedConnection(0, 'getContribution')
        TranslationMatches.showContributionError(this)
      },
      success: function (d) {
        if (d.errors.length) {
          UI.processErrors(d.errors, 'getContribution')
          TranslationMatches.renderContributionErrors(d.errors, this)
        }
      },
    })
  },

  processContributions: function (data, sid) {
    if (config.translation_matches_enabled && data) {
      if (!data) return true
      this.renderContributions(data, sid)
    }
  },

  showContributionError: function (segment) {
    SegmentActions.setSegmentContributions(
      UI.getSegmentId(segment),
      UI.getSegmentFileId(segment),
      [],
      [{}],
    )
  },

  autoCopySuggestionEnabled: function () {
    return !!config.translation_matches_enabled
  },

  renderContributionErrors: function (errors, segment) {
    SegmentActions.setSegmentContributions(
      UI.getSegmentId(segment),
      UI.getSegmentFileId(segment),
      [],
      errors,
    )
  },

  setDeleteSuggestion: function (source, target, id, sid) {
    return APP.doRequest({
      data: {
        action: 'deleteContribution',
        source_lang: config.source_rfc,
        target_lang: config.target_rfc,
        id_job: config.id_job,
        password: config.password,
        seg: source,
        tra: target,
        id_translator: config.id_translator,
        id_match: id,
        current_password: config.currentPassword,
      },
      error: function () {
        OfflineUtils.failedConnection(0, 'deleteContribution')
      },
      success: function (d) {
        TranslationMatches.setDeleteSuggestion_success(d)
      },
    })
  },
  setDeleteSuggestion_success: function (d, idMatch, sid) {
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
