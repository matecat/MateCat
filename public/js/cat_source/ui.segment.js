import _ from 'lodash'
import CommonUtils from './es6/utils/commonUtils'
import OfflineUtils from './es6/utils/offlineUtils'
import TagUtils from './es6/utils/tagUtils'
import TextUtils from './es6/utils/textUtils'
import DraftMatecatUtils from './es6/components/segments/utils/DraftMatecatUtils'
import SegmentActions from './es6/actions/SegmentActions'
import SegmentStore from './es6/stores/SegmentStore'
import {toggleTagProjectionJob} from './es6/api/toggleTagProjectionJob'
import {getTagProjection} from './es6/api/getTagProjection'
import {setCurrentSegment} from './es6/api/setCurrentSegment'
;(function ($) {
  $.extend(window.UI, {
    /*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
         Tag Proj start
         */

    startSegmentTagProjection: function (sid) {
      UI.getSegmentTagsProjection(sid)
        .then(function (response) {
          // Set as Tagged and restore source with taggedText
          SegmentActions.setSegmentAsTagged(sid)
          // Unescape HTML
          let unescapedTranslation = TagUtils.transformTextFromBe(
            response.data.translation,
          )
          // Update target area
          SegmentActions.copyTagProjectionInCurrentSegment(
            sid,
            unescapedTranslation,
          )
          // TODO: Autofill target based on Source Map, rewrite
          //SegmentActions.autoFillTagsInTarget(sid);
        })
        .catch((errors) => {
          if (errors && (errors.length > 0 || !_.isUndefined(errors.code))) {
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
      var segmentObj = SegmentStore.getSegmentByIdToJS(sid)
      var source = segmentObj.segment
      source = TagUtils.prepareTextToSend(source)
      // source = TextUtils.htmlDecode(source);
      //Retrieve the chosen suggestion if exist
      var suggestion
      var currentContribution = SegmentStore.getSegmentChoosenContribution(sid)
      // Send the suggestion to Tag Projection only if is > 89% and is not MT
      if (
        !_.isUndefined(currentContribution) &&
        currentContribution.match !== 'MT' &&
        parseInt(currentContribution.match) > 89
      ) {
        suggestion = currentContribution.translation
      }

      var target = TagUtils.prepareTextToSend(segmentObj.translation)
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

    /**
     * Set the tag projection to true and reload file
     */
    enableTagProjectionInJob: function () {
      config.tag_projection_enabled = 1
      toggleTagProjectionJob({enabled: true}).then(() => {
        // UI.render({
        //     segmentToOpen: UI.getSegmentId(UI.currentSegment)
        // });
        // UI.checkWarnings(false);
        SegmentActions.changeTagProjectionStatus(true)
      })
    },
    /**
     * Set the tag projection to true and reload file
     */
    disableTagProjectionInJob: function () {
      config.tag_projection_enabled = 0
      toggleTagProjectionJob({enabled: false}).then(() => {
        // UI.render({
        //     segmentToOpen: UI.getSegmentId(UI.currentSegment)
        // });
        // UI.checkWarnings(false);
        SegmentActions.changeTagProjectionStatus(false)
      })
    },
    /*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
         Tag Proj end
         */

    /** TODO: Remove
     * evalNextSegment
     *
     * Evaluates the next segment and populates this.nextSegmentId ;
     *
     */
    evalNextSegment: function () {
      var currentSegment = SegmentStore.getCurrentSegment()
      var nextUntranslated = currentSegment
        ? SegmentStore.getNextSegment(currentSegment.sid, null, 8)
        : null

      if (nextUntranslated) {
        // se ci sono sotto segmenti caricati con lo status indicato
        this.nextUntranslatedSegmentId = nextUntranslated.sid
      } else {
        this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer
      }
      var next = currentSegment
        ? SegmentStore.getNextSegment(currentSegment.sid, null, null)
        : null
      this.nextSegmentId = next ? next.sid : null
    },
    //Override by  plugin
    gotoNextSegment: function () {
      SegmentActions.gotoNextSegment()
    },
    //Overridden by  plugin
    gotoPreviousSegment: function () {
      var prevSeg = SegmentStore.getPrevSegment()
      if (prevSeg) {
        SegmentActions.openSegment(prevSeg.sid)
      }
    },
    /**
     * Search for the next translated segment to propose for revision.
     * This function searches in the current UI first, then falls back
     * to invoke the server and eventually reload the page to the new
     * URL.
     *
     * Overridden by  plugin
     */
    openNextTranslated: function (sid) {
      sid = sid || UI.currentSegmentId
      var nextTranslatedSegment = SegmentStore.getNextSegment(
        sid,
        null,
        7,
        null,
        true,
      )
      var nextTranslatedSegmentInPrevious = SegmentStore.getNextSegment(
        -1,
        null,
        7,
        null,
        true,
      )
      // find in next segments
      if (nextTranslatedSegment) {
        SegmentActions.openSegment(nextTranslatedSegment.sid)
      } else {
        SegmentActions.openSegment(
          UI.nextUntranslatedSegmentIdByServer
            ? UI.nextUntranslatedSegmentIdByServer
            : nextTranslatedSegmentInPrevious.sid,
        )
      }
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
    //Overridden by  plugin
    getStatusForAutoSave: function (segment) {
      var status
      if (segment.hasClass('status-translated')) {
        status = 'translated'
      } else if (segment.hasClass('status-approved')) {
        status = 'approved'
      } else if (segment.hasClass('status-rejected')) {
        status = 'rejected'
      } else if (segment.hasClass('status-new')) {
        status = 'new'
      } else {
        status = 'draft'
      }

      if (status == 'new') {
        status = 'draft'
      }
      console.debug('status', status)
      return status
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
          UI.setCurrentSegment_success(id_segment, data)
        })
        .catch(() => {
          OfflineUtils.failedConnection(requestData, 'setCurrentSegment')
        })
    },
    setCurrentSegment_success: function (id_segment, d) {
      this.nextUntranslatedSegmentIdByServer = d.nextSegmentId
      SegmentActions.setNextUntranslatedSegmentFromServer(d.nextSegmentId)

      var segment = SegmentStore.getSegmentByIdToJS(id_segment)
      if (!segment) return
      if (config.alternativesEnabled && !segment.alternatives) {
        this.getTranslationMismatches(id_segment)
      }
      $('html').trigger('setCurrentSegment_success', [d, id_segment])
    },

    getSegmentById: function (id) {
      return $('#segment-' + id)
    },
    getEditAreaBySegmentId: function (id) {
      return $('#segment-' + id + ' .targetarea')
    },

    segmentIsLoaded: function (segmentId) {
      var segment = SegmentStore.getSegmentByIdToJS(segmentId)
      return segment || UI.getSegmentsSplit(segmentId).length > 0
    },
    getSegmentsSplit: function (id) {
      return SegmentStore.getSegmentsSplitGroup(id)
    },
    getContextBefore: function (segmentId) {
      var segmentBefore = SegmentStore.getPrevSegment(segmentId)
      if (!segmentBefore) {
        return null
      }
      var segmentBeforeId = segmentBefore.splitted
      var isSplitted = segmentBefore.splitted
      if (isSplitted) {
        if (segmentBefore.original_sid !== segmentId.split('-')[0]) {
          return this.collectSplittedTranslations(
            segmentBefore.original_sid,
            '.source',
          )
        } else {
          return this.getContextBefore(segmentBeforeId)
        }
      } else {
        return TagUtils.prepareTextToSend(segmentBefore.segment)
      }
    },
    getContextAfter: function (segmentId) {
      var segmentAfter = SegmentStore.getNextSegment(segmentId)
      if (!segmentAfter) {
        return null
      }
      var segmentAfterId = segmentAfter.sid
      var isSplitted = segmentAfter.splitted
      if (isSplitted) {
        if (segmentAfter.firstOfSplit) {
          return this.collectSplittedTranslations(
            segmentAfter.original_sid,
            '.source',
          )
        } else {
          return this.getContextAfter(segmentAfterId)
        }
      } else {
        return TagUtils.prepareTextToSend(segmentAfter.segment)
      }
    },
    getIdBefore: function (segmentId) {
      var segmentBefore = SegmentStore.getPrevSegment(segmentId)
      // var segmentBefore = findSegmentBefore();
      if (!segmentBefore) {
        return null
      }
      return segmentBefore.original_sid
    },
    getIdAfter: function (segmentId) {
      var segmentAfter = SegmentStore.getNextSegment(segmentId)
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
          UI.clickOnApprovedButton(segment, false)
        })
      } else {
        if (!segment.tagged) {
          setTimeout(function () {
            UI.startSegmentTagProjection(segment.sid)
          })
        } else if (segment.translation.trim() !== '') {
          setTimeout(function () {
            UI.clickOnTranslatedButton(segment, false)
          })
        }
      }
    },
  })
})(jQuery)
