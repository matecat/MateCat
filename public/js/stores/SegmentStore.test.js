import {fromJS} from 'immutable'

import AppDispatcher from './AppDispatcher'
import SegmentConstants from '../constants/SegmentConstants'
import EditAreaConstants from '../constants/EditAreaConstants'
import {
  splittedTranslationPlaceholder,
  REVISE_STEP_NUMBER,
  SEGMENTS_STATUS,
} from '../constants/Constants'

jest.mock('./AppDispatcher', () => ({
  __esModule: true,
  default: {register: jest.fn(), dispatch: jest.fn()},
}))

jest.mock('../components/segments/utils/DraftMatecatUtils/tagUtils', () => ({
  transformTagsToText: (t) => (t === undefined || t === null ? '' : t),
  removeTagsFromText: (t) => t,
  checkXliffTagsInText: () => false,
  hasDataOriginalTags: () => false,
}))

jest.mock('../utils/tagProjectionUtils', () => ({
  checkTPEnabled: () => false,
  checkCurrentSegmentTPEnabled: () => false,
}))

jest.mock('../utils/segmentUtils', () => ({
  __esModule: true,
  default: {isUnlockedSegment: () => false},
}))

import SegmentStore from './SegmentStore'

const freshGlobalWarnings = () => ({
  lexiqa: [],
  matecat: {
    ERROR: {Categories: [], total: 0},
    WARNING: {Categories: [], total: 0},
    INFO: {Categories: [], total: 0},
  },
})

const makeSegment = (sid, overrides = {}) => ({
  sid: String(sid),
  segment: 'source ' + sid,
  translation: 'translation ' + sid,
  status: 'NEW',
  revision_number: REVISE_STEP_NUMBER.REVISE1,
  repetitions_in_chunk: 1,
  readonly: 'false',
  id_file: '1',
  notes: null,
  metadata: [],
  target_chunk_lengths: {len: [0], statuses: ['DRAFT']},
  ...overrides,
})

let dispatch

describe('SegmentStore', () => {
  beforeAll(async () => {
    global.config = {
      revisionNumber: REVISE_STEP_NUMBER.REVISE1,
      word_count_type: 'equivalent',
    }
    // allow the async import('../utils/segmentUtils') inside the store to resolve
    await new Promise((resolve) => setTimeout(resolve, 0))
    const callback = AppDispatcher.register.mock.calls[0][0]
    dispatch = (action) => callback(action)
  })

  beforeEach(() => {
    SegmentStore._segments = fromJS([])
    SegmentStore._footerTabsConfig = fromJS({})
    SegmentStore._globalWarnings = freshGlobalWarnings()
    SegmentStore.segmentsInBulk = []
    SegmentStore.searchOccurrences = []
    SegmentStore.searchResultsDictionary = {}
    SegmentStore.currentInSearch = 0
    SegmentStore.searchParams = {}
    SegmentStore.nextUntranslatedFromServer = null
    SegmentStore.clipboardFragment = ''
    SegmentStore.clipboardPlainText = ''
    SegmentStore.sideOpen = false
    SegmentStore._aiSuggestions = []
    SegmentStore.currentSegmentId = undefined
    SegmentStore.editStart = undefined
    SegmentStore.removeAllListeners()
    jest.spyOn(SegmentStore, 'emitChange').mockImplementation(() => {})
  })

  afterEach(() => {
    jest.restoreAllMocks()
  })

  const render = (segments) =>
    dispatch({actionType: SegmentConstants.RENDER_SEGMENTS, segments})

  describe('registration', () => {
    test('registers a single dispatcher callback', () => {
      expect(AppDispatcher.register).toHaveBeenCalledTimes(1)
      expect(typeof AppDispatcher.register.mock.calls[0][0]).toBe('function')
    })
  })

  describe('updateAll / RENDER_SEGMENTS', () => {
    test('renders segments and exposes them via getAllSegments', () => {
      render([makeSegment(1), makeSegment(2)])
      const all = SegmentStore.getAllSegments()
      expect(all).toHaveLength(2)
      expect(all[0].sid).toBe('1')
      expect(all[0].splitted).toBe(false)
      expect(all[0].tagged).toBe(true)
      expect(all[0].unlocked).toBe(false)
    })

    test('RENDER_SEGMENTS with idToOpen opens the segment', () => {
      dispatch({
        actionType: SegmentConstants.RENDER_SEGMENTS,
        segments: [makeSegment(1), makeSegment(2)],
        idToOpen: '2',
      })
      expect(SegmentStore.getSegmentByIdToJS('2').opened).toBe(true)
    })

    test('RENDER_SEGMENTS while search active emits UPDATE_SEARCH', () => {
      SegmentStore.searchOccurrences = ['1']
      render([makeSegment(1)])
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        SegmentConstants.UPDATE_SEARCH,
      )
    })

    test('updateAll prepends when where is before', () => {
      render([makeSegment(2)])
      dispatch({
        actionType: SegmentConstants.ADD_SEGMENTS,
        segments: [makeSegment(1)],
        where: 'before',
      })
      expect(SegmentStore.getAllSegments()[0].sid).toBe('1')
    })

    test('updateAll appends when where is after', () => {
      render([makeSegment(1)])
      dispatch({
        actionType: SegmentConstants.ADD_SEGMENTS,
        segments: [makeSegment(2)],
        where: 'after',
      })
      const all = SegmentStore.getAllSegments()
      expect(all[all.length - 1].sid).toBe('2')
    })

    test('updateAll re-applies bulk selection when segmentsInBulk not empty', () => {
      SegmentStore.segmentsInBulk = ['1']
      render([makeSegment(1)])
      expect(SegmentStore.getSegmentByIdToJS('1').inBulk).toBe(true)
    })

    test('normalizeSplittedSegments handles splitted segments', () => {
      const seg = makeSegment(20, {
        segment: 'a' + splittedTranslationPlaceholder + 'b',
        translation: 'x' + splittedTranslationPlaceholder + 'y',
        target_chunk_lengths: {len: [0, 0], statuses: ['DRAFT', 'DRAFT']},
      })
      render([seg])
      const parts = SegmentStore.getSegmentsSplitGroup('20')
      expect(parts).toHaveLength(2)
      expect(parts[0].splitted).toBe(true)
      expect(parts[0].sid).toBe('20-1')
      expect(parts[0].firstOfSplit).toBe(true)
    })
  })

  describe('open / close / select', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2), makeSegment(3)]))

    test('OPEN_SEGMENT opens a segment and getCurrentSegment reflects it', () => {
      dispatch({actionType: SegmentConstants.OPEN_SEGMENT, sid: '2'})
      expect(SegmentStore.getCurrentSegment().sid).toBe('2')
      expect(SegmentStore.getCurrentSegmentId()).toBe('2')
    })

    test('SET_OPEN_SEGMENT opens the segment and closes splits', () => {
      dispatch({actionType: SegmentConstants.SET_OPEN_SEGMENT, sid: '3'})
      expect(SegmentStore.getSegmentByIdToJS('3').opened).toBe(true)
    })

    test('CLOSE_SEGMENT closes all opened segments', () => {
      dispatch({actionType: SegmentConstants.OPEN_SEGMENT, sid: '1'})
      dispatch({actionType: SegmentConstants.CLOSE_SEGMENT, sid: '1'})
      expect(SegmentStore.getCurrentSegment()).toBeUndefined()
    })

    test('SET_CURRENT_SEGMENT_ID stores the id and edit start time', () => {
      dispatch({actionType: SegmentConstants.SET_CURRENT_SEGMENT_ID, sid: '2'})
      expect(SegmentStore.currentSegmentId).toBe('2')
      expect(SegmentStore.getStartEditTime()).toBeInstanceOf(Date)
    })

    test('SELECT_SEGMENT next selects following segment', () => {
      dispatch({actionType: SegmentConstants.OPEN_SEGMENT, sid: '1'})
      dispatch({actionType: SegmentConstants.SELECT_SEGMENT, direction: 'next'})
      expect(SegmentStore.getSelectedSegmentId()).toBe('2')
    })

    test('SELECT_SEGMENT prev selects previous segment', () => {
      dispatch({actionType: SegmentConstants.OPEN_SEGMENT, sid: '3'})
      SegmentStore.currentSegmentId = '3'
      dispatch({actionType: SegmentConstants.SELECT_SEGMENT, direction: 'prev'})
      expect(SegmentStore.getSelectedSegmentId()).toBe('2')
    })

    test('getSelectedSegmentId returns null when nothing selected', () => {
      expect(SegmentStore.getSelectedSegmentId()).toBeNull()
    })
  })

  describe('status / metadata / propagation', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2)]))

    test('SET_SEGMENT_STATUS updates status', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_STATUS,
        id: '1',
        fid: '1',
        status: SEGMENTS_STATUS.TRANSLATED,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').status).toBe('TRANSLATED')
    })

    test('setStatus maps APPROVED to APPROVED2 during second revision', () => {
      global.config.revisionNumber = REVISE_STEP_NUMBER.REVISE2
      SegmentStore.setStatus('1', '1', SEGMENTS_STATUS.APPROVED)
      expect(SegmentStore.getSegmentByIdToJS('1').status).toBe('APPROVED2')
      global.config.revisionNumber = REVISE_STEP_NUMBER.REVISE1
    })

    test('setStatus is a no-op for unknown segment', () => {
      expect(() => SegmentStore.setStatus('999', '1', 'NEW')).not.toThrow()
    })

    test('SET_SEGMENT_DISABLED adds and updates translation_disabled metadata', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_DISABLED,
        id: '1',
        disabled: true,
      })
      let meta = SegmentStore.getSegmentByIdToJS('1').metadata
      expect(meta[0]).toEqual({
        meta_key: 'translation_disabled',
        meta_value: '1',
      })
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_DISABLED,
        id: '1',
        disabled: false,
      })
      meta = SegmentStore.getSegmentByIdToJS('1').metadata
      expect(meta[0].meta_value).toBe('0')
    })

    test('SET_SEGMENT_HEADER updates suggestion match', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_HEADER,
        id: '1',
        fid: '1',
        match: {match: '85%'},
      })
      expect(SegmentStore.getSegmentByIdToJS('1').suggestion_match).toBe('85')
    })

    test('HIDE_SEGMENT_HEADER emits propagation change', () => {
      dispatch({
        actionType: SegmentConstants.HIDE_SEGMENT_HEADER,
        id: '1',
        fid: '1',
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        SegmentConstants.SET_SEGMENT_PROPAGATION,
        '1',
        false,
      )
    })

    test('SET_SEGMENT_PROPAGATION sets and clears autopropagated_from', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_PROPAGATION,
        id: '1',
        fid: '1',
        propagation: true,
        from: '5',
      })
      expect(SegmentStore.getSegmentByIdToJS('1').autopropagated_from).toBe('5')
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_PROPAGATION,
        id: '1',
        fid: '1',
        propagation: false,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').autopropagated_from).toBe(0)
    })
  })

  describe('translation / source updates', () => {
    beforeEach(() => render([makeSegment(1)]))

    test('SET_SEGMENT_ORIGINAL_TRANSLATION updates original translation', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION,
        id: '1',
        originalTranslation: 'orig',
      })
      const seg = SegmentStore.getSegmentByIdToJS('1')
      expect(seg.originalDecodedTranslation).toBe('orig')
      expect(seg.decodedTranslation).toBe('orig')
    })

    test('REPLACE_TRANSLATION replaces translation', () => {
      dispatch({
        actionType: SegmentConstants.REPLACE_TRANSLATION,
        id: '1',
        translation: 'replaced',
      })
      expect(SegmentStore.getSegmentByIdToJS('1').translation).toBe('replaced')
    })

    test('UPDATE_TRANSLATION marks modified and updates fields', () => {
      dispatch({
        actionType: SegmentConstants.UPDATE_TRANSLATION,
        id: '1',
        translation: 'brand new',
        decodedTranslation: 'brand new',
        tagMap: [],
        missingTagsInTarget: [],
        lxqDecodedTranslation: 'brand new',
      })
      const seg = SegmentStore.getSegmentByIdToJS('1')
      expect(seg.translation).toBe('brand new')
      expect(seg.modified).toBe(true)
    })

    test('updateTranslation marks not modified when equal to original', () => {
      const original =
        SegmentStore.getSegmentByIdToJS('1').originalDecodedTranslation
      SegmentStore.updateTranslation('1', original, original, [], [], original)
      expect(SegmentStore.getSegmentByIdToJS('1').modified).toBe(false)
    })

    test('updateTranslation no-op for unknown segment', () => {
      expect(() =>
        SegmentStore.updateTranslation('999', 't', 't', [], [], 't'),
      ).not.toThrow()
    })

    test('UPDATE_SOURCE updates source fields', () => {
      dispatch({
        actionType: SegmentConstants.UPDATE_SOURCE,
        id: '1',
        source: 'new source',
        decodedSource: 'new source',
        tagMap: [],
        lxqDecodedSource: 'new source',
      })
      const seg = SegmentStore.getSegmentByIdToJS('1')
      expect(seg.updatedSource).toBe('new source')
      expect(seg.decodedSource).toBe('new source')
    })

    test('MODIFIED_TRANSLATION toggles modified flag', () => {
      dispatch({
        actionType: SegmentConstants.MODIFIED_TRANSLATION,
        sid: '1',
        status: true,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').modified).toBe(true)
      dispatch({
        actionType: SegmentConstants.MODIFIED_TRANSLATION,
        sid: '1',
        status: false,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').modified).toBe(false)
    })
  })

  describe('edit area lock / tabs', () => {
    beforeEach(() => render([makeSegment(1)]))

    test('LOCK_EDIT_AREA toggles lock on', () => {
      dispatch({actionType: SegmentConstants.LOCK_EDIT_AREA, id: '1'})
      expect(SegmentStore.getSegmentByIdToJS('1').edit_area_locked).toBe(true)
    })

    test('UNLOCK_EDIT_AREA sets lock off explicitly', () => {
      dispatch({actionType: SegmentConstants.LOCK_EDIT_AREA, id: '1'})
      dispatch({actionType: SegmentConstants.UNLOCK_EDIT_AREA, id: '1'})
      expect(SegmentStore.getSegmentByIdToJS('1').edit_area_locked).toBe(false)
    })

    test('REGISTER_TAB configures a footer tab', () => {
      dispatch({
        actionType: SegmentConstants.REGISTER_TAB,
        tab: 'matches',
        visible: true,
        open: true,
      })
      expect(SegmentStore._footerTabsConfig.getIn(['matches', 'visible'])).toBe(
        true,
      )
      expect(SegmentStore._footerTabsConfig.getIn(['matches', 'enabled'])).toBe(
        true,
      )
    })

    test('SET_DEFAULT_TAB opens a tab and closes others', () => {
      dispatch({
        actionType: SegmentConstants.REGISTER_TAB,
        tab: 'matches',
        visible: true,
        open: true,
      })
      dispatch({
        actionType: SegmentConstants.SET_DEFAULT_TAB,
        tabName: 'glossary',
        fid: '1',
      })
      expect(SegmentStore._footerTabsConfig.getIn(['matches', 'open'])).toBe(
        false,
      )
      expect(SegmentStore._footerTabsConfig.getIn(['glossary', 'open'])).toBe(
        true,
      )
    })

    test('MODIFY_TAB_VISIBILITY and SHOW_FOOTER_MESSAGE emit', () => {
      dispatch({
        actionType: SegmentConstants.MODIFY_TAB_VISIBILITY,
        tabName: 'matches',
        visible: false,
      })
      dispatch({
        actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
        sid: '1',
        message: 'hi',
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        SegmentConstants.MODIFY_TAB_VISIBILITY,
        'matches',
        false,
      )
    })
  })

  describe('contributions / alternatives', () => {
    beforeEach(() => render([makeSegment(1)]))

    test('SET_CONTRIBUTIONS caches matches array', () => {
      dispatch({
        actionType: SegmentConstants.SET_CONTRIBUTIONS,
        sid: '1',
        matches: [{id: 'a'}, {id: 'b'}],
        errors: [],
      })
      const seg = SegmentStore.getSegmentByIdToJS('1')
      expect(seg.contributions.matches).toHaveLength(2)
    })

    test('setContributionsToCache stores undefined for non-array', () => {
      SegmentStore.setContributionsToCache('1', null, [])
      expect(SegmentStore.getSegmentByIdToJS('1').contributions).toBeUndefined()
    })

    test('SET_CL_CONTRIBUTIONS caches cross language matches', () => {
      dispatch({
        actionType: SegmentConstants.SET_CL_CONTRIBUTIONS,
        sid: '1',
        fid: '1',
        matches: [{id: 'a'}],
        errors: [],
      })
      expect(
        SegmentStore.getSegmentByIdToJS('1').cl_contributions.matches,
      ).toHaveLength(1)
    })

    test('SET_ALTERNATIVES sets and clears alternatives', () => {
      dispatch({
        actionType: SegmentConstants.SET_ALTERNATIVES,
        sid: '1',
        alternatives: [{a: 1}],
      })
      expect(SegmentStore.getSegmentByIdToJS('1').alternatives).toHaveLength(1)
      dispatch({
        actionType: SegmentConstants.SET_ALTERNATIVES,
        sid: '1',
        alternatives: undefined,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').alternatives).toBeUndefined()
    })

    test('DELETE_CONTRIBUTION removes a match by id', () => {
      SegmentStore.setContributionsToCache('1', [{id: 'a'}, {id: 'b'}], [])
      dispatch({
        actionType: SegmentConstants.DELETE_CONTRIBUTION,
        sid: '1',
        matchId: 'a',
      })
      const matches = SegmentStore.getSegmentByIdToJS('1').contributions.matches
      expect(matches).toHaveLength(1)
      expect(matches[0].id).toBe('b')
    })

    test('CHOOSE_CONTRIBUTION emits change', () => {
      dispatch({
        actionType: SegmentConstants.CHOOSE_CONTRIBUTION,
        sid: '1',
        index: 1,
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        SegmentConstants.CHOOSE_CONTRIBUTION,
        '1',
        1,
      )
    })

    test('getSegmentChoosenContribution returns chosen match', () => {
      SegmentStore.setContributionsToCache('1', [{id: 'a'}, {id: 'b'}], [])
      SegmentStore.setChoosenSuggestion('1', 1)
      expect(SegmentStore.getSegmentChoosenContribution('1').id).toBe('a')
    })
  })

  describe('glossary', () => {
    beforeEach(() => render([makeSegment(1)]))

    const term = (overrides = {}) => ({
      term_id: 't1',
      matching_words: ['word', ''],
      metadata: {key: 'k', key_name: 'kn'},
      ...overrides,
    })

    test('SET_GLOSSARY_TO_CACHE caches filtered glossary terms', () => {
      dispatch({
        actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
        sid: '1',
        glossary: [term()],
      })
      const seg = SegmentStore.getSegmentByIdToJS('1')
      expect(seg.glossary).toHaveLength(1)
      expect(seg.glossary[0].matching_words).toEqual(['word'])
    })

    test('SET_GLOSSARY_TO_CACHE_BY_SEARCH caches search results', () => {
      dispatch({
        actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE_BY_SEARCH,
        sid: '1',
        glossary: [term({term_id: 's1'})],
      })
      expect(
        SegmentStore.getSegmentByIdToJS('1').glossary_search_results,
      ).toHaveLength(1)
    })

    test('ADD_GLOSSARY_ITEM adds a glossary term', () => {
      dispatch({
        actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
        sid: '1',
        glossary: [term()],
      })
      dispatch({
        actionType: SegmentConstants.ADD_GLOSSARY_ITEM,
        sid: '1',
        payload: {term: term({term_id: 't2'})},
      })
      expect(
        SegmentStore.getSegmentByIdToJS('1').glossary.length,
      ).toBeGreaterThan(0)
    })

    test('CHANGE_GLOSSARY updates a glossary term', () => {
      dispatch({
        actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
        sid: '1',
        glossary: [term()],
      })
      dispatch({
        actionType: SegmentConstants.CHANGE_GLOSSARY,
        sid: '1',
        payload: {term: term({matching_words: ['updated']})},
      })
      const g = SegmentStore.getSegmentByIdToJS('1').glossary
      expect(g[0].matching_words).toEqual(['updated'])
    })

    test('DELETE_FROM_GLOSSARY removes a term', () => {
      dispatch({
        actionType: SegmentConstants.SET_GLOSSARY_TO_CACHE,
        sid: '1',
        glossary: [term()],
      })
      dispatch({
        actionType: SegmentConstants.DELETE_FROM_GLOSSARY,
        sid: '1',
        term: {term_id: 't1'},
      })
      expect(SegmentStore.getSegmentByIdToJS('1').glossary).toHaveLength(0)
    })

    test('normalizeSetUpdateGlossary handles metadata.keys form', () => {
      dispatch({
        actionType: SegmentConstants.ADD_GLOSSARY_ITEM,
        sid: '1',
        payload: {
          term: term({
            metadata: {keys: [{key: 'k1', key_name: 'n1'}]},
          }),
        },
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        SegmentConstants.ADD_GLOSSARY_ITEM,
        expect.any(Object),
      )
    })

    test('glossary error actions emit change', () => {
      dispatch({
        actionType: SegmentConstants.ERROR_ADD_GLOSSARY_ITEM,
        sid: '1',
        error: 'boom',
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        SegmentConstants.ERROR_ADD_GLOSSARY_ITEM,
        '1',
        'boom',
      )
    })

    test('SET_QA_CHECK adds missing terms and blacklist', () => {
      dispatch({
        actionType: SegmentConstants.SET_QA_CHECK,
        sid: '1',
        data: {
          missing_terms: [term({term_id: 'm1'})],
          blacklisted_terms: [{id: 'b1'}],
        },
      })
      expect(
        SegmentStore.getSegmentByIdToJS('1').qaBlacklistGlossary,
      ).toHaveLength(1)
    })
  })

  describe('bulk selection', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2), makeSegment(3)]))

    test('TOGGLE_SEGMENT_ON_BULK adds then removes a segment', () => {
      dispatch({actionType: SegmentConstants.TOGGLE_SEGMENT_ON_BULK, sid: '1'})
      expect(SegmentStore.segmentsInBulk).toContain('1')
      dispatch({actionType: SegmentConstants.TOGGLE_SEGMENT_ON_BULK, sid: '1'})
      expect(SegmentStore.segmentsInBulk).not.toContain('1')
    })

    test('SET_BULK_SELECTION_INTERVAL selects a range', () => {
      dispatch({
        actionType: SegmentConstants.SET_BULK_SELECTION_INTERVAL,
        from: 1,
        to: 3,
        fid: '1',
      })
      expect(SegmentStore.segmentsInBulk).toEqual(
        expect.arrayContaining(['1', '2', '3']),
      )
    })

    test('SET_BULK_SELECTION_SEGMENTS sets flags and skips ice locked', () => {
      SegmentStore._segments = SegmentStore._segments.setIn(
        [1, 'ice_locked'],
        true,
      )
      dispatch({
        actionType: SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
        segmentsArray: ['1', '2'],
      })
      expect(SegmentStore.getSegmentByIdToJS('1').inBulk).toBe(true)
      expect(SegmentStore.segmentsInBulk).not.toContain('2')
    })

    test('REMOVE_SEGMENTS_ON_BULK clears the selection', () => {
      dispatch({actionType: SegmentConstants.TOGGLE_SEGMENT_ON_BULK, sid: '1'})
      dispatch({actionType: SegmentConstants.REMOVE_SEGMENTS_ON_BULK})
      expect(SegmentStore.segmentsInBulk).toHaveLength(0)
    })
  })

  describe('unlock / mute', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2)]))

    test('SET_UNLOCKED_SEGMENT unlocks a segment', () => {
      dispatch({
        actionType: SegmentConstants.SET_UNLOCKED_SEGMENT,
        sid: '1',
        fid: '1',
        unlocked: true,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').unlocked).toBe(true)
    })

    test('SET_UNLOCKED_SEGMENTS unlocks many segments', () => {
      dispatch({
        actionType: SegmentConstants.SET_UNLOCKED_SEGMENTS,
        segments: ['1', '2'],
      })
      expect(SegmentStore.getSegmentByIdToJS('2').unlocked).toBe(true)
    })

    test('SET_MUTED_SEGMENTS mutes non listed segments', () => {
      dispatch({
        actionType: SegmentConstants.SET_MUTED_SEGMENTS,
        segmentsArray: ['1'],
      })
      expect(SegmentStore.getSegmentByIdToJS('1').muted).toBeUndefined()
      expect(SegmentStore.getSegmentByIdToJS('2').muted).toBe(true)
    })

    test('REMOVE_MUTED_SEGMENTS clears muted flags', () => {
      dispatch({
        actionType: SegmentConstants.SET_MUTED_SEGMENTS,
        segmentsArray: ['1'],
      })
      dispatch({actionType: SegmentConstants.REMOVE_MUTED_SEGMENTS})
      expect(SegmentStore.getSegmentByIdToJS('2').muted).toBe(false)
    })
  })

  describe('warnings', () => {
    beforeEach(() => render([makeSegment(1)]))

    test('SET_SEGMENT_WARNINGS sets local warnings', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_WARNINGS,
        sid: '1',
        warnings: {ERROR: {}},
        tagMismatch: {foo: 'bar'},
      })
      const seg = SegmentStore.getSegmentByIdToJS('1')
      expect(seg.warnings).toEqual({ERROR: {}})
      expect(seg.tagMismatch).toEqual({foo: 'bar'})
    })

    test('UPDATE_GLOBAL_WARNINGS aggregates categories', () => {
      dispatch({
        actionType: SegmentConstants.UPDATE_GLOBAL_WARNINGS,
        warnings: {
          ERROR: {Categories: {cat1: ['1', '2']}, total: 0},
          WARNING: {Categories: {}, total: 0},
          INFO: {Categories: {}, total: 0},
        },
      })
      expect(SegmentStore.getGlobalWarnings().matecat.total).toBeGreaterThan(0)
    })

    test('QA_LEXIQA_ISSUES updates lexiqa warnings', () => {
      dispatch({
        actionType: SegmentConstants.QA_LEXIQA_ISSUES,
        warnings: ['1'],
      })
      expect(SegmentStore.getGlobalWarnings().lexiqa).toEqual(['1'])
    })

    test('QA_LEXIQA_ISSUES with empty list removes lexiqa warnings', () => {
      dispatch({
        actionType: SegmentConstants.QA_LEXIQA_ISSUES,
        warnings: [],
      })
      expect(SegmentStore.getGlobalWarnings().lexiqa).toEqual([])
    })

    test('hasGlobalErrors detects errors for a sid', () => {
      SegmentStore._globalWarnings.matecat.ERROR.Categories = {cat: ['1']}
      expect(SegmentStore.hasGlobalErrors('1')).toBe(true)
      expect(SegmentStore.hasGlobalErrors('999')).toBe(false)
    })

    test('filterGlobalWarning handles TAGS and default types', () => {
      expect(SegmentStore.filterGlobalWarning('TAGS', '1')).toBe(true)
      expect(SegmentStore.filterGlobalWarning('OTHER', 5)).toBe(true)
    })

    test('ADD_LXQ_HIGHLIGHT adds highlights per type', () => {
      dispatch({
        actionType: SegmentConstants.ADD_LXQ_HIGHLIGHT,
        sid: '1',
        matches: [{a: 1}],
        type: 1,
      })
      dispatch({
        actionType: SegmentConstants.ADD_LXQ_HIGHLIGHT,
        sid: '1',
        matches: [{b: 2}],
        type: 2,
      })
      dispatch({
        actionType: SegmentConstants.ADD_LXQ_HIGHLIGHT,
        sid: '1',
        matches: {c: 3},
        type: 3,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').lexiqa).toBeDefined()
    })
  })

  describe('side panel / comments / issues', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2)]))

    test('OPEN_ISSUES_PANEL opens issues and side panel', () => {
      dispatch({
        actionType: SegmentConstants.OPEN_ISSUES_PANEL,
        data: {sid: '1'},
      })
      expect(SegmentStore.isSideOpen()).toBe(true)
      expect(SegmentStore.isSidePanelToOpen()).toBe(true)
    })

    test('CLOSE_ISSUES_PANEL closes issues and side', () => {
      dispatch({
        actionType: SegmentConstants.OPEN_ISSUES_PANEL,
        data: {sid: '1'},
      })
      dispatch({actionType: SegmentConstants.CLOSE_ISSUES_PANEL})
      expect(SegmentStore.isSideOpen()).toBe(false)
    })

    test('OPEN_COMMENTS opens comments and side', () => {
      dispatch({actionType: SegmentConstants.OPEN_COMMENTS, sid: '1'})
      expect(SegmentStore.getSegmentByIdToJS('1').openComments).toBe(true)
      expect(SegmentStore.isSideOpen()).toBe(true)
    })

    test('CLOSE_COMMENTS closes comments and side', () => {
      dispatch({actionType: SegmentConstants.OPEN_COMMENTS, sid: '1'})
      dispatch({actionType: SegmentConstants.CLOSE_COMMENTS})
      expect(SegmentStore.getSegmentByIdToJS('1').openComments).toBe(false)
      expect(SegmentStore.isSideOpen()).toBe(false)
    })

    test('CLOSE_SIDE closes issues and comments', () => {
      dispatch({actionType: SegmentConstants.OPEN_COMMENTS, sid: '1'})
      dispatch({actionType: SegmentConstants.CLOSE_SIDE})
      expect(SegmentStore.isSideOpen()).toBe(false)
    })

    test('OPEN_SIDE opens when a panel should be open', () => {
      SegmentStore.openSegmentComments('1')
      dispatch({actionType: SegmentConstants.OPEN_SIDE})
      expect(SegmentStore.isSideOpen()).toBe(true)
    })

    test('closeSegmentComments handles a specific sid', () => {
      SegmentStore.openSegmentComments('1')
      SegmentStore.closeSegmentComments('1')
      expect(SegmentStore.getSegmentByIdToJS('1').openComments).toBe(false)
    })

    test('segmentHasIssues detects issues', () => {
      expect(SegmentStore.segmentHasIssues(null)).toBe(false)
      expect(
        SegmentStore.segmentHasIssues({
          versions: [{issues: [{id: 1}]}],
        }),
      ).toBe(true)
    })
  })

  describe('split segments', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2)]))

    test('OPEN_SPLIT_SEGMENT opens split then CLOSE_SPLIT_SEGMENT closes', () => {
      dispatch({actionType: SegmentConstants.OPEN_SPLIT_SEGMENT, sid: '1'})
      expect(SegmentStore.getSegmentByIdToJS('1').openSplit).toBe(true)
      dispatch({actionType: SegmentConstants.CLOSE_SPLIT_SEGMENT})
      expect(SegmentStore.getSegmentByIdToJS('1').openSplit).toBe(false)
    })

    test('getSegmentsInSplit returns split members', () => {
      SegmentStore._segments = SegmentStore._segments.setIn(
        [0, 'original_sid'],
        '1',
      )
      expect(SegmentStore.getSegmentsInSplit('1')).toHaveLength(1)
    })
  })

  describe('search', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2), makeSegment(3)]))

    test('ADD_SEARCH_RESULTS stores occurrences and flags segments', () => {
      dispatch({
        actionType: SegmentConstants.ADD_SEARCH_RESULTS,
        occurrencesList: ['1', '2'],
        searchResultsDictionary: {1: 1, 2: 1},
        currentIndex: 0,
        text: {query: 'foo'},
      })
      expect(SegmentStore.searchOccurrences).toEqual(['1', '2'])
      expect(SegmentStore.getSegmentByIdToJS('1').inSearch).toBe(true)
    })

    test('ADD_CURRENT_SEARCH updates the current occurrence', () => {
      dispatch({
        actionType: SegmentConstants.ADD_SEARCH_RESULTS,
        occurrencesList: ['1', '2'],
        searchResultsDictionary: {1: 1, 2: 1},
        currentIndex: 0,
        text: {query: 'foo'},
      })
      dispatch({
        actionType: SegmentConstants.ADD_CURRENT_SEARCH,
        currentIndex: 1,
      })
      expect(SegmentStore.getSegmentByIdToJS('2').currentInSearch).toBe(true)
    })

    test('REMOVE_SEARCH_RESULTS clears search state', () => {
      dispatch({
        actionType: SegmentConstants.ADD_SEARCH_RESULTS,
        occurrencesList: ['1'],
        searchResultsDictionary: {1: 1},
        currentIndex: 0,
        text: {query: 'foo'},
      })
      dispatch({actionType: SegmentConstants.REMOVE_SEARCH_RESULTS})
      expect(SegmentStore.searchOccurrences).toHaveLength(0)
      expect(SegmentStore.currentInSearch).toBe(0)
    })

    test('REPLACE_SEARCH_RESULTS emits with text', () => {
      dispatch({
        actionType: EditAreaConstants.REPLACE_SEARCH_RESULTS,
        text: 'abc',
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        EditAreaConstants.REPLACE_SEARCH_RESULTS,
        'abc',
      )
    })
  })

  describe('clipboard / ai suggestions / misc', () => {
    beforeEach(() => render([makeSegment(1)]))

    test('COPY_FRAGMENT_TO_CLIPBOARD stores fragment', () => {
      dispatch({
        actionType: EditAreaConstants.COPY_FRAGMENT_TO_CLIPBOARD,
        fragment: 'frag',
        plainText: 'plain',
      })
      expect(SegmentStore.getFragmentFromClipboard()).toEqual({
        fragment: 'frag',
        plainText: 'plain',
      })
    })

    test('COPY_GLOSSARY_IN_EDIT_AREA emits change', () => {
      dispatch({
        actionType: EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
        segment: 's',
        glossaryTranslation: 'g',
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
        's',
        'g',
      )
    })

    test('EDIT_AREA_CHANGED emits change', () => {
      dispatch({
        actionType: EditAreaConstants.EDIT_AREA_CHANGED,
        sid: '1',
        isTarget: true,
      })
      expect(SegmentStore.emitChange).toHaveBeenCalledWith(
        EditAreaConstants.EDIT_AREA_CHANGED,
        '1',
        true,
      )
    })

    test('AI_SUGGESTION stores a suggestion retrievable by sid', () => {
      dispatch({
        actionType: SegmentConstants.AI_SUGGESTION,
        sid: '1',
        suggestion: 'hello',
      })
      expect(SegmentStore.getAiSuggestion('1').suggestion).toBe('hello')
    })

    test('setAiSuggestion caps stored suggestions at the maximum', () => {
      for (let i = 0; i < 15; i++) {
        SegmentStore.setAiSuggestion({sid: String(i), suggestion: 's' + i})
      }
      expect(SegmentStore._aiSuggestions.length).toBeLessThanOrEqual(11)
    })

    test('HELP_AI_ASSISTANT stores helper words', () => {
      dispatch({
        actionType: SegmentConstants.HELP_AI_ASSISTANT,
        words: 'help',
      })
      expect(SegmentStore.helpAiAssistantWords.words).toBe('help')
    })

    test('CHARACTER_COUNTER stores counter data', () => {
      dispatch({
        actionType: SegmentConstants.CHARACTER_COUNTER,
        sid: '1',
        counter: 5,
        segmentCharacters: 10,
        limit: 100,
      })
      const seg = SegmentStore.getSegmentByIdToJS('1')
      expect(seg.charactersCounter).toBe(5)
      expect(seg.segmentCharacters).toBe(10)
    })

    test('CONCORDANCE_RESULT stores concordance matches', () => {
      dispatch({
        actionType: SegmentConstants.CONCORDANCE_RESULT,
        sid: '1',
        matches: [{id: 'c'}],
      })
      expect(SegmentStore.getSegmentByIdToJS('1').concordance).toHaveLength(1)
    })

    test('SET_SEGMENT_TAGGED marks segment tagged', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_TAGGED,
        id: '1',
        fid: '1',
      })
      expect(SegmentStore.getSegmentByIdToJS('1').tagged).toBe(true)
    })

    test('SET_SEGMENT_SAVING toggles saving flag', () => {
      dispatch({
        actionType: SegmentConstants.SET_SEGMENT_SAVING,
        sid: '1',
        saving: true,
      })
      expect(SegmentStore.getSegmentByIdToJS('1').saving).toBe(true)
    })

    test('SET_GUESS_TAGS updates tag projection status', () => {
      dispatch({actionType: SegmentConstants.OPEN_SEGMENT, sid: '1'})
      dispatch({actionType: SegmentConstants.SET_GUESS_TAGS, enabled: true})
      expect(SegmentStore.getSegmentByIdToJS('1').tpEnabled).toBe(true)
    })

    test('ADD_SEGMENT_VERSIONS_ISSUES sets versions', () => {
      dispatch({
        actionType: SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES,
        sid: '1',
        versions: [{id: 3, translation: 't', issues: []}],
      })
      expect(SegmentStore.getSegmentByIdToJS('1').versions).toHaveLength(1)
    })

    test('addSegmentVersions clears versions for empty placeholder version', () => {
      const result = SegmentStore.addSegmentVersions('1', [
        {id: 0, translation: ''},
      ])
      expect(result.get('versions').size).toBe(0)
    })

    test('ADD_SEGMENT_PRELOADED_ISSUES sets preloaded issues', () => {
      dispatch({
        actionType: SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES,
        versionsIssues: {1: [{id: 'i1'}]},
      })
      expect(
        SegmentStore.getSegmentByIdToJS('1').versions[0].issues,
      ).toHaveLength(1)
    })

    test('pass-through emit-only actions do not throw', () => {
      const actions = [
        {actionType: SegmentConstants.SCROLL_TO_SEGMENT, sid: '1'},
        {
          actionType: SegmentConstants.ADD_SEGMENT_CLASS,
          id: '1',
          newClass: 'c',
        },
        {
          actionType: SegmentConstants.REMOVE_SEGMENT_CLASS,
          id: '1',
          className: 'c',
        },
        {actionType: SegmentConstants.UPDATE_ALL_SEGMENTS},
        {
          actionType: SegmentConstants.ADD_TAB_INDEX,
          sid: '1',
          tab: 't',
          data: 1,
        },
        {actionType: SegmentConstants.GET_MORE_SEGMENTS, where: 'after'},
        {actionType: SegmentConstants.FREEZING_SEGMENTS, isFreezing: true},
        {actionType: SegmentConstants.HIGHLIGHT_GLOSSARY_TERM, term: 'x'},
        {
          actionType: SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG,
          sid: '1',
        },
        {actionType: SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL, sid: '1'},
        {actionType: SegmentConstants.FOCUS_TAGS, sid: '1'},
        {actionType: SegmentConstants.REFRESH_TAG_MAP},
        {actionType: SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES},
        {actionType: SegmentConstants.LARA_STYLES, data: {}},
        {actionType: SegmentConstants.AI_ALTERNATIVES, data: {}},
        {actionType: SegmentConstants.AI_FEEDBACK, data: {}},
        {actionType: SegmentConstants.AI_ALTERNATIVES_SUGGESTION, data: {}},
        {actionType: SegmentConstants.AI_FEEDBACK_SUGGESTION, data: {}},
        {
          actionType: SegmentConstants.HIGHLIGHT_TAGS,
          tagId: 't',
          tagPlaceholder: 'p',
          entityKey: 'e',
          isTarget: true,
        },
        {actionType: 'UNKNOWN_ACTION_TYPE', sid: '1', data: 'x'},
      ]
      actions.forEach((a) => expect(() => dispatch(a)).not.toThrow())
    })

    test('REMOVE_ALL_SEGMENTS empties the store', () => {
      dispatch({actionType: SegmentConstants.REMOVE_ALL_SEGMENTS})
      expect(SegmentStore.getAllSegments()).toHaveLength(0)
    })
  })

  describe('getters and navigation', () => {
    beforeEach(() => render([makeSegment(1), makeSegment(2), makeSegment(3)]))

    test('getSegmentById / getSegmentByIndex / getSegmentIndex', () => {
      expect(SegmentStore.getSegmentById('2').get('sid')).toBe('2')
      expect(SegmentStore.getSegmentByIndex(0).get('sid')).toBe('1')
      expect(SegmentStore.getSegmentIndex('2')).toBe(1)
      expect(SegmentStore.getSegmentIndex('999')).toBe(-1)
    })

    test('getSegmentIndex works with splitted sids', () => {
      const seg = makeSegment(50, {
        segment: 'a' + splittedTranslationPlaceholder + 'b',
        translation: 'x' + splittedTranslationPlaceholder + 'y',
        target_chunk_lengths: {len: [0, 0], statuses: ['DRAFT', 'DRAFT']},
      })
      render([seg])
      expect(SegmentStore.getSegmentIndex('50-2')).toBe(1)
    })

    test('getFirstSegmentId / getLastSegmentId', () => {
      expect(SegmentStore.getFirstSegmentId()).toBe('1')
      expect(SegmentStore.getLastSegmentId()).toBe('3')
    })

    test('getSegmentByIdToJS returns null for unknown', () => {
      expect(SegmentStore.getSegmentByIdToJS('999')).toBeNull()
    })

    test('getNextSegment finds following segment', () => {
      const next = SegmentStore.getNextSegment({current_sid: '1'})
      expect(next.sid).toBe('2')
    })

    test('getNextSegment returns null with no current and no sid', () => {
      expect(SegmentStore.getNextSegment()).toBeNull()
    })

    test('getNextSegment finds untranslated segment', () => {
      const next = SegmentStore.getNextSegment({
        current_sid: '1',
        status: SEGMENTS_STATUS.UNTRANSLATED,
      })
      expect(next.sid).toBe('2')
    })

    test('getNextSegment matches a specific status', () => {
      SegmentStore.setStatus('3', '1', SEGMENTS_STATUS.TRANSLATED)
      const next = SegmentStore.getNextSegment({
        current_sid: '1',
        status: SEGMENTS_STATUS.TRANSLATED,
      })
      expect(next.sid).toBe('3')
    })

    test('getNextSegment handles UNAPPROVED with revision number', () => {
      SegmentStore.setStatus('2', '1', SEGMENTS_STATUS.TRANSLATED)
      const next = SegmentStore.getNextSegment({
        current_sid: '1',
        status: SEGMENTS_STATUS.UNAPPROVED,
        revisionNumber: REVISE_STEP_NUMBER.REVISE1,
      })
      expect(next.sid).toBe('2')
    })

    test('getNextUntranslatedSegmentId returns next untranslated sid', () => {
      dispatch({actionType: SegmentConstants.OPEN_SEGMENT, sid: '1'})
      expect(SegmentStore.getNextUntranslatedSegmentId()).toBe('2')
    })

    test('getPrevSegment finds previous segment', () => {
      const prev = SegmentStore.getPrevSegment('3')
      expect(prev.sid).toBe('2')
    })

    test('getSegmentsInPropagation filters by hash', () => {
      SegmentStore._segments = SegmentStore._segments
        .setIn([0, 'segment_hash'], 'h1')
        .setIn([1, 'segment_hash'], 'h1')
      const result = SegmentStore.getSegmentsInPropagation('h1', true)
      expect(Array.isArray(result)).toBe(true)
    })

    test('copyFragmentToClipboard / getFragmentFromClipboard round-trips', () => {
      SegmentStore.copyFragmentToClipboard('f', 'p')
      expect(SegmentStore.getFragmentFromClipboard()).toEqual({
        fragment: 'f',
        plainText: 'p',
      })
    })

    test('setLastTranslatedSegmentId stores id on the store', () => {
      SegmentStore.setLastTranslatedSegmentId('42')
      expect(SegmentStore.lastTranslatedSegmentId).toBe('42')
    })

    test('emitChange delegates to emit', () => {
      SegmentStore.emitChange.mockRestore()
      const emitSpy = jest
        .spyOn(SegmentStore, 'emit')
        .mockImplementation(() => {})
      SegmentStore.emitChange('EVT', 1)
      expect(emitSpy).toHaveBeenCalledWith('EVT', 1)
    })

    test('setSegmentAsTagged and getSegmentsSplitGroup helpers', () => {
      SegmentStore.setSegmentAsTagged('1')
      expect(SegmentStore.getSegmentByIdToJS('1').tagged).toBe(true)
    })

    test('addSegmentPreloadedIssues no-op for unknown segment', () => {
      expect(SegmentStore.addSegmentPreloadedIssues('999', [])).toBeUndefined()
    })
  })
})
