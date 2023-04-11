import Cookies from 'js-cookie'
import _ from 'lodash'

import AppDispatcher from '../stores/AppDispatcher'
import SegmentConstants from '../constants/SegmentConstants'
import EditAreaConstants from '../constants/EditAreaConstants'
import CatToolConstants from '../constants/CatToolConstants'
import SegmentStore from '../stores/SegmentStore'
import TranslationMatches from '../components/segments/utils/translationMatches'
import TagUtils from '../utils/tagUtils'
import OfflineUtils from '../utils/offlineUtils'
import CommonUtils from '../utils/commonUtils'
import SegmentUtils from '../utils/segmentUtils'
import CopySourceModal from '../components/modals/CopySourceModal'
import {unescapeHTMLLeaveTags} from '../components/segments/utils/DraftMatecatUtils/textUtils'
import CatToolActions from './CatToolActions'
import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import {getGlossaryForSegment} from '../api/getGlossaryForSegment'
import {getGlossaryMatch} from '../api/getGlossaryMatch'
import {deleteGlossaryItem} from '../api/deleteGlossaryItem'
import {addGlossaryItem} from '../api/addGlossaryItem'
import {updateGlossaryItem} from '../api/updateGlossaryItem'
import {approveSegments} from '../api/approveSegments'
import {translateSegments} from '../api/translateSegments'
import {splitSegment} from '../api/splitSegment'
import {copyAllSourceToTarget} from '../api/copyAllSourceToTarget'
import AlertModal from '../components/modals/AlertModal'
import ModalsActions from './ModalsActions'
import {getLocalWarnings} from '../api/getLocalWarnings'
import {getGlossaryCheck} from '../api/getGlossaryCheck'
import SearchUtils from '../components/header/cattol/search/searchUtils'
import CatToolStore from '../stores/CatToolStore'

const SegmentActions = {
  /********* SEGMENTS *********/
  renderSegments: function (segments, idToOpen) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.RENDER_SEGMENTS,
      segments: segments,
      idToOpen: idToOpen,
    })
  },
  splitSegments: function (oldSid, newSegments, splitGroup, fid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SPLIT_SEGMENT,
      oldSid: oldSid,
      newSegments: newSegments,
      splitGroup: splitGroup,
      fid: fid,
    })
  },
  splitSegment: function (sid, text) {
    SegmentActions.freezingSegments(true)
    splitSegment(sid, text)
      .then(() => {
        SegmentActions.removeAllSegments()
        CatToolActions.onRender({segmentToOpen: sid.split('-')[0]})
      })
      .catch((errors) => {
        var notification = {
          title: 'Error',
          text: errors[0].message,
          type: 'error',
        }
        CatToolActions.addNotification(notification)
        SegmentActions.freezingSegments(false)
      })
  },
  addSegments: function (segments, where) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_SEGMENTS,
      segments: segments,
      where: where,
    })
  },

  updateAllSegments: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.UPDATE_ALL_SEGMENTS,
    })
  },

  addSearchResultToSegments: function (
    occurrencesList,
    searchResultsDictionary,
    currentIndex,
    text,
  ) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_SEARCH_RESULTS,
      occurrencesList,
      searchResultsDictionary,
      currentIndex,
      text,
    })
  },
  changeCurrentSearchSegment: function (currentIndex) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_CURRENT_SEARCH,
      currentIndex,
    })
  },
  removeSearchResultToSegments: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.REMOVE_SEARCH_RESULTS,
    })
  },
  replaceCurrentSearch: function (text) {
    AppDispatcher.dispatch({
      actionType: EditAreaConstants.REPLACE_SEARCH_RESULTS,
      text: text,
    })
  },

  /********** Segment **********/
  setOpenSegment: function (sid, fid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_OPEN_SEGMENT,
      sid: sid,
      fid: fid,
    })
  },

  openSegment: function (sid, wasOriginatedFromBrowserHistory = false) {
    const segment = SegmentStore.getSegmentByIdToJS(sid)

    if (segment) {
      //Check first if the segment is in the view
      if (UI.isReadonlySegment(segment) && !SearchUtils.searchEnabled) {
        UI.readonlyClickDisplay()
        return
      }
      let $segment =
        segment.splitted && sid.indexOf('-') === -1
          ? UI.getSegmentById(sid + '-1')
          : UI.getSegmentById(sid)
      if ($segment.length === 0) {
        this.scrollToSegment(sid, this.openSegment)
        return
      }
      AppDispatcher.dispatch({
        actionType: SegmentConstants.OPEN_SEGMENT,
        sid: sid,
        wasOriginatedFromBrowserHistory,
      })
    } else {
      SegmentActions.removeAllSegments()
      CatToolActions.onRender({
        firstLoad: false,
        segmentToOpen: sid,
      })
    }
  },
  closeSegment: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CLOSE_SEGMENT,
    })
    this.closeIssuesPanel()
  },
  saveSegmentBeforeClose: function (segment) {
    if (UI.translationIsToSaveBeforeClose(segment)) {
      return UI.setTranslation({
        id_segment: segment.sid,
        status:
          segment.status.toLowerCase() === 'new' ? 'draft' : segment.status,
      })
    } else {
      var deferred = $.Deferred()
      deferred.resolve()
      return deferred.promise()
    }
  },
  scrollToCurrentSegment() {
    if (SegmentStore.getCurrentSegment())
      this.scrollToSegment(SegmentStore.getCurrentSegment().sid)
  },
  scrollToSegment: function (sid, callback) {
    const segment = SegmentStore.getSegmentByIdToJS(sid)
    if (segment) {
      AppDispatcher.dispatch({
        actionType: SegmentConstants.SCROLL_TO_SEGMENT,
        sid: sid,
      })
      if (callback) {
        setTimeout(() => callback.apply(this, [sid]))
      }
    } else {
      SegmentActions.removeAllSegments()
      CatToolActions.onRender({
        firstLoad: false,
        segmentToOpen: sid,
        callbackAfterSegmentsResponse: () =>
          callback && setTimeout(() => callback.apply(this, [sid]), 1000),
      })
    }
  },
  addClassToSegment: function (sid, newClass) {
    setTimeout(function () {
      AppDispatcher.dispatch({
        actionType: SegmentConstants.ADD_SEGMENT_CLASS,
        id: sid,
        newClass: newClass,
      })
    }, 0)
  },

  removeClassToSegment: function (sid, className) {
    setTimeout(function () {
      AppDispatcher.dispatch({
        actionType: SegmentConstants.REMOVE_SEGMENT_CLASS,
        id: sid,
        className: className,
      })
    }, 0)
  },

  setStatus: function (sid, fid, status) {
    if (sid) {
      AppDispatcher.dispatch({
        actionType: SegmentConstants.SET_SEGMENT_STATUS,
        id: sid,
        fid: fid,
        status: status,
      })
    }
  },

  setHeaderPercentage: function (sid, fid, perc, className, createdBy) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_SEGMENT_HEADER,
      id: sid,
      fid: fid,
      perc: perc,
      className: className,
      createdBy: createdBy,
    })
  },

  hideSegmentHeader: function (sid, fid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.HIDE_SEGMENT_HEADER,
      id: sid,
      fid: fid,
    })
  },

  propagateTranslation: function (segmentId, propagatedSegments, status) {
    const segment = SegmentStore.getSegmentByIdToJS(segmentId)
    if (!segment) return
    if (segment.splitted > 2) return false

    for (var i = 0, len = propagatedSegments.length; i < len; i++) {
      const sid = propagatedSegments[i]
      const segToModify = SegmentStore.getSegmentByIdToJS(sid)
      if (
        segToModify &&
        sid !== segmentId &&
        segment &&
        !segToModify.splitted
      ) {
        SegmentActions.updateOriginalTranslation(sid, segment.translation)
        SegmentActions.replaceEditAreaTextContent(sid, segment.translation)
        //Tag Projection: disable it if enable
        SegmentActions.setSegmentAsTagged(sid)
        SegmentActions.setStatus(sid, null, status) // now the status, too, is propagated
        SegmentActions.setSegmentPropagation(sid, null, true, segment.sid)
        SegmentActions.modifiedTranslation(sid, false)
      }
      SegmentActions.setAlternatives(sid, undefined)
    }

    SegmentActions.setSegmentPropagation(segmentId, null, false)
    SegmentActions.setAlternatives(segmentId, undefined)
  },

  setSegmentPropagation: function (sid, fid, propagation, from) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_SEGMENT_PROPAGATION,
      id: sid,
      fid: fid,
      propagation: propagation,
      from: from,
    })
  },
  changeTagProjectionStatus: function (enabled) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_GUESS_TAGS,
      enabled: enabled,
    })
  },
  /**
   * Disable the Tag Projection, for example after clicking on the Translation Matches
   */
  disableTPOnSegment: function (segmentObj) {
    var currentSegment = segmentObj
      ? segmentObj
      : SegmentStore.getCurrentSegment()

    if (!currentSegment) return

    var tagProjectionEnabled =
      TagUtils.hasDataOriginalTags(currentSegment.segment) &&
      !currentSegment.tagged
    if (SegmentUtils.checkTPEnabled() && tagProjectionEnabled) {
      SegmentActions.setSegmentAsTagged(
        currentSegment.sid,
        currentSegment.id_file,
      )
      UI.getSegmentById(currentSegment.sid).data('tagprojection', 'tagged')
    }
  },
  setSegmentAsTagged: function (sid, fid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_SEGMENT_TAGGED,
      id: sid,
      fid: fid,
    })
  },
  disableTagLock: function () {
    UI.tagLockEnabled = false
  },
  enableTagLock: function () {
    UI.tagLockEnabled = true
  },

  setSegmentWarnings: function (sid, warnings, tagMismatch) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_SEGMENT_WARNINGS,
      sid: sid,
      warnings: warnings,
      tagMismatch: tagMismatch,
    })
  },

  updateGlobalWarnings: function (warnings) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      warnings: warnings,
    })
  },

  qaComponentsetLxqIssues: function (issues) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.QA_LEXIQA_ISSUES,
      warnings: issues,
    })
  },
  setChoosenSuggestion: function (sid, index) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_CHOOSEN_SUGGESTION,
      sid: sid,
      index: index,
    })
  },
  addQaCheck: function (sid, data) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_QA_CHECK,
      sid,
      data,
    })
  },
  addLexiqaHighlight: function (sid, matches, type) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_LXQ_HIGHLIGHT,
      sid: sid,
      matches: matches,
      type: type,
    })
  },
  selectNextSegmentDebounced: _.debounce(() => {
    SegmentActions.selectNextSegment()
  }, 100),

  selectNextSegment: function (sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SELECT_SEGMENT,
      sid: sid,
      direction: 'next',
    })
  },
  selectPrevSegmentDebounced: _.debounce(() => {
    SegmentActions.selectPrevSegment()
  }, 100),
  selectPrevSegment: function (sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SELECT_SEGMENT,
      sid: sid,
      direction: 'prev',
    })
  },
  openSelectedSegment: function () {
    let sid = SegmentStore.getSelectedSegmentId()
    if (sid) {
      this.openSegment(sid)
    }
  },
  copySourceToTarget: function () {
    let currentSegment = SegmentStore.getCurrentSegment()
    SegmentActions.disableTPOnSegment(currentSegment)
    if (currentSegment) {
      let source = currentSegment.segment
      let sid = currentSegment.sid
      // Escape html
      source = unescapeHTMLLeaveTags(source)
      SegmentActions.replaceEditAreaTextContent(sid, source)
      SegmentActions.modifiedTranslation(sid, true)
      SegmentActions.getSegmentsQa(currentSegment)

      if (config.translation_matches_enabled) {
        SegmentActions.setChoosenSuggestion(sid, null)
      }

      if (!config.isReview) {
        var alreadyCopied = false
        $.each(SegmentStore.consecutiveCopySourceNum, function () {
          if (this === sid) alreadyCopied = true
        })
        if (!alreadyCopied) {
          SegmentStore.consecutiveCopySourceNum.push(this.currentSegmentId)
        }
        if (SegmentStore.consecutiveCopySourceNum.length > 2) {
          this.copyAllSources()
        }
      }
    }
  },
  copyAllSources: function () {
    if (
      typeof Cookies.get(
        'source_copied_to_target-' + config.id_job + '-' + config.password,
      ) == 'undefined'
    ) {
      var props = {
        confirmCopyAllSources: SegmentActions.continueCopyAllSources.bind(this),
        abortCopyAllSources: SegmentActions.abortCopyAllSources.bind(this),
      }

      ModalsActions.showModalComponent(
        CopySourceModal,
        props,
        'Copy source to ALL segments',
      )
    } else {
      SegmentStore.consecutiveCopySourceNum = []
    }
  },
  continueCopyAllSources: function () {
    SegmentStore.consecutiveCopySourceNum = []

    SegmentActions.removeAllSegments() //TODO
    $('#outer').addClass('loading')

    copyAllSourceToTarget()
      .then(() => {
        CatToolActions.onRender({
          segmentToOpen: UI.currentSegmentId,
        })
      })
      .catch((errors) => {
        const notification = {
          title: 'Error',
          text: 'Error copying all sources to target. Try again!',
          type: 'error',
          position: 'bl',
          ...(errors[0]?.message && {
            title: 'Error',
            text: errors[0].message,
            type: 'error',
            position: 'bl',
          }),
        }
        CatToolActions.addNotification(notification)
      })
  },
  abortCopyAllSources: function () {
    SegmentStore.consecutiveCopySourceNum = []
  },
  /******************* EditArea ************/
  modifiedTranslation: function (sid, status) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.MODIFIED_TRANSLATION,
      sid: sid,
      status: status,
    })
  },
  replaceEditAreaTextContent: function (sid, text) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.REPLACE_TRANSLATION,
      id: sid,
      translation: text,
    })
  },

  updateTranslation: function (
    sid,
    translation,
    decodedTranslation,
    tagMap,
    missingTagsInTarget,
    lxqDecodedTranslation,
  ) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.UPDATE_TRANSLATION,
      id: sid,
      translation: translation,
      decodedTranslation,
      tagMap,
      missingTagsInTarget,
      lxqDecodedTranslation,
    })
  },
  /**
   * Set the original translation of a segment.
   * Used to create the revision trackChanges
   * @param sid
   * @param fid
   * @param originalTranslation
   */
  updateOriginalTranslation: function (sid, originalTranslation) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION,
      id: sid,
      originalTranslation: originalTranslation,
    })
  },
  updateSource: function (
    sid,
    source,
    decodedSource,
    tagMap,
    lxqDecodedSource,
  ) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.UPDATE_SOURCE,
      id: sid,
      source: source,
      decodedSource,
      tagMap,
      lxqDecodedSource,
    })
  },
  lockEditArea: function (sid, fid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.LOCK_EDIT_AREA,
      fid: fid,
      id: sid,
    })
  },
  undoInSegment: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.UNDO_TEXT,
    })
  },
  redoInSegment: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.REDO_TEXT,
    })
  },
  setFocusOnEditArea: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.FOCUS_EDITAREA,
    })
  },
  autoFillTagsInTarget: function (sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.FILL_TAGS_IN_TARGET,
      sid: sid,
    })
  },
  copyTagProjectionInCurrentSegment(sid, translation) {
    if (!_.isUndefined(translation) && translation.length > 0) {
      SegmentActions.replaceEditAreaTextContent(sid, translation)
    }
  },
  setSegmentSaving(sid, saving) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_SEGMENT_SAVING,
      sid,
      saving,
    })
  },
  /************ SPLIT ****************/
  openSplitSegment: function (sid) {
    if (OfflineUtils.offline) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Split is disabled in Offline Mode',
        },
        'Split disabled',
      )
      return
    }
    AppDispatcher.dispatch({
      actionType: SegmentConstants.OPEN_SPLIT_SEGMENT,
      sid: sid,
    })
  },
  closeSplitSegment: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CLOSE_SPLIT_SEGMENT,
    })
  },
  /************ FOOTER ***************/
  registerTab: function (tab, visible, open) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.REGISTER_TAB,
      tab: tab,
      visible: visible,
      open: open,
    })
  },
  setSegmentContributions: function (sid, contributions, errors) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_CONTRIBUTIONS,
      sid: sid,
      matches: contributions,
      errors: errors,
    })
  },
  setSegmentCrossLanguageContributions: function (
    sid,
    fid,
    contributions,
    errors,
  ) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_CL_CONTRIBUTIONS,
      sid: sid,
      fid: fid,
      matches: contributions,
      errors: errors,
    })
  },
  setAlternatives: function (sid, alternatives) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_ALTERNATIVES,
      sid: sid,
      alternatives: alternatives,
    })
  },
  chooseContributionOnCurrentSegment: function (index) {
    const segment = SegmentStore.getCurrentSegment()
    if (segment.contributions) {
      AppDispatcher.dispatch({
        actionType: SegmentConstants.CHOOSE_CONTRIBUTION,
        sid: segment.sid,
        index: index,
      })
    }
  },
  deleteContribution: function (source, target, matchId, sid) {
    TranslationMatches.setDeleteSuggestion(source, target, matchId, sid).then(
      () => {
        AppDispatcher.dispatch({
          actionType: SegmentConstants.DELETE_CONTRIBUTION,
          sid: sid,
          matchId: matchId,
        })
      },
    )
  },
  activateTab: function (sid, tab) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.OPEN_TAB,
      sid: sid,
      data: tab,
    })
  },
  closeTabs: function (sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CLOSE_TABS,
      sid: sid,
      data: null,
    })
  },

  setTabOpen: function (sid, tabName) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_DEFAULT_TAB,
      tabName: tabName,
    })
  },
  getGlossaryForSegment: function ({sid, fid, text, shouldRefresh = false}) {
    if (!CatToolStore.haveKeysGlossary) return
    // refresh segment glossary already included
    if (shouldRefresh) {
      getGlossaryForSegment({
        idSegment: sid,
        source: text,
      }).catch(() => {
        OfflineUtils.failedConnection(sid, 'getGlossaryForSegment')
      })
      return
    }

    let requestes = [
      {
        sid: sid,
        fid: fid,
        text: text,
      },
    ]
    let nextSegment = SegmentStore.getNextSegment(sid, fid)
    if (nextSegment) {
      requestes.push({
        sid: nextSegment.sid,
        fid: nextSegment.fid,
        text: nextSegment.segment,
      })
      let nextSegmentUntranslated = SegmentStore.getNextSegment(sid, fid, 8)
      if (
        nextSegmentUntranslated &&
        requestes[1].sid != nextSegmentUntranslated.sid
      ) {
        requestes.push({
          sid: nextSegmentUntranslated.sid,
          fid: nextSegmentUntranslated.fid,
          text: nextSegmentUntranslated.segment,
        })
      }
    }

    for (let index = 0; index < requestes.length; index++) {
      let request = requestes[index]
      let segment = SegmentStore.getSegmentByIdToJS(request.sid, request.fid)
      if (
        segment &&
        (typeof segment.glossary === 'undefined' || sid === request.sid)
      ) {
        //Response inside SSE Channel
        getGlossaryForSegment({
          idSegment: request.sid,
          source: request.text,
        }).catch(() => {
          OfflineUtils.failedConnection(request.sid, 'getGlossaryForSegment')
        })
      }
    }
  },

  searchGlossary: function ({
    idSegment,
    sentence,
    sourceLanguage,
    targetLanguage,
    isSearchingInTarget,
  }) {
    SegmentStore.isSearchingGlossaryInTarget = isSearchingInTarget
    getGlossaryMatch({
      idSegment,
      sentence,
      sourceLanguage,
      targetLanguage,
    }).catch(() => {
      OfflineUtils.failedConnection(0, 'glossary')
      SegmentStore.isSearchingGlossaryInTarget = false
    })
  },

  setGlossaryForSegment: (sid, terms) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
      sid: sid,
      glossary: terms,
    })
  },

  setGlossaryForSegmentBySearch: (sid, terms) => {
    SegmentStore.isSearchingGlossaryInTarget = false
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE_BY_SEARCH,
      sid: sid,
      glossary: terms,
    })
  },

  deleteGlossaryItem: function (data) {
    deleteGlossaryItem(data)
      .then(() => {})
      .catch(() => {
        OfflineUtils.failedConnection(0, 'deleteGlossaryItem')
      })
  },

  deleteGlossaryFromCache: (sid, term) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
      sid: sid,
      message: 'A glossary item has been deleted',
    })
    AppDispatcher.dispatch({
      actionType: SegmentConstants.DELETE_FROM_GLOSSARY,
      sid: sid,
      term,
    })
  },
  errorDeleteGlossaryFromCache: (sid, error) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
      sid: sid,
      message: error.message ? error.message : `Error code: ${error.code}`,
    })
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ERROR_DELETE_FROM_GLOSSARY,
      sid: sid,
      error,
    })
  },

  addGlossaryItem: function (data) {
    const sid = data.id_segment
    addGlossaryItem(data)
      .then(() => {})
      .catch((errors) => {
        if (errors.length > 0) {
          AppDispatcher.dispatch({
            actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
            sid: sid,
            message: errors[0].message,
          })
        } else {
          OfflineUtils.failedConnection(0, 'addGlossaryItem')
        }
      })
  },
  addGlossaryItemToCache: (sid, terms) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
      sid: sid,
      message: 'A glossary item has been added',
    })
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_GLOSSARY_ITEM,
      sid: sid,
      terms,
    })
  },
  errorAddGlossaryItemToCache: (sid, error) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
      sid: sid,
      message: error.message ? error.message : `Error code: ${error.code}`,
    })
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ERROR_ADD_GLOSSARY_ITEM,
      sid: sid,
      error,
    })
  },
  updateGlossaryItem: function (data) {
    updateGlossaryItem(data)
      .then(() => {})
      .catch(() => {
        OfflineUtils.failedConnection(0, 'updateGlossaryItem')
      })
  },

  updateglossaryCache: (sid, terms) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
      sid: sid,
      message: 'A glossary item has been updated',
    })
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CHANGE_GLOSSARY,
      sid: sid,
      terms,
    })
  },
  errorUpdateglossaryCache: (sid, error) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
      sid: sid,
      message: error.message ? error.message : `Error code: ${error.code}`,
    })
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ERROR_CHANGE_GLOSSARY,
      sid: sid,
      error,
    })
  },

  copyGlossaryItemInEditarea: function (glossaryTranslation, segment) {
    AppDispatcher.dispatch({
      actionType: EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
      segment: segment,
      glossaryTranslation: glossaryTranslation,
    })
  },

  setTabIndex: function (sid, tab, index) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_TAB_INDEX,
      sid: sid,
      tab: tab,
      data: index,
    })
  },

  openConcordance: function (sid, currentSelectedText, inTarget) {
    SegmentActions.activateTab(sid, 'concordances')
    SegmentActions.findConcordance(sid, {
      text: currentSelectedText,
      inTarget: inTarget,
    })
  },

  findConcordance: function (sid, data) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.FIND_CONCORDANCE,
      sid: sid,
      data: data,
    })
  },

  getContributions: function (sid) {
    TranslationMatches.getContribution(sid, 0)
    TranslationMatches.getContribution(sid, 1)
    TranslationMatches.getContribution(sid, 2)
  },

  getContribution: function (sid, force) {
    TranslationMatches.getContribution(sid, 0, force)
  },

  getContributionsSuccess: function (data, sid) {
    TranslationMatches.processContributions(data, sid)
  },

  setConcordanceResult: function (sid, data) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CONCORDANCE_RESULT,
      sid: sid,
      matches: data.matches,
    })
  },

  modifyTabVisibility: function (tabName, visible) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.MODIFY_TAB_VISIBILITY,
      tabName: tabName,
      visible: visible,
    })
  },

  /************ Revise ***************/
  showSelection: function (sid, data) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_SELECTION,
      sid: sid,
      data: data,
    })
  },

  openIssuesPanel: function (data, openSegment) {
    if (UI.openIssuesPanel(data, openSegment)) {
      AppDispatcher.dispatch({
        actionType: SegmentConstants.OPEN_ISSUES_PANEL,
        data: data,
      })
    }
  },

  closeIssuesPanel: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CLOSE_ISSUES_PANEL,
    })
    $('body').removeClass(
      'side-tools-opened review-side-panel-opened review-extended-opened',
    )
    localStorage.setItem(ReviewExtended.localStoragePanelClosed, true)
  },

  closeSegmentIssuePanel: function (sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CLOSE_ISSUES_PANEL,
      sid: sid,
    })
    localStorage.setItem(ReviewExtended.localStoragePanelClosed, true)
    this.scrollToSegment(sid)
  },

  showIssuesMessage: function (sid, type) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SHOW_ISSUE_MESSAGE,
      sid: sid,
      data: type,
    })
  },

  submitIssue: function (sid, data) {
    return UI.submitIssues(sid, data)
  },

  issueAdded: function (sid, issueId) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ISSUE_ADDED,
      sid: sid,
      data: issueId,
    })
  },

  openIssueComments: function (sid, issueId) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.OPEN_ISSUE_COMMENT,
      sid: sid,
      data: issueId,
    })
  },

  addPreloadedIssuesToSegment: function (issues) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES,
      versionsIssues: issues,
    })
  },

  addTranslationIssuesToSegment: function (sid, versions) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES,
      sid: sid,
      versions: versions,
    })
  },

  deleteIssue: function (issue, sid, dontShowMessage) {
    UI.deleteIssue(issue, sid, dontShowMessage)
  },

  confirmDeletedIssue: function (sid, issue_id) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ISSUE_DELETED,
      sid: sid,
      data: issue_id,
    })
  },

  submitComment: function (sid, idIssue, data) {
    return UI.submitComment(sid, idIssue, data)
  },

  showApproveAllModalWarnirng: function () {
    var props = {
      text: 'It was not possible to approve all segments. There are some segments that have not been translated.',
      successText: 'Ok',
      successCallback: function () {
        ModalsActions.onCloseModal()
      },
    }
    ModalsActions.showModalComponent(ConfirmMessageModal, props, 'Warning')
  },
  showTranslateAllModalWarnirng: function () {
    var props = {
      text: 'It was not possible to translate all segments.',
      successText: 'Ok',
      successCallback: function () {
        ModalsActions.onCloseModal()
      },
    }
    ModalsActions.showModalComponent(ConfirmMessageModal, props, 'Warning')
  },
  approveFilteredSegments: function (segmentsArray) {
    if (segmentsArray.length >= 500) {
      var subArray = segmentsArray.slice(0, 499)
      var todoArray = segmentsArray.slice(500, segmentsArray.length - 1)
      return this.approveFilteredSegments(subArray).then(() => {
        return this.approveFilteredSegments(todoArray)
      })
    } else {
      const promise = approveSegments(segmentsArray)
      promise.then((response) => {
        this.checkUnchangebleSegments(response, segmentsArray, 'APPROVED')
        setTimeout(CatToolActions.updateFooterStatistics(), 2000)
      })
      return promise
    }
  },
  translateFilteredSegments: function (segmentsArray) {
    if (segmentsArray.length >= 500) {
      var subArray = segmentsArray.slice(0, 499)
      var todoArray = segmentsArray.slice(499, segmentsArray.length)
      return this.translateFilteredSegments(subArray).then(() => {
        return this.translateFilteredSegments(todoArray)
      })
    } else {
      const promise = translateSegments(segmentsArray)
      promise.then((response) => {
        this.checkUnchangebleSegments(response, segmentsArray, 'TRANSLATED')
        setTimeout(CatToolActions.updateFooterStatistics(), 2000)
      })
      return promise
    }
  },
  checkUnchangebleSegments: function (response, status) {
    if (response.unchangeble_segments.length > 0) {
      if (!config.isReview) {
        this.showTranslateAllModalWarnirng()
      } else {
        this.showApproveAllModalWarnirng()
      }
    }
  },
  bulkChangeStatusCallback: function (segmentsArray, status) {
    if (segmentsArray.length > 0) {
      segmentsArray.forEach((item) => {
        var segment = SegmentStore.getSegmentByIdToJS(item)
        if (segment) {
          SegmentActions.setStatus(item, segment.id_file, status)
          SegmentActions.modifiedTranslation(item, false)
          SegmentActions.disableTPOnSegment(segment)
        }
      })
      setTimeout(CatToolActions.reloadSegmentFilter, 500)
    }
  },
  toggleSegmentOnBulk: function (sid, fid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.TOGGLE_SEGMENT_ON_BULK,
      fid: fid,
      sid: sid,
    })
  },

  removeSegmentsOnBulk: function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.REMOVE_SEGMENTS_ON_BULK,
    })
  },

  setSegmentLocked(segment, fid, unlocked) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_UNLOCKED_SEGMENT,
      fid: fid,
      sid: segment.sid,
      unlocked: unlocked,
    })

    if (!unlocked) {
      //TODO: move this to SegmentActions
      CommonUtils.removeFromStorage('unlocked-' + segment.sid)
      if (segment.inBulk) {
        this.toggleSegmentOnBulk(segment.sid, fid)
      }
    } else {
      CommonUtils.addInStorage('unlocked-' + segment.sid, true)
      SegmentActions.openSegment(segment.sid)
    }
  },

  unlockSegments(segments) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_UNLOCKED_SEGMENTS,
      segments,
    })
    segments.forEach((segmentSid) => {
      CommonUtils.addInStorage('unlocked-' + segmentSid, true)
    })
  },

  setBulkSelectionInterval(from, to, fid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_BULK_SELECTION_INTERVAL,
      from: from,
      to: to,
      fid: fid,
    })
  },
  setBulkSelectionSegments(segmentsArray) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
      segmentsArray: segmentsArray,
    })
  },
  setMutedSegments(segmentsArray) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_MUTED_SEGMENTS,
      segmentsArray: segmentsArray,
    })
  },
  removeAllMutedSegments() {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.REMOVE_MUTED_SEGMENTS,
    })
  },

  openSideSegments() {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.OPEN_SIDE,
    })
  },
  closeSideSegments() {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CLOSE_SIDE,
    })
  },
  openSegmentComment(sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.OPEN_COMMENTS,
      sid: sid,
    })
  },
  closeSegmentComment(sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CLOSE_COMMENTS,
      sid: sid,
    })
  },
  gotoNextSegment() {
    let next = SegmentStore.getNextSegment()
    if (next) {
      SegmentActions.openSegment(next.sid)
    } else {
      this.closeSegment()
    }
  },
  gotoNextUntranslatedSegment() {
    let next = SegmentStore.getNextUntranslatedSegmentId()
    if (next) SegmentActions.openSegment(next)
  },
  setNextUntranslatedSegmentFromServer(sid) {
    SegmentStore.nextUntranslatedFromServer = sid
  },
  copyFragmentToClipboard: function (fragment, plainText) {
    AppDispatcher.dispatch({
      actionType: EditAreaConstants.COPY_FRAGMENT_TO_CLIPBOARD,
      fragment,
      plainText,
    })
  },
  editAreaChanged: function (sid, isTarget) {
    AppDispatcher.dispatch({
      actionType: EditAreaConstants.EDIT_AREA_CHANGED,
      sid,
      isTarget,
    })
  },
  highlightTags: function (tagId, tagPlaceholder, entityKey, isTarget) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.HIGHLIGHT_TAGS,
      tagId,
      tagPlaceholder,
      entityKey,
      isTarget,
    })
  },
  toggleCharacterCounter: () => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.TOGGLE_CHARACTER_COUNTER,
    })
  },
  characterCounter: ({sid, counter, limit}) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.CHARACTER_COUNTER,
      sid,
      counter,
      limit,
    })
  },
  getMoreSegments: (where) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.GET_MORE_SEGMENTS,
      where,
    })
  },
  removeAllSegments: () => {
    UI.removeCacheObjects()
    AppDispatcher.dispatch({
      actionType: SegmentConstants.REMOVE_ALL_SEGMENTS,
    })
  },
  freezingSegments: (isFreezing) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.FREEZING_SEGMENTS,
      isFreezing,
    })
  },
  getSegmentsQa: (segment) => {
    if (!segment) return

    var segment_status = segment.status

    const src_content = TagUtils.prepareTextToSend(segment.updatedSource)
    const trg_content = TagUtils.prepareTextToSend(segment.translation)

    getLocalWarnings({
      id: segment.sid,
      id_job: config.id_job,
      password: config.password,
      src_content: src_content,
      trg_content: trg_content,
      segment_status: segment_status,
      characters_counter: segment.charactersCounter ?? 0,
    })
      .then((data) => {
        if (data.details && data.details.id_segment) {
          SegmentActions.setSegmentWarnings(
            data.details.id_segment,
            data.details.issues_info,
            data.details.tag_mismatch,
          )
        } else {
          SegmentActions.setSegmentWarnings(segment.original_sid, {}, {})
        }
        $(document).trigger('getWarning:local:success', {
          resp: data,
          segment: segment,
        })
      })
      .catch(() => {
        OfflineUtils.failedConnection(0, 'getWarning')
      })
    // get tm keys
    new Promise((resolve) => {
      if (!CatToolStore.getJobTmKeys() || !CatToolStore.getHaveKeysGlossary()) {
        let isJobTmKeysCompleted = !!CatToolStore.getJobTmKeys()
        let isHaveKeysGlossaryCompleted = !!CatToolStore.getHaveKeysGlossary()

        const resolvePromise = () =>
          isJobTmKeysCompleted && isHaveKeysGlossaryCompleted && resolve()

        const setJobTmKeys = () => {
          isJobTmKeysCompleted = true
          resolvePromise()

          CatToolStore.removeListener(
            CatToolConstants.UPDATE_TM_KEYS,
            setJobTmKeys,
          )
        }
        const setHaveKeysGlossary = () => {
          isHaveKeysGlossaryCompleted = true
          resolvePromise()

          CatToolStore.removeListener(
            CatToolConstants.HAVE_KEYS_GLOSSARY,
            setHaveKeysGlossary,
          )
        }

        CatToolStore.addListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)
        CatToolStore.addListener(
          CatToolConstants.HAVE_KEYS_GLOSSARY,
          setHaveKeysGlossary,
        )
      } else {
        resolve()
      }
    }).then(() => {
      if (CatToolStore.getHaveKeysGlossary() && trg_content) {
        const jobTmKeys = CatToolStore.getJobTmKeys()
        getGlossaryCheck({
          idSegment: segment.sid,
          target: trg_content,
          source: src_content,
          keys: jobTmKeys.map(({key}) => key),
        })
      }
    })
  },
  highlightGlossaryTerm: ({sid, termId, type, isTarget}) => {
    SegmentActions.activateTab(sid, 'glossary')
    AppDispatcher.dispatch({
      actionType: SegmentConstants.HIGHLIGHT_GLOSSARY_TERM,
      sid,
      termId,
      type,
      isTarget,
    })
  },
  setIsCurrentSearchOccurrenceTag: (value) => {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG,
      value,
    })
  },
}

export default SegmentActions
