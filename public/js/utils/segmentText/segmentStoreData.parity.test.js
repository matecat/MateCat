// Golden check: segmentStoreData must give the store exactly what the Draft bridges (sourceEditorBridge,
// targetEditorBridge) gave it, for every segment string. It runs both on the round-trip corpus and on the edge cases
// below. Deleted with Draft, once its expected values are frozen into segmentStoreData's own tests.
import SegmentActions from '../../actions/SegmentActions'
import {
  publishSource,
  tagIdentities as draftSourceTagIdentities,
} from '../../components/segments/sourceEditorBridge'
import {
  charactersCounter as draftCharactersCounter,
  tagIdentities as draftTargetTagIdentities,
  translationUpdate as draftTranslationUpdate,
} from '../../components/segments/targetEditorBridge'
import CORPUS from '../../components/editor/core/__fixtures__/roundTripCorpus.json'
import {
  charactersCounter,
  sourceStoreData,
  tagIdentities,
  translationUpdate,
} from './segmentStoreData'

jest.mock('../../actions/SegmentActions', () => ({updateSource: jest.fn()}))

const b64 = (text) => Buffer.from(text, 'utf8').toString('base64')

const EDGE_CASES = [
  '',
  'Plain text without tags',
  '<g id="1"></g>',
  '<x id="1"/><bx id="2"/><ex id="3"/>',
  'One##$_0A$##two',
  'One##$_0D$##two',
  'One##$_0D$####$_0A$##two',
  '##$_0A$####$_0A$##',
  '##$_0A$##',
  'a##$_A0$##b##$_09$##c',
  '##$_A0$##',
  'Emoji \u{1F600} and \u{20000} astral',
  '&lt;b&gt; &amp; &amp;lt; text',
  '&lt;g id="1"&gt;escaped&lt;/g&gt;',
  `<ph id="" equiv-text="base64:${b64('{1}')}"/> and <ph id="" equiv-text="base64:${b64('{2}')}"/>`,
  `<ph id="mtc_1" equiv-text="base64:${b64('&lt;br&gt;')}"/>`,
  '<g id="1">outer <g id="2">inner</g> tail</g>',
  '<g id="1">unclosed',
  'stray close</g> here',
  '<g id="1" equiv-text="base64:Ym9sZA==">equiv</g>',
  'Line<g id="1">##$_0A$##inside</g>',
  `<ph id="1" equiv-text="base64:${b64('a\nb')}"/>`,
  `A line break inside a tag: <ph id="1" equiv-text="base64:${b64('a##$_0A$##b')}"/> end`,
  `Two: <ph id="1" equiv-text="base64:${b64('##$_0D$####$_0A$##')}"/><ph id="2" equiv-text="base64:${b64('x##$_0A$##')}"/>`,
  'Split##$_SPLIT$##point',
  'word\u2060joiner',
  'Real\nnewline and\r\nCRLF and\rCR',
  '\u200B',
]

const SEGMENTS = [...CORPUS.map(({segment}) => segment), ...EDGE_CASES]

const draftSource = (source) => {
  SegmentActions.updateSource.mockClear()
  publishSource('1', source)
  const call = SegmentActions.updateSource.mock.calls[0]
  if (!call) return undefined
  const [, decodedSegment, plainText, tagRange, lxqText] = call
  return {decodedSegment, plainText, tagRange, lxqText}
}

beforeEach(() => {
  Object.assign(global.config, {
    lfPlaceholder: '##$_0A$##',
    crPlaceholder: '##$_0D$##',
    tabPlaceholder: '##$_09$##',
    nbspPlaceholder: '##$_A0$##',
  })
})

describe('as a source', () => {
  test('the empty source never reaches the store', () => {
    expect(draftSource('')).toBeUndefined()
  })

  test.each(SEGMENTS.filter((source) => source !== ''))(
    'publishes what Draft publishes: %j',
    (source) => {
      expect(sourceStoreData(source)).toStrictEqual(draftSource(source))
      expect(tagIdentities(source)).toStrictEqual(
        draftSourceTagIdentities(source),
      )
    },
  )
})

describe('as a target', () => {
  // Each segment against its own source tags (nothing missing) and against its neighbour's (tags missing, indexes
  // inherited from a different source), and with no source tag map at all.
  const sourceTagMaps = (index) => [
    undefined,
    [],
    sourceStoreData(SEGMENTS[index] || 'x').tagRange,
    sourceStoreData(SEGMENTS[(index + 1) % SEGMENTS.length] || 'x').tagRange,
  ]

  test.each(SEGMENTS.map((segment, index) => [segment, index]))(
    'updates the store as Draft does: %j',
    (translation, index) => {
      sourceTagMaps(index).forEach((sourceTagMap) => {
        expect(translationUpdate(translation, sourceTagMap)).toStrictEqual(
          draftTranslationUpdate(translation, sourceTagMap),
        )
        expect(tagIdentities(translation, sourceTagMap)).toStrictEqual(
          draftTargetTagIdentities(translation, sourceTagMap),
        )
      })
      ;[true, false].forEach((countTags) =>
        expect(charactersCounter(translation, countTags)).toBe(
          draftCharactersCounter(translation, countTags),
        ),
      )
    },
  )
})
