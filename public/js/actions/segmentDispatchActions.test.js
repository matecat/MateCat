jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))

jest.mock('../constants/SegmentConstants', () => ({
  SET_SEGMENT_STATUS: 'SET_SEGMENT_STATUS',
  SET_SEGMENT_DISABLED: 'SET_SEGMENT_DISABLED',
  SET_SEGMENT_HEADER: 'SET_SEGMENT_HEADER',
  HIDE_SEGMENT_HEADER: 'HIDE_SEGMENT_HEADER',
  SET_SEGMENT_PROPAGATION: 'SET_SEGMENT_PROPAGATION',
  MODIFIED_TRANSLATION: 'MODIFIED_TRANSLATION',
  REPLACE_TRANSLATION: 'REPLACE_TRANSLATION',
  SET_CHOOSEN_SUGGESTION: 'SET_CHOOSEN_SUGGESTION',
  SET_CONTRIBUTIONS: 'SET_CONTRIBUTIONS',
  SET_SEGMENT_SAVING: 'SET_SEGMENT_SAVING',
  SET_MUTED_SEGMENTS: 'SET_MUTED_SEGMENTS',
  REMOVE_MUTED_SEGMENTS: 'REMOVE_MUTED_SEGMENTS',
  OPEN_TAB: 'OPEN_TAB',
  HIGHLIGHT_GLOSSARY_TERM: 'HIGHLIGHT_GLOSSARY_TERM',
  ADD_SEARCH_RESULTS: 'ADD_SEARCH_RESULTS',
  REMOVE_SEARCH_RESULTS: 'REMOVE_SEARCH_RESULTS',
  QA_LEXIQA_ISSUES: 'QA_LEXIQA_ISSUES',
  ADD_LXQ_HIGHLIGHT: 'ADD_LXQ_HIGHLIGHT',
  SET_SEGMENT_WARNINGS: 'SET_SEGMENT_WARNINGS',
  SET_SEGMENT_TAGGED: 'SET_SEGMENT_TAGGED',
}))

import AppDispatcher from '../stores/AppDispatcher'
import {
  setStatus,
  setSegmentDisabled,
  setHeaderPercentage,
  hideSegmentHeader,
  setSegmentPropagation,
  modifiedTranslation,
  replaceEditAreaTextContent,
  setChoosenSuggestion,
  setSegmentContributions,
  setSegmentSaving,
  setMutedSegments,
  removeAllMutedSegments,
  activateTab,
  highlightGlossaryTerm,
  addSearchResultToSegments,
  removeSearchResultToSegments,
  qaComponentsetLxqIssues,
  addLexiqaHighlight,
  setSegmentWarnings,
  setSegmentAsTagged,
} from './segmentDispatchActions'

describe('segmentDispatchActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('setStatus dispatches SET_SEGMENT_STATUS when sid is set', () => {
    setStatus(1, 2, 'DONE')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_STATUS',
      id: 1,
      fid: 2,
      status: 'DONE',
    })
  })

  test('setStatus does nothing when sid is falsy', () => {
    setStatus(0, 2, 'DONE')

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })

  test('setSegmentDisabled dispatches SET_SEGMENT_DISABLED when sid is set', () => {
    setSegmentDisabled(1, true)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_DISABLED',
      id: 1,
      disabled: true,
    })
  })

  test('setSegmentDisabled does nothing when sid is falsy', () => {
    setSegmentDisabled(null, true)

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })

  test('setHeaderPercentage dispatches SET_SEGMENT_HEADER', () => {
    setHeaderPercentage(1, 2, 90, 'match-class', 'user1')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_HEADER',
      id: 1,
      fid: 2,
      match: 90,
      className: 'match-class',
      createdBy: 'user1',
    })
  })

  test('hideSegmentHeader dispatches HIDE_SEGMENT_HEADER', () => {
    hideSegmentHeader(1, 2)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HIDE_SEGMENT_HEADER',
      id: 1,
      fid: 2,
    })
  })

  test('setSegmentPropagation dispatches SET_SEGMENT_PROPAGATION', () => {
    setSegmentPropagation(1, 2, true, 'source')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_PROPAGATION',
      id: 1,
      fid: 2,
      propagation: true,
      from: 'source',
    })
  })

  test('modifiedTranslation dispatches MODIFIED_TRANSLATION', () => {
    modifiedTranslation(1, 'DRAFT')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'MODIFIED_TRANSLATION',
      sid: 1,
      status: 'DRAFT',
    })
  })

  test('replaceEditAreaTextContent dispatches REPLACE_TRANSLATION', () => {
    replaceEditAreaTextContent(1, 'hello')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REPLACE_TRANSLATION',
      id: 1,
      translation: 'hello',
    })
  })

  test('setChoosenSuggestion dispatches SET_CHOOSEN_SUGGESTION', () => {
    setChoosenSuggestion(1, 3)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_CHOOSEN_SUGGESTION',
      sid: 1,
      index: 3,
    })
  })

  test('setSegmentContributions dispatches SET_CONTRIBUTIONS', () => {
    setSegmentContributions(1, ['m1'], ['e1'])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_CONTRIBUTIONS',
      sid: 1,
      matches: ['m1'],
      errors: ['e1'],
    })
  })

  test('setSegmentSaving dispatches SET_SEGMENT_SAVING', () => {
    setSegmentSaving(1, true)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_SAVING',
      sid: 1,
      saving: true,
    })
  })

  test('setMutedSegments dispatches SET_MUTED_SEGMENTS', () => {
    setMutedSegments([1, 2])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_MUTED_SEGMENTS',
      segmentsArray: [1, 2],
    })
  })

  test('removeAllMutedSegments dispatches REMOVE_MUTED_SEGMENTS', () => {
    removeAllMutedSegments()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_MUTED_SEGMENTS',
    })
  })

  test('activateTab dispatches OPEN_TAB', () => {
    activateTab(1, 'glossary')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_TAB',
      sid: 1,
      data: 'glossary',
    })
  })

  test('highlightGlossaryTerm activates glossary tab and dispatches HIGHLIGHT_GLOSSARY_TERM', () => {
    highlightGlossaryTerm({sid: 1, termId: 5, type: 'source', isTarget: false})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_TAB',
      sid: 1,
      data: 'glossary',
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HIGHLIGHT_GLOSSARY_TERM',
      sid: 1,
      termId: 5,
      type: 'source',
      isTarget: false,
    })
  })

  test('addSearchResultToSegments dispatches ADD_SEARCH_RESULTS', () => {
    addSearchResultToSegments([1, 2], {1: 'a'}, 0, 'text')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_SEARCH_RESULTS',
      occurrencesList: [1, 2],
      searchResultsDictionary: {1: 'a'},
      currentIndex: 0,
      text: 'text',
    })
  })

  test('removeSearchResultToSegments dispatches REMOVE_SEARCH_RESULTS', () => {
    removeSearchResultToSegments()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_SEARCH_RESULTS',
    })
  })

  test('qaComponentsetLxqIssues dispatches QA_LEXIQA_ISSUES', () => {
    qaComponentsetLxqIssues(['w1'])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'QA_LEXIQA_ISSUES',
      warnings: ['w1'],
    })
  })

  test('addLexiqaHighlight dispatches ADD_LXQ_HIGHLIGHT', () => {
    addLexiqaHighlight(1, ['m1'], 'source')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_LXQ_HIGHLIGHT',
      sid: 1,
      matches: ['m1'],
      type: 'source',
    })
  })

  test('setSegmentWarnings dispatches SET_SEGMENT_WARNINGS', () => {
    setSegmentWarnings(1, ['w1'], true)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_WARNINGS',
      sid: 1,
      warnings: ['w1'],
      tagMismatch: true,
    })
  })

  test('setSegmentAsTagged dispatches SET_SEGMENT_TAGGED', () => {
    setSegmentAsTagged(1, 2)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_TAGGED',
      id: 1,
      fid: 2,
    })
  })
})
