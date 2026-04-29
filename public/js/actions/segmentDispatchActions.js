import AppDispatcher from '../stores/AppDispatcher'
import SegmentConstants from '../constants/SegmentConstants'

export const setStatus = (sid, fid, status) => {
  if (sid) {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.SET_SEGMENT_STATUS,
      id: sid,
      fid: fid,
      status: status,
    })
  }
}

export const setHeaderPercentage = (sid, fid, match, className, createdBy) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_SEGMENT_HEADER,
    id: sid,
    fid: fid,
    match,
    className: className,
    createdBy: createdBy,
  })
}

export const hideSegmentHeader = (sid, fid) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.HIDE_SEGMENT_HEADER,
    id: sid,
    fid: fid,
  })
}

export const setSegmentPropagation = (sid, fid, propagation, from) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_SEGMENT_PROPAGATION,
    id: sid,
    fid: fid,
    propagation: propagation,
    from: from,
  })
}

export const modifiedTranslation = (sid, status) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.MODIFIED_TRANSLATION,
    sid: sid,
    status: status,
  })
}

export const replaceEditAreaTextContent = (sid, text) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.REPLACE_TRANSLATION,
    id: sid,
    translation: text,
  })
}

export const setChoosenSuggestion = (sid, index) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_CHOOSEN_SUGGESTION,
    sid: sid,
    index: index,
  })
}

export const setSegmentContributions = (sid, contributions, errors) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_CONTRIBUTIONS,
    sid: sid,
    matches: contributions,
    errors: errors,
  })
}

export const setSegmentSaving = (sid, saving) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_SEGMENT_SAVING,
    sid,
    saving,
  })
}

export const setMutedSegments = (segmentsArray) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_MUTED_SEGMENTS,
    segmentsArray: segmentsArray,
  })
}

export const removeAllMutedSegments = () => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.REMOVE_MUTED_SEGMENTS,
  })
}

export const activateTab = (sid, tab) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.OPEN_TAB,
    sid: sid,
    data: tab,
  })
}

export const highlightGlossaryTerm = ({sid, termId, type, isTarget}) => {
  activateTab(sid, 'glossary')
  AppDispatcher.dispatch({
    actionType: SegmentConstants.HIGHLIGHT_GLOSSARY_TERM,
    sid,
    termId,
    type,
    isTarget,
  })
}

export const addSearchResultToSegments = (
  occurrencesList,
  searchResultsDictionary,
  currentIndex,
  text,
) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.ADD_SEARCH_RESULTS,
    occurrencesList,
    searchResultsDictionary,
    currentIndex,
    text,
  })
}

export const removeSearchResultToSegments = () => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.REMOVE_SEARCH_RESULTS,
  })
}

export const qaComponentsetLxqIssues = (issues) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.QA_LEXIQA_ISSUES,
    warnings: issues,
  })
}

export const addLexiqaHighlight = (sid, matches, type) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.ADD_LXQ_HIGHLIGHT,
    sid: sid,
    matches: matches,
    type: type,
  })
}

export const setSegmentWarnings = (sid, warnings, tagMismatch) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_SEGMENT_WARNINGS,
    sid: sid,
    warnings: warnings,
    tagMismatch: tagMismatch,
  })
}

export const setSegmentAsTagged = (sid, fid) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.SET_SEGMENT_TAGGED,
    id: sid,
    fid: fid,
  })
}
