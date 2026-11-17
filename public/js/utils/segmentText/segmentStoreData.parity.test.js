// Golden check: segmentStoreData must give the store exactly what the Draft bridges (sourceEditorBridge,
// targetEditorBridge) gave it, for every segment string: the round-trip corpus and the edge cases below. What the
// bridges gave is frozen in __golden__/segmentStoreData.json, as JSON: compared after the same JSON round trip, so tags
// are compared by their fields. A tag is frozen with the fields the store keeps (its offset, and id, name, encodedText,
// decodedText, placeholder, index and tagRole of its data), taken from Draft's tag structs. The edge cases marked below
// are the exceptions: there Draft was wrong.
import CORPUS from '../../components/editor/core/__fixtures__/roundTripCorpus.json'
import {
  charactersCounter,
  sourceStoreData,
  tagIdentities,
  translationUpdate,
} from './segmentStoreData'
import GOLDEN from './__golden__/segmentStoreData.json'

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
  // This case and the next are not Draft's values, on purpose: Draft split its blocks at the line break inside the
  // tag text, dropping the tag from the decoded segment and the tag map (and so reporting it missing). Frozen with
  // the tag kept whole.
  `A line break inside a tag: <ph id="1" equiv-text="base64:${b64('a##$_0A$##b')}"/> end`,
  `Two: <ph id="1" equiv-text="base64:${b64('##$_0D$####$_0A$##')}"/><ph id="2" equiv-text="base64:${b64('x##$_0A$##')}"/>`,
  'Split##$_SPLIT$##point',
  'word\u2060joiner',
  'Real\nnewline and\r\nCRLF and\rCR',
  '\u200B',
]

const SEGMENTS = [...CORPUS.map(({segment}) => segment), ...EDGE_CASES]

const json = (value) => JSON.parse(JSON.stringify(value))

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
    // The Draft bridge published nothing for it: it has no frozen value.
    expect(GOLDEN.source).not.toHaveProperty([''])
  })

  test.each(SEGMENTS.filter((source) => source !== ''))(
    'publishes what Draft publishes: %j',
    (source) => {
      expect(
        json({
          store: sourceStoreData(source),
          tagIdentities: tagIdentities(source),
        }),
      ).toStrictEqual(GOLDEN.source[source])
    },
  )
})

describe('as a target', () => {
  // Each segment against its own source tags (nothing missing), against a neighbour's (tags missing, indexes inherited
  // from a different source) and with no source tag map at all. The golden keys every case by the segment and, under
  // `neighbours`, by the neighbour source it was frozen with, so adding, removing or reordering a case moves no other.
  const sourceTagMap = (source) => sourceStoreData(source || 'x').tagRange
  const update = (translation, tagMap) => ({
    update: translationUpdate(translation, tagMap),
    tagIdentities: tagIdentities(translation, tagMap),
  })

  test.each(SEGMENTS)('updates the store as Draft does: %j', (translation) => {
    const golden = GOLDEN.target[translation]
    expect(
      json({
        counter: [true, false].map((countTags) =>
          charactersCounter(translation, countTags),
        ),
        none: update(translation, undefined),
        empty: update(translation, []),
        own: update(translation, sourceTagMap(translation)),
        neighbours: Object.fromEntries(
          Object.keys(golden?.neighbours ?? {}).map((source) => [
            source,
            update(translation, sourceTagMap(source)),
          ]),
        ),
      }),
    ).toStrictEqual(golden)
  })
})
