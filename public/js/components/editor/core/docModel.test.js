import {EditorState as DraftEditorState} from 'draft-js'
import {undo, redo} from '@codemirror/commands'
import encodeContent from '../../segments/utils/DraftMatecatUtils/encodeContent'
import decodeSegment from '../../segments/utils/DraftMatecatUtils/decodeSegment'
import {markableText} from '../../segmentView/segmentModel'
import {SEGMENTS} from '../e2e/fixtures'
import corpus from './__fixtures__/roundTripCorpus.json'
import {createSegmentState} from './createSegmentEditor'
import {
  insertRaw,
  locateTags,
  selectionText,
  serialize,
  tagList,
  toDoc,
  visibleDoc,
} from './docModel'

// What Draft saves today for a segment it loaded: the reference for the round trip.
const draftRoundTrip = (segment) =>
  decodeSegment(
    encodeContent(DraftEditorState.createEmpty(), segment).editorState,
  ).decodedSegment

const roundTrip = (segment) => serialize(createSegmentState(segment))

const CORPUS = [
  ...corpus.map(({segment}) => segment),
  ...Object.values(SEGMENTS).flatMap(({source, target}) => [source, target]),
]

// Inputs on which CM6 deliberately saves something other than Draft; each case has its own test below.
const KNOWN_DIFFERENCE =
  /[\t\u00a0\r\u200b]|##\$_0D\$##|&(?!(?:amp|lt|gt);)#?[a-z0-9]+;|&lt;\/?(?:g|x|bx|ex|ph)\b/i

const update = (state, spec) => state.update(spec).state

beforeEach(() => {
  global.config.lfPlaceholder = '##$_0A$##'
})

describe('round trip', () => {
  test('the corpus covers the fixtures it is collected from', () => {
    expect(CORPUS.length).toBeGreaterThan(100)
  })

  test.each(CORPUS.filter((s) => !KNOWN_DIFFERENCE.test(s)))(
    'saves what Draft saves: %j',
    (segment) => {
      expect(roundTrip(segment)).toBe(draftRoundTrip(segment))
    },
  )

  test.each(CORPUS)(
    'a saved segment loads and saves unchanged: %j',
    (segment) => {
      const saved = roundTrip(segment)
      expect(roundTrip(saved)).toBe(saved)
    },
  )

  test('only the inputs with a known difference are left out of the Draft comparison', () => {
    const differing = CORPUS.filter((s) => roundTrip(s) !== draftRoundTrip(s))
    expect(differing.every((s) => KNOWN_DIFFERENCE.test(s))).toBe(true)
  })
})

describe('round trip: where Draft is wrong or the model decides otherwise', () => {
  test('encoded tag markup stays text; Draft turns it into a tag', () => {
    const segment = 'a &lt;x id="9"/&gt; b'
    expect(draftRoundTrip(segment)).toBe('a <x id="9"/> b')
    expect(roundTrip(segment)).toBe(segment)
  })

  test('entities other than &amp; &lt; &gt; are decoded; Draft encodes them again', () => {
    expect(draftRoundTrip('it&apos;s &quot;ok&quot;')).toBe(
      'it&amp;apos;s &amp;quot;ok&amp;quot;',
    )
    expect(roundTrip('it&apos;s &quot;ok&quot;')).toBe('it\'s "ok"')
  })

  test('an nbsp entity or character becomes the nbsp placeholder', () => {
    expect(roundTrip('a&nbsp;b\u00a0c')).toBe('a##$_A0$##b##$_A0$##c')
  })

  test('a tab character becomes the tab placeholder', () => {
    expect(roundTrip('a\tb')).toBe('a##$_09$##b')
  })

  test('a carriage return stays one; Draft turns it into a line feed', () => {
    expect(draftRoundTrip('a##$_0D$##b')).toBe('a##$_0A$##b')
    expect(roundTrip('a##$_0D$##b')).toBe('a##$_0D$##b')
    expect(roundTrip('a##$_0D$####$_0A$##b')).toBe('a##$_0D$####$_0A$##b')
    expect(roundTrip('a\rb')).toBe('a##$_0D$##b')
  })

  test('a zero-width space in the text is kept; Draft deletes it', () => {
    expect(draftRoundTrip('a\u200bb')).toBe('ab')
    expect(roundTrip('a\u200bb')).toBe('a\u200bb')
  })
})

describe('toDoc', () => {
  test('the document is the decoded text, with line feed, nbsp and tab as characters', () => {
    const {text, tags} = toDoc('a &amp; b##$_0A$##c##$_A0$##d##$_09$##e')
    expect(text).toBe('a & b\nc\u00a0d\te')
    expect(tags).toEqual([])
  })

  test('tags keep their markup and are recorded as ranges', () => {
    const {text, tags} = toDoc('<g id="1">Hi</g>##$_0D$##<x id="2"/>')
    expect(text).toBe('<g id="1">Hi</g>##$_0D$##<x id="2"/>')
    expect(tags.map((r) => text.slice(r.from, r.to))).toEqual([
      '<g id="1">',
      '</g>',
      '##$_0D$##',
      '<x id="2"/>',
    ])
  })

  test('XLIFF markup tokenize does not know is kept as a tag, as Draft keeps it', () => {
    const segment = 'a <ph id="1"/><bpt id="2">b</bpt>'
    const {text, tags} = toDoc(segment)
    expect(tags.map((r) => text.slice(r.from, r.to))).toEqual([
      '<ph id="1"/>',
      '<bpt id="2">',
      '</bpt>',
    ])
    expect(roundTrip(segment)).toBe(draftRoundTrip(segment))
  })

  test('spaces are text even where the space signature is on', () => {
    expect(toDoc('a b').tags).toEqual([])
  })

  test('an empty or missing segment is an empty document', () => {
    expect(toDoc('')).toEqual({text: '', tags: []})
    expect(toDoc(null)).toEqual({text: '', tags: []})
  })
})

describe('tag ranges', () => {
  test('typed markup stays text and is saved encoded', () => {
    const state = createSegmentState('a')
    const next = update(state, {
      changes: {from: 1, insert: '<x id="9"/>'},
      userEvent: 'input.type',
    })
    expect(tagList(next)).toEqual([])
    expect(serialize(next)).toBe('a&lt;x id="9"/&gt;')
  })

  test('insertRaw makes tags of the markup it inserts', () => {
    const state = createSegmentState('ab')
    const next = update(state, insertRaw(state, 1, 1, '<x id="1"/>&amp;'))
    expect(serialize(next)).toBe('a<x id="1"/>&amp;b')
    expect(tagList(next)).toEqual([{from: 1, to: 12}])
    expect(next.selection.main.head).toBe(13)
  })

  test('text inserted at the edge of a tag stays outside it', () => {
    const state = createSegmentState('<x id="1"/>')
    const next = update(state, {
      changes: [
        {from: 0, insert: 'a'},
        {from: 11, insert: 'b'},
      ],
    })
    expect(tagList(next)).toEqual([{from: 1, to: 12}])
    expect(serialize(next)).toBe('a<x id="1"/>b')
  })

  test('undo brings a deleted tag back as a tag, redo deletes it again', () => {
    let state = createSegmentState('a<x id="1"/>b')
    const view = {
      get state() {
        return state
      },
      dispatch: (tr) => {
        state = tr.state
      },
    }
    state = update(state, {changes: {from: 1, to: 12}, userEvent: 'delete'})
    expect(serialize(state)).toBe('ab')
    undo(view)
    expect(serialize(state)).toBe('a<x id="1"/>b')
    expect(tagList(state)).toEqual([{from: 1, to: 12}])
    redo(view)
    expect(serialize(state)).toBe('ab')
    undo(view)
    // still a tag after a second undo: a typed edit inside would be refused
    expect(tagList(state)).toEqual([{from: 1, to: 12}])
  })
})

describe('visibleDoc', () => {
  // markableText drops markup it does not know (`<strong>`, a `<ph>` without equiv-text); the editor keeps it, as
  // text or as an unknown tag, so offsets differ there.
  const withoutMarkupText = CORPUS.filter(
    (s) =>
      !locateTags(s).some((t) => !t.token) &&
      !/<\/?[A-Za-z][^>]*>/.test(visibleDoc(createSegmentState(s)).text),
  )

  test.each(withoutMarkupText)(
    'its text is the markableText of the saved segment: %j',
    (segment) => {
      const state = createSegmentState(segment)
      const {text, pos} = visibleDoc(state)
      expect(text).toBe(markableText(serialize(state)))
      expect(pos).toHaveLength(text.length)
    },
  )

  test('positions point at the document character, a tag at its start', () => {
    const state = createSegmentState('a<x id="1"/>b##$_0A$##c')
    expect(visibleDoc(state)).toEqual({
      text: 'a\ufffcb\nc',
      pos: [0, 1, 12, 13, 14],
    })
  })
})

describe('selectionText', () => {
  test('gives the selection with its tags and without them', () => {
    const state = createSegmentState('a <g id="1">b &amp; c</g> d##$_A0$##e')
    const doc = state.doc.toString()
    const from = doc.indexOf('<g')
    const to = doc.indexOf('e')
    const selected = update(state, {selection: {anchor: from, head: to}})
    expect(selectionText(selected)).toEqual({
      textWithTags: '<g id="1">b &amp; c</g> d##$_A0$##',
      textWithoutTags: 'b & c d\u00a0',
    })
  })

  test('a selection ending inside a tag takes the whole tag', () => {
    const state = createSegmentState('a<x id="1"/>b')
    const selected = update(state, {selection: {anchor: 0, head: 3}})
    expect(selectionText(selected).textWithTags).toBe('a<x id="1"/>')
  })
})
