import {EditorState, SelectionState} from 'draft-js'
import {Base64} from 'js-base64'
import getSegmentSlice from './getSegmentSlice'
import buildPastedEditorState from './buildPastedEditorState'
import encodeContent from './encodeContent'
import decodeSegment from './decodeSegment'
import {
  readSegmentClipboard,
  segmentClipboardHtml,
} from '../../../segmentView/segmentModel'
import {initTagSignature, setTagSignatureMiddleware} from './tagModel'

// A copy (or cut, or drag) in the editor through the real Draft conversion:
// what it puts on the clipboard, and what a paste of it gives back.

beforeAll(() => {
  initTagSignature({show_whitespace: 0})
  global.config.lfPlaceholder = '##$_0A$##'
})
afterAll(() => setTagSignatureMiddleware('space', () => undefined))

const editorWith = (segment) =>
  encodeContent(EditorState.createEmpty(), segment).editorState

const blocks = (editorState) =>
  editorState.getCurrentContent().getBlocksAsArray()

// Selects from `start` in the first block to `end` in the last block.
const select = (editorState, start, end, {backward = false} = {}) => {
  const all = blocks(editorState)
  const first = all[0].getKey()
  const last = all[all.length - 1].getKey()
  const range = backward
    ? {anchorKey: last, anchorOffset: end, focusKey: first, focusOffset: start}
    : {anchorKey: first, anchorOffset: start, focusKey: last, focusOffset: end}
  return EditorState.forceSelection(
    editorState,
    SelectionState.createEmpty(first).merge({...range, isBackward: backward}),
  )
}

const selectAll = (editorState) =>
  select(editorState, 0, blocks(editorState).slice(-1)[0].getLength())

// Offsets of the first tag entity in the first block.
const firstTagRange = (editorState) => {
  let range
  blocks(editorState)[0].findEntityRanges(
    (character) => character.getEntity() !== null,
    (start, end) => (range ??= {start, end}),
  )
  return range
}

const caretAt = (editorState, offset) => select(editorState, offset, offset)

const paste = (editorState, slice) =>
  buildPastedEditorState({
    text: slice.text,
    html: segmentClipboardHtml(slice),
    editorState,
  })

const decoded = (editorState) => decodeSegment(editorState).decodedSegment

const ph = (html, id) =>
  `<ph id="${id}" ctype="x-html" equiv-text="base64:${Base64.encode(html)}"/>`

describe('a copy in the editor', () => {
  test('of the whole segment pastes back into an empty target as the same segment string', () => {
    const segment = `Hi <g id="1">t &amp; c</g>${ph('&lt;br/&gt;', 'p1')}##$_A0$##end<x id="2"/>`
    const slice = getSegmentSlice(selectAll(editorWith(segment)))

    expect(readSegmentClipboard(segmentClipboardHtml(slice))).toBe(segment)
    expect(decoded(paste(caretAt(editorWith(''), 0), slice))).toBe(segment)
  })

  test('keeps a line break, across blocks', () => {
    const segment = 'one##$_0A$##two'
    const editorState = editorWith(segment)
    expect(blocks(editorState).length).toBeGreaterThan(1)

    const slice = getSegmentSlice(selectAll(editorState))

    expect(slice.raw).toBe(segment)
    expect(decoded(paste(caretAt(editorWith(''), 0), slice))).toBe(segment)
  })

  test('takes a tag whole when only part of it is selected', () => {
    const editorState = editorWith('ab<x id="1"/>cd')
    const {start} = firstTagRange(editorState)

    const slice = getSegmentSlice(select(editorState, 1, start + 1))

    expect(slice.raw).toBe('b<x id="1"/>')
  })

  test('reads a backward selection as the same range', () => {
    const editorState = editorWith('ab<x id="1"/>cd')
    const forward = getSegmentSlice(select(editorState, 1, 7))
    const backward = getSegmentSlice(
      select(editorState, 1, 7, {backward: true}),
    )

    expect(backward).toEqual(forward)
  })

  test('writes the text as shown for other apps, without zero-width spaces', () => {
    const slice = getSegmentSlice(selectAll(editorWith('a &lt;b&gt; c')))

    expect(slice.text).toBe('a <b> c')
    expect(slice.raw).toBe('a &lt;b&gt; c')
  })

  test('of nothing writes nothing, so the browser copy is left alone', () => {
    expect(getSegmentSlice(caretAt(editorWith('abc'), 1))).toBeNull()
  })
})
