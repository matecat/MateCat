jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegment: jest.fn(),
  getSegmentsInSplit: jest.fn(),
  getAllSegments: jest.fn(),
  getSegmentIndex: jest.fn(),
  getStartEditTime: jest.fn(),
  getSegmentByIdToJS: jest.fn(),
}))
jest.mock('../components/segments/utils/DraftMatecatUtils', () => ({
  removeTagsFromText: jest.fn((text) => text),
  hasDataOriginalTags: jest.fn(),
  excludeSomeTagsTransformToText: jest.fn((text) => text),
  decodePlaceholdersToPlainText: jest.fn((text) => text),
  getCharactersCounter: jest.fn((text) => (text ? text.length : 0)),
}))
jest.mock('../stores/UserStore', () => ({
  getUserMetadata: jest.fn(),
}))

import SegmentStore from '../stores/SegmentStore'
import DraftMatecatUtils from '../components/segments/utils/DraftMatecatUtils'
import UserStore from '../stores/UserStore'
import {SEGMENTS_STATUS} from '../constants/Constants'
import SegmentUtils from './segmentUtils'

beforeEach(() => {
  localStorage.clear()
  global.config = {
    ...global.config,
    id_job: 2,
    password: 'pass',
    source_code: 'it-IT',
    target_code: 'en-GB',
    tag_projection_languages: {'it-en': true},
    isReview: false,
    revisionNumber: 1,
    currentPassword: 'current-pass',
    project_completion_feature_enabled: false,
    job_completion_current_phase: 'translate',
  }
  global.globalFunctions = {
    getContextBefore: jest.fn(() => 'before'),
    getIdBefore: jest.fn(() => 'id-before'),
    getContextAfter: jest.fn(() => 'after'),
    getIdAfter: jest.fn(() => 'id-after'),
  }
  DraftMatecatUtils.removeTagsFromText.mockImplementation((text) => text)
  DraftMatecatUtils.hasDataOriginalTags.mockReturnValue(false)
  DraftMatecatUtils.decodePlaceholdersToPlainText.mockImplementation(
    (text) => text,
  )
  DraftMatecatUtils.excludeSomeTagsTransformToText.mockImplementation(
    (text) => text,
  )
  DraftMatecatUtils.getCharactersCounter.mockImplementation((text) =>
    text ? text.length : 0,
  )
})

test('checkTPSupportedLanguage matches configured language pairs in either direction', () => {
  expect(SegmentUtils.checkTPSupportedLanguage()).toBe(true)

  global.config.tag_projection_languages = {'en-it': true}
  expect(SegmentUtils.checkTPSupportedLanguage()).toBe(true)

  global.config.tag_projection_languages = {'fr-de': true}
  expect(SegmentUtils.checkTPSupportedLanguage()).toBe(false)
})

test('checkTPEnabled requires support, guess_tags metadata, and non-review mode', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})
  expect(SegmentUtils.checkTPEnabled()).toBe(true)

  UserStore.getUserMetadata.mockReturnValue({guess_tags: 0})
  expect(SegmentUtils.checkTPEnabled()).toBe(false)

  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})
  global.config.isReview = true
  expect(SegmentUtils.checkTPEnabled()).toBe(false)
})

test('checkCurrentSegmentTPEnabled returns false without a current segment', () => {
  SegmentStore.getCurrentSegment.mockReturnValue(null)

  expect(SegmentUtils.checkCurrentSegmentTPEnabled()).toBe(false)
})

test('checkCurrentSegmentTPEnabled is true for an untagged segment with original tags', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})
  DraftMatecatUtils.hasDataOriginalTags.mockReturnValue(true)
  DraftMatecatUtils.removeTagsFromText.mockReturnValue('hello')

  const segment = {segment: '<g>hello</g>', tagged: false}
  expect(SegmentUtils.checkCurrentSegmentTPEnabled(segment)).toBe(true)
})

test('checkCurrentSegmentTPEnabled falls back to the store current segment', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})
  DraftMatecatUtils.hasDataOriginalTags.mockReturnValue(true)
  DraftMatecatUtils.removeTagsFromText.mockReturnValue('hello')
  SegmentStore.getCurrentSegment.mockReturnValue({
    segment: '<g>hello</g>',
    tagged: false,
  })

  expect(SegmentUtils.checkCurrentSegmentTPEnabled()).toBe(true)
})

test('checkCurrentSegmentTPEnabled is false when already tagged or text is empty', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})
  DraftMatecatUtils.hasDataOriginalTags.mockReturnValue(true)
  DraftMatecatUtils.removeTagsFromText.mockReturnValue('')

  expect(
    SegmentUtils.checkCurrentSegmentTPEnabled({
      segment: '<g></g>',
      tagged: false,
    }),
  ).toBe(false)

  DraftMatecatUtils.removeTagsFromText.mockReturnValue('hello')
  expect(
    SegmentUtils.checkCurrentSegmentTPEnabled({
      segment: '<g>hello</g>',
      tagged: true,
    }),
  ).toBe(false)
})

test('isIceSegment reflects the ice_locked flag', () => {
  expect(SegmentUtils.isIceSegment({ice_locked: true})).toBe(true)
  expect(SegmentUtils.isIceSegment({ice_locked: false})).toBe(false)
})

test('isSecondPassLockedSegment is true only for an approved-2 segment on a different revision', () => {
  const segment = {
    status: SEGMENTS_STATUS.APPROVED2.toLowerCase(),
    revision_number: 2,
  }
  expect(SegmentUtils.isSecondPassLockedSegment(segment)).toBe(true)

  global.config.revisionNumber = 2
  expect(SegmentUtils.isSecondPassLockedSegment(segment)).toBe(false)
})

test('isUnlockedSegment checks the per-job unlocked list in local storage', () => {
  expect(SegmentUtils.isUnlockedSegment({sid: '5'})).toBe(false)

  SegmentUtils.addUnlockedSegment('5')
  expect(SegmentUtils.isUnlockedSegment({sid: '5'})).toBe(true)
  expect(SegmentUtils.isUnlockedSegment({sid: '6'})).toBe(false)
})

test('isUnlockedSegment is always true when all segments are unlocked', () => {
  localStorage.setItem(SegmentUtils.localStorageUnlockedAllSegments, 'true')

  expect(SegmentUtils.isUnlockedSegment({sid: 'anything'})).toBe(true)
})

test('addUnlockedSegment appends to an existing list without duplicating', () => {
  SegmentUtils.addUnlockedSegment('1')
  SegmentUtils.addUnlockedSegment('2')
  SegmentUtils.addUnlockedSegment('1')

  const stored = localStorage.getItem(SegmentUtils.localStorageUnlockedSegments)
  expect(stored.split(',')).toEqual(['1', '2'])
})

test('removeUnlockedSegment removes a previously unlocked segment', () => {
  SegmentUtils.addUnlockedSegment('1')
  SegmentUtils.addUnlockedSegment('2')

  SegmentUtils.removeUnlockedSegment('1')

  const stored = localStorage.getItem(SegmentUtils.localStorageUnlockedSegments)
  expect(stored.split(',')).toEqual(['2'])
})

test('removeUnlockedSegment is a no-op when nothing is stored or the sid is absent', () => {
  expect(() => SegmentUtils.removeUnlockedSegment('1')).not.toThrow()

  SegmentUtils.addUnlockedSegment('1')
  SegmentUtils.removeUnlockedSegment('999')
  expect(localStorage.getItem(SegmentUtils.localStorageUnlockedSegments)).toBe(
    '1',
  )
})

test('getSelectedKeysGlossary filters cached keys down to the ones passed in', () => {
  // the lookup compares Object.keys(item)[0] (always a string) against
  // config.id_job with strict equality, so id_job must be a string here
  // to exercise the "found" branch
  global.config.id_job = '2'
  window.localStorage.setItem(
    'selectedKeysGlossary',
    JSON.stringify([{2: [10, 30]}]),
  )
  const keys = [
    {id: 10, term: 'a'},
    {id: 20, term: 'b'},
  ]

  const result = SegmentUtils.getSelectedKeysGlossary(keys)

  expect(result).toEqual([{id: 10, term: 'a'}])
})

test('getSelectedKeysGlossary returns an empty array when nothing is cached', () => {
  expect(SegmentUtils.getSelectedKeysGlossary([{id: 1}])).toEqual([])
})

test('setSelectedKeysGlossary stores keys under the current job and evicts the oldest entry past the cap', () => {
  const cachedItems = Array.from({length: 101}, (_, i) => ({[`job-${i}`]: [i]}))
  window.localStorage.setItem(
    'selectedKeysGlossary',
    JSON.stringify(cachedItems),
  )

  SegmentUtils.setSelectedKeysGlossary([{id: 42}])

  const stored = JSON.parse(window.localStorage.getItem('selectedKeysGlossary'))
  expect(stored[stored.length - 1]).toEqual({2: [42]})
  expect(stored.length).toBe(101)
})

test('segmentHasNote is true when notes, context groups, or metadata are present', () => {
  expect(SegmentUtils.segmentHasNote({notes: 'a note'})).toBe(true)
  expect(
    SegmentUtils.segmentHasNote({context_groups: {context_json: '{}'}}),
  ).toBe(true)
  expect(SegmentUtils.segmentHasNote({metadata: [1]})).toBe(true)
  expect(SegmentUtils.segmentHasNote({})).toBe(false)
})

test('getSegmentFileId returns the segment file id', () => {
  expect(SegmentUtils.getSegmentFileId({id_file: 7})).toBe(7)
})

test('collectSplittedStatuses substitutes the status for the matching split segment', () => {
  SegmentStore.getSegmentsInSplit.mockReturnValue([
    {sid: '1.1', status: 'NEW'},
    {sid: '1.2', status: 'NEW'},
  ])

  const statuses = SegmentUtils.collectSplittedStatuses(1, '1.2', 'TRANSLATED')

  expect(statuses).toEqual(['NEW', 'TRANSLATED'])
})

test('getSegmentContext throws when the segment cannot be found', () => {
  SegmentStore.getAllSegments.mockReturnValue([])
  SegmentStore.getSegmentIndex.mockReturnValue(-1)

  expect(() => SegmentUtils.getSegmentContext(1)).toThrow('Segment not found.')
})

test('getSegmentContext returns up to 5 segments before and 3 after', () => {
  const segments = Array.from({length: 10}, (_, i) => ({
    sid: i,
    segment: `text-${i}`,
  }))
  SegmentStore.getAllSegments.mockReturnValue(segments)
  SegmentStore.getSegmentIndex.mockReturnValue(5)

  const context = SegmentUtils.getSegmentContext(5)

  expect(context.contextListBefore).toEqual([
    'text-0',
    'text-1',
    'text-2',
    'text-3',
    'text-4',
  ])
  expect(context.contextListAfter).toEqual(['text-6', 'text-7'])
})

test('collectSplittedTranslations concatenates split segments with the placeholder', () => {
  SegmentStore.getSegmentsInSplit.mockReturnValue([
    {segment: 'source1', translation: 'trans1'},
    {segment: 'source2', translation: 'trans2'},
  ])

  expect(SegmentUtils.collectSplittedTranslations(1)).toBe(
    'trans1##$_SPLIT$##trans2',
  )
  expect(SegmentUtils.collectSplittedTranslations(1, '.source')).toBe(
    'source1##$_SPLIT$##source2',
  )
})

test('createSetTranslationRequest builds the payload from segment, config, and context helpers', () => {
  SegmentStore.getStartEditTime.mockReturnValue(0)
  const segment = {
    translation: 'hola',
    segment: 'hello',
    original_sid: 1,
    sid: 1,
    status: 'DRAFT',
    splitted: false,
    charactersCounter: 5,
  }

  const request = SegmentUtils.createSetTranslationRequest(
    segment,
    'TRANSLATED',
    true,
  )

  expect(request).toMatchObject({
    id_segment: 1,
    id_job: 2,
    password: 'current-pass',
    status: 'TRANSLATED',
    translation: 'hola',
    segment: 'hello',
    propagate: true,
    context_before: 'before',
    id_before: 'id-before',
    context_after: 'after',
    id_after: 'id-after',
    splitStatuses: null,
    characters_counter: 5,
  })
})

test('createSetTranslationRequest merges split translations and statuses when the segment is split', () => {
  SegmentStore.getStartEditTime.mockReturnValue(0)
  SegmentStore.getSegmentsInSplit.mockReturnValue([
    {sid: '1.1', segment: 'src1', translation: 'trans1', status: 'NEW'},
    {sid: '1.2', segment: 'src2', translation: 'trans2', status: 'NEW'},
  ])
  const segment = {
    translation: 'ignored',
    segment: 'ignored',
    original_sid: 1,
    sid: '1.2',
    status: 'DRAFT',
    splitted: true,
    charactersCounter: 3,
  }

  const request = SegmentUtils.createSetTranslationRequest(
    segment,
    'TRANSLATED',
  )

  expect(request.translation).toBe('trans1##$_SPLIT$##trans2')
  expect(request.segment).toBe('src1##$_SPLIT$##src2')
  expect(request.splitStatuses).toBe('NEW,TRANSLATED')
})

test('createSetTranslationRequest includes the suggestion array outside review mode', () => {
  SegmentStore.getStartEditTime.mockReturnValue(0)
  const segment = {
    translation: 'hola',
    segment: 'hello',
    original_sid: 1,
    sid: 1,
    status: 'DRAFT',
    splitted: false,
    charactersCounter: 5,
    choosenSuggestionIndex: 1,
    contributions: {
      matches: [{id: 1}, {id: 2}, {id: 3}, {id: 4}, {id: 5}],
    },
  }

  const request = SegmentUtils.createSetTranslationRequest(segment)

  expect(request.chosen_suggestion_index).toBe(1)
  expect(JSON.parse(request.suggestion_array)).toEqual([
    {id: 1},
    {id: 2},
    {id: 3},
  ])
})

test('isReadonlySegment is true when project completion review-phase lock applies', () => {
  global.config.project_completion_feature_enabled = true
  global.config.isReview = false
  global.config.job_completion_current_phase = 'revise'

  expect(SegmentUtils.isReadonlySegment({})).toBe(true)
})

test('isReadonlySegment is true when the segment itself is readonly or translation-disabled', () => {
  expect(SegmentUtils.isReadonlySegment({readonly: true})).toBe(true)
  expect(
    SegmentUtils.isReadonlySegment({
      metadata: [{meta_key: 'translation_disabled', meta_value: true}],
    }),
  ).toBe(true)
  expect(SegmentUtils.isReadonlySegment({readonly: false})).toBeFalsy()
})

test('getRelativeTransUnitCharactersCounter sums characters across the same translation unit', () => {
  SegmentStore.getSegmentByIdToJS.mockReturnValue({internal_id: 'u1'})
  SegmentStore.getAllSegments.mockReturnValue([
    {sid: 1, internal_id: 'u1', translation: 'aaa'},
    {sid: 2, internal_id: 'u1', translation: 'bb'},
    {sid: 3, internal_id: 'u2', translation: 'zzzzz'},
  ])
  DraftMatecatUtils.getCharactersCounter.mockImplementation(
    (text) => text.length,
  )

  const result = SegmentUtils.getRelativeTransUnitCharactersCounter({
    sid: 1,
    charactersCounter: 3,
  })

  expect(result).toEqual({segmentCharacters: 3, unitCharacters: 5})
})

test('getRelativeTransUnitCharactersCounter can count tags as characters', () => {
  SegmentStore.getSegmentByIdToJS.mockReturnValue({internal_id: 'u1'})
  SegmentStore.getAllSegments.mockReturnValue([
    {sid: 1, internal_id: 'u1', translation: 'aaa'},
    {sid: 2, internal_id: 'u1', translation: '<g>bb</g>'},
  ])
  DraftMatecatUtils.excludeSomeTagsTransformToText.mockImplementation(
    (text) => text,
  )
  DraftMatecatUtils.getCharactersCounter.mockImplementation(
    (text) => text.length,
  )

  const result = SegmentUtils.getRelativeTransUnitCharactersCounter({
    sid: 1,
    charactersCounter: 0,
    shouldCountTagsAsChars: true,
  })

  expect(DraftMatecatUtils.excludeSomeTagsTransformToText).toHaveBeenCalled()
  expect(result.unitCharacters).toBe('<g>bb</g>'.length)
})
