import {isUndefined} from 'lodash'
import CommonUtils from './es6/utils/commonUtils'
import OfflineUtils from './es6/utils/offlineUtils'
import SegmentActions from './es6/actions/SegmentActions'
import SegmentStore from './es6/stores/SegmentStore'
import {getTagProjection} from './es6/api/getTagProjection'
import {setCurrentSegment} from './es6/api/setCurrentSegment'
import SegmentUtils from './es6/utils/segmentUtils'
import {SEGMENTS_STATUS} from './es6/constants/Constants'
;(function ($) {
  $.extend(window.UI, {
    /*++++++++++  Tag Proj start ++++++++++*/

    startSegmentTagProjection: function (sid) {
      UI.getSegmentTagsProjection(sid)
        .then(function (response) {
          // Set as Tagged and restore source with taggedText
          SegmentActions.setSegmentAsTagged(sid)
          // Unescape HTML
          let unescapedTranslation = response.data.translation

          // Update target area
          SegmentActions.copyTagProjectionInCurrentSegment(
            sid,
            unescapedTranslation,
          )
          // TODO: Autofill target based on Source Map, rewrite
          //SegmentActions.autoFillTagsInTarget(sid);
        })
        .catch((errors) => {
          if (errors && (errors.length > 0 || !isUndefined(errors.code))) {
            UI.processErrors(errors, 'getTagProjection')
            SegmentActions.disableTPOnSegment()
            // Set as Tagged and restore source with taggedText
            SegmentActions.setSegmentAsTagged(sid)
            // Add missing tag at the end of the string
            SegmentActions.autoFillTagsInTarget(sid)
          } else {
            SegmentActions.setSegmentAsTagged(sid)
            SegmentActions.autoFillTagsInTarget(sid)
            OfflineUtils.startOfflineMode()
          }
        })
        .finally(function () {
          UI.registerQACheck()
        })
    },
    /**
     * Tag Projection: get the tag projection for the current segment
     * @returns translation with the Tag prjection
     */
    getSegmentTagsProjection: function (sid) {
      const segmentObj = SegmentStore.getSegmentByIdToJS(sid)
      const source = segmentObj.segment
      //Retrieve the chosen suggestion if exist
      let suggestion
      let currentContribution = SegmentStore.getSegmentChoosenContribution(sid)
      // Send the suggestion to Tag Projection only if is > 89% and is not MT
      if (
        !isUndefined(currentContribution) &&
        currentContribution.match !== 'MT' &&
        parseInt(currentContribution.match) > 89
      ) {
        suggestion = currentContribution.translation
      }

      const target = segmentObj.translation
      return getTagProjection({
        action: 'getTagProjection',
        password: config.password,
        id_job: config.id_job,
        source: source,
        target: target,
        source_lang: config.source_rfc,
        target_lang: config.target_rfc,
        suggestion: suggestion,
        id_segment: sid,
      })
    },

    /*++++++++++  Tag Proj end ++++++++++*/

    /** TODO: Remove
     * evalNextSegment
     *
     * Evaluates the next segment and populates this.nextSegmentId ;
     *
     */
    evalNextSegment: function () {
      var currentSegment = SegmentStore.getCurrentSegment()
      var nextUntranslated = currentSegment
        ? SegmentStore.getNextSegment({
            current_sid: currentSegment.sid,
            status: SEGMENTS_STATUS.UNTRANSLATED,
          })
        : null

      if (nextUntranslated) {
        // se ci sono sotto segmenti caricati con lo status indicato
        this.nextUntranslatedSegmentId = nextUntranslated.sid
      } else {
        this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer
      }
      var next = currentSegment
        ? SegmentStore.getNextSegment({current_sid: currentSegment.sid})
        : null
      this.nextSegmentId = next ? next.sid : null
    },
    //Overridden by  plugin
    isReadonlySegment: function (segment) {
      const projectCompletionCheck =
        config.project_completion_feature_enabled &&
        !config.isReview &&
        config.job_completion_current_phase == 'revise'
      return (
        projectCompletionCheck ||
        segment.readonly == 'true' ||
        UI.body.hasClass('archived')
      )
    },
    setCurrentSegment: function () {
      var id_segment = this.currentSegmentId
      if (!id_segment) return
      CommonUtils.setLastSegmentFromLocalStorage(id_segment.toString())
      const requestData = {
        action: 'setCurrentSegment',
        password: config.password,
        revision_number: config.revisionNumber,
        id_segment: id_segment.toString(),
        id_job: config.id_job,
      }
      setCurrentSegment(requestData)
        .then((data) => {
          this.nextUntranslatedSegmentIdByServer = data.nextSegmentId
          SegmentActions.setNextUntranslatedSegmentFromServer(
            data.nextSegmentId,
          )

          var segment = SegmentStore.getSegmentByIdToJS(id_segment)
          if (!segment) return
          if (config.alternativesEnabled && !segment.alternatives) {
            this.getTranslationMismatches(id_segment)
          }
        })
        .catch(() => {
          OfflineUtils.failedConnection(requestData, 'setCurrentSegment')
        })
    },

    getSegmentById: function (id) {
      return $('#segment-' + id)
    },

    segmentIsLoaded: function (segmentId) {
      var segment = SegmentStore.getSegmentByIdToJS(segmentId)
      return segment || UI.getSegmentsSplit(segmentId).length > 0
    },
    getSegmentsSplit: function (id) {
      return SegmentStore.getSegmentsSplitGroup(id)
    },
    getContextBefore: function (segmentId) {
      var segmentBefore = SegmentStore.getPrevSegment(segmentId, true)
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
    getContextAfter: function (segmentId) {
      var segmentAfter = SegmentStore.getNextSegment({
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
    getIdBefore: function (segmentId) {
      var segmentBefore = SegmentStore.getPrevSegment(segmentId, true)
      // var segmentBefore = findSegmentBefore();
      if (!segmentBefore) {
        return null
      }
      return segmentBefore.original_sid
    },
    getIdAfter: function (segmentId) {
      var segmentAfter = SegmentStore.getNextSegment({
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
    translateAndGoToNext: function () {
      var segment = SegmentStore.getCurrentSegment()
      if (!segment || UI.isReadonlySegment(segment)) {
        return
      }
      if (config.isReview) {
        setTimeout(function () {
          SegmentActions.clickOnApprovedButton(segment, false)
        })
      } else {
        if (!segment.tagged) {
          setTimeout(function () {
            UI.startSegmentTagProjection(segment.sid)
          })
        } else if (segment.translation.trim() !== '') {
          setTimeout(function () {
            SegmentActions.clickOnTranslatedButton(segment, false)
          })
        }
      }
    },
  })
})(jQuery)
