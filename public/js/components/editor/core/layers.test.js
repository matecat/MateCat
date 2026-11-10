import {createSegmentState} from './createSegmentEditor'
import {
  applyLayers,
  isLayerHidden,
  layerRangesAt,
  layers,
  replaceLayerRange,
  SEARCH_LAYER,
  setLayer,
  visibleToDoc,
} from './layers'
import {EditorView} from '@codemirror/view'
import {createSegmentEditor} from './createSegmentEditor'
import {serialize} from './docModel'

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

describe('volatile layers (Lexiqa: warnings on the text as it was checked)', () => {
  const lexiqaOn = (state, ranges) =>
    state.update({
      effects: setLayer.of({name: 'lexiqa', ranges, volatile: true}),
    }).state
  const edit = (state, changes, userEvent) =>
    state.update({changes, ...(userEvent ? {userEvent} : {})}).state

  test('a user edit touching a warning drops it; the others follow the text', () => {
    let state = createSegmentState('teh cat sat')
    state = lexiqaOn(state, [
      {from: 0, to: 3, className: 'l'},
      {from: 8, to: 11, className: 'l'},
    ])
    state = edit(state, {from: 3, insert: 'n'}, 'input.type')
    expect(shown(state)).toEqual([[9, 12, 'l']])
  })

  test('a programmatic change and an IME composition only map the warnings', () => {
    let state = createSegmentState('teh cat')
    state = lexiqaOn(state, [{from: 0, to: 3, className: 'l'}])
    state = edit(state, {from: 3, insert: 'x'})
    state = edit(state, {from: 4, insert: 'y'}, 'input.type.compose')
    expect(shown(state)).toEqual([[0, 3, 'l']])
  })

  test('a layer that is not volatile is mapped through a user edit', () => {
    let state = createSegmentState('teh cat')
    state = withLayer(state, 'blacklist', [{from: 0, to: 3, className: 'b'}])
    state = edit(state, {from: 1, insert: 'x'}, 'input.type')
    expect(shown(state)).toEqual([[0, 4, 'b']])
  })
})

describe('isLayerHidden', () => {
  test('every layer but search is hidden while search has ranges', () => {
    let state = createSegmentState('one two')
    expect(isLayerHidden(state, 'lexiqa')).toBe(false)
    state = withLayer(state, SEARCH_LAYER, [{from: 0, to: 3, className: 's'}])
    expect(isLayerHidden(state, 'lexiqa')).toBe(true)
    expect(isLayerHidden(state, SEARCH_LAYER)).toBe(false)
  })
})

const mountEditor = (value, options = {}) =>
  createSegmentEditor({
    parent: document.body.appendChild(document.createElement('div')),
    value,
    mode: 'target',
    ...options,
  })

describe('replaceLayerRange (a Lexiqa suggestion, Editarea.replaceWordAt)', () => {
  test('replaces the warned text on its own line of a multi-line target, as one user edit', () => {
    const editor = mountEditor('tow one##$_0A$##two tow<x id="1"/>')
    const second = editor.view.state.doc.toString().lastIndexOf('tow')
    expect(
      replaceLayerRange(editor.view, {from: second, to: second + 3}, 'two'),
    ).toBe(true)
    expect(serialize(editor.view.state)).toBe(
      'tow one##$_0A$##two two<x id="1"/>',
    )
    editor.undo()
    expect(serialize(editor.view.state)).toBe(
      'tow one##$_0A$##two tow<x id="1"/>',
    )
    editor.destroy()
  })

  test('the replacement is text: markup in a suggestion stays text', () => {
    const editor = mountEditor('a tow')
    replaceLayerRange(editor.view, {from: 2, to: 5}, '<b>')
    expect(serialize(editor.view.state)).toBe('a &lt;b&gt;')
    editor.destroy()
  })

  test('a read-only target is left as it is', () => {
    const editor = mountEditor('a tow', {readOnly: true})
    expect(replaceLayerRange(editor.view, {from: 2, to: 5}, 'two')).toBe(false)
    expect(serialize(editor.view.state)).toBe('a tow')
    editor.destroy()
  })
})

describe('applyLayers (no layer redraw during an IME composition)', () => {
  let composing
  beforeEach(() => {
    jest.useFakeTimers()
    composing = jest
      .spyOn(EditorView.prototype, 'composing', 'get')
      .mockReturnValue(false)
  })
  afterEach(() => {
    composing.mockRestore()
    jest.useRealTimers()
  })

  const lexiqaOf = (view) =>
    layerRangesAt(view.state, 1).filter((r) => r.name === 'lexiqa')
  const markFirstWord = (state) => [
    setLayer.of({
      name: 'lexiqa',
      ranges: [
        {from: 0, to: state.doc.toString().indexOf(' '), className: 'l'},
      ],
    }),
  ]

  test('out of a composition the effects are dispatched at once', () => {
    const editor = mountEditor('teh cat')
    expect(applyLayers(editor.view, markFirstWord)).toBe(true)
    expect(lexiqaOf(editor.view)).toHaveLength(1)
    editor.destroy()
  })

  test('during a composition the latest update waits for compositionend and runs on the state of then', () => {
    const editor = mountEditor('teh cat')
    composing.mockReturnValue(true)
    const first = jest.fn(() => [])
    expect(applyLayers(editor.view, first)).toBe(false)
    expect(applyLayers(editor.view, markFirstWord)).toBe(false)
    editor.view.dispatch({changes: {from: 0, insert: 'xx'}})
    expect(lexiqaOf(editor.view)).toEqual([])

    // compositionend can come before the composition is over: it waits for the view to leave it
    editor.view.contentDOM.dispatchEvent(new Event('compositionend'))
    jest.advanceTimersByTime(10)
    expect(lexiqaOf(editor.view)).toEqual([])

    composing.mockReturnValue(false)
    jest.advanceTimersByTime(50)
    expect(first).not.toHaveBeenCalled()
    const [range] = layerRangesAt(editor.view.state, 1)
    expect(editor.view.state.sliceDoc(range.from, range.to)).toBe('xxteh')
    editor.destroy()
  })

  test('an update asked for after the composition replaces the one that was waiting', () => {
    const editor = mountEditor('teh cat')
    composing.mockReturnValue(true)
    const waiting = jest.fn(() => [])
    applyLayers(editor.view, waiting)
    composing.mockReturnValue(false)
    applyLayers(editor.view, markFirstWord)
    editor.view.contentDOM.dispatchEvent(new Event('compositionend'))
    jest.runOnlyPendingTimers()
    expect(waiting).not.toHaveBeenCalled()
    editor.destroy()
  })
})
