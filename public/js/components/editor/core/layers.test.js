import {createSegmentState} from './createSegmentEditor'
import {
  layerRangesAt,
  layers,
  SEARCH_LAYER,
  setLayer,
  visibleToDoc,
} from './layers'
import {EditorView} from '@codemirror/view'

const withLayer = (state, name, ranges) =>
  state.update({effects: setLayer.of({name, ranges})}).state

// What the view would draw: the decoration ranges and their classes.
const shown = (state) => {
  const out = []
  state.facet(EditorView.decorations).forEach((source) => {
    const set = typeof source === 'function' ? null : source
    if (!set || !set.between) return
    set.between(0, state.doc.length, (from, to, deco) => {
      if (deco.spec.class) out.push([from, to, deco.spec.class])
    })
  })
  return out.sort((a, b) => a[0] - b[0] || a[1] - b[1])
}

describe('layers (activateSearch / activateGlossary exclusivity)', () => {
  test('ranges of several layers are shown together, overlaps included', () => {
    let state = createSegmentState('one two three')
    state = withLayer(state, 'glossary', [{from: 0, to: 7, className: 'g'}])
    state = withLayer(state, 'lexiqa', [{from: 4, to: 13, className: 'l'}])
    expect(shown(state)).toEqual([
      [0, 7, 'g'],
      [4, 13, 'l'],
    ])
  })

  test('while search has ranges only search is shown; emptying it brings the others back', () => {
    let state = createSegmentState('one two three')
    state = withLayer(state, 'glossary', [{from: 0, to: 3, className: 'g'}])
    state = withLayer(state, SEARCH_LAYER, [{from: 4, to: 7, className: 's'}])
    expect(shown(state)).toEqual([[4, 7, 's']])
    state = withLayer(state, SEARCH_LAYER, [])
    expect(shown(state)).toEqual([[0, 3, 'g']])
  })

  test('setting a layer replaces only that layer; empty ranges are dropped', () => {
    let state = createSegmentState('one two')
    state = withLayer(state, 'glossary', [{from: 0, to: 3, className: 'g'}])
    state = withLayer(state, 'icu', [{from: 4, to: 7, className: 'i'}])
    state = withLayer(state, 'glossary', [
      {from: 4, to: 4, className: 'g'},
      {from: 4, to: 7, className: 'g'},
    ])
    expect(shown(state)).toEqual([
      [4, 7, 'g'],
      [4, 7, 'i'],
    ])
  })

  test('ranges follow the text they mark through an edit', () => {
    let state = createSegmentState('one two')
    state = withLayer(state, 'glossary', [{from: 4, to: 7, className: 'g'}])
    state = state.update({changes: {from: 0, insert: '>> '}}).state
    expect(shown(state)).toEqual([[7, 10, 'g']])
  })

  test('layerRangesAt answers with the data of every layer at a position, hidden ones too', () => {
    let state = createSegmentState('one two')
    state = withLayer(state, 'glossary', [
      {from: 0, to: 3, className: 'g', data: {termId: 7}},
    ])
    state = withLayer(state, SEARCH_LAYER, [{from: 0, to: 7, className: 's'}])
    expect(
      layerRangesAt(state, 2).map(({name, from, to, data}) => [
        name,
        from,
        to,
        data,
      ]),
    ).toEqual([
      ['glossary', 0, 3, {termId: 7}],
      [SEARCH_LAYER, 0, 7, undefined],
    ])
    expect(layerRangesAt(state, 5).map((r) => r.name)).toEqual([SEARCH_LAYER])
  })

  test('the field starts empty', () => {
    expect(createSegmentState('x').field(layers).size).toBe(0)
  })
})

describe('visibleToDoc (offsets of the segment view marks)', () => {
  const segment = 'Click <g id="1">Save</g> now'
  // markable text: 'Click ' + tag + 'Save' + tag + ' now', each tag one character
  test('a mark over text maps to the same text in the document', () => {
    const state = createSegmentState(segment)
    const [range] = visibleToDoc(state, [{start: 7, end: 11, className: 'x'}])
    expect(state.sliceDoc(range.from, range.to)).toBe('Save')
    expect(range.className).toBe('x')
  })

  test('a mark covering a tag covers the whole tag', () => {
    const state = createSegmentState(segment)
    const [range] = visibleToDoc(state, [{start: 6, end: 11}])
    expect(state.sliceDoc(range.from, range.to)).toBe('<g id="1">Save')
  })

  test('a mark to the end of the text ends at the end of the document', () => {
    const state = createSegmentState(segment)
    const [range] = visibleToDoc(state, [{start: 13, end: 16}])
    expect(state.sliceDoc(range.from, range.to)).toBe('now')
    expect(range.to).toBe(state.doc.length)
  })

  test('empty marks and marks past the text are dropped', () => {
    const state = createSegmentState('abc')
    expect(
      visibleToDoc(state, [
        {start: 1, end: 1},
        {start: 9, end: 10},
      ]),
    ).toEqual([])
  })
})
