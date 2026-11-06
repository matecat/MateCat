import {EditorState, SelectionState} from 'draft-js'
import resolveDrop from './resolveDrop'
import getSegmentSlice from './getSegmentSlice'
import encodeContent from './encodeContent'
import decodeSegment from './decodeSegment'
import {segmentClipboardHtml} from '../../../segmentView/segmentModel'
import {initTagSignature, setTagSignatureMiddleware} from './tagModel'

// Drops through the real Draft conversion. A drag carries the same raw
// segment slice as a copy (see getSegmentSlice), from the source or from the
// target itself.

beforeAll(() => initTagSignature({show_whitespace: 0}))
afterAll(() => setTagSignatureMiddleware('space', () => undefined))

const editorWith = (segment) =>
  encodeContent(EditorState.createEmpty(), segment).editorState

const firstBlock = (editorState) =>
  editorState.getCurrentContent().getFirstBlock()

const range = (editorState, start, end, {backward = false} = {}) =>
  SelectionState.createEmpty(firstBlock(editorState).getKey()).merge({
    anchorOffset: backward ? end : start,
    focusOffset: backward ? start : end,
    isBackward: backward,
  })

const select = (editorState, start, end, options) =>
  EditorState.forceSelection(
    editorState,
    range(editorState, start, end, options),
  )

const tagRange = (editorState) => {
  let found
  firstBlock(editorState).findEntityRanges(
    (character) => character.getEntity() !== null,
    (start, end) => (found ??= {start, end}),
  )
  return found
}

const offsetOf = (editorState, text) =>
  firstBlock(editorState).getText().indexOf(text)

const sliceHtml = (editorState) =>
  segmentClipboardHtml(getSegmentSlice(editorState))

const decoded = (editorState) => decodeSegment(editorState).decodedSegment

describe('a drag from the source', () => {
  const source = () => {
    const editorState = editorWith('ab<x id="1"/>cd')
    return select(editorState, 0, tagRange(editorState).end)
  }

  test('is inserted at the drop point with its tags', () => {
    const target = editorWith('hello world')
    const at = offsetOf(target, ' world')

    const {outcome, editorState} = resolveDrop({
      editorState: target,
      selection: range(target, at, at),
      html: sliceHtml(source()),
      draggingFromEditArea: false,
    })

    expect(outcome).toBe('handled')
    expect(decoded(editorState)).toBe('helloab<x id="1"/> world')
  })

  test('is undone back to the target before the drop', () => {
    const target = editorWith('hello')

    const {editorState} = resolveDrop({
      editorState: target,
      selection: range(target, 5, 5),
      html: sliceHtml(source()),
      draggingFromEditArea: false,
    })

    expect(
      EditorState.undo(editorState).getCurrentContent().getPlainText(),
    ).toBe('hello')
  })

  test('is left to Draft when it carries no slice (text from another page)', () => {
    const target = editorWith('hello')

    const result = resolveDrop({
      editorState: target,
      selection: range(target, 5, 5),
      html: '<b>bold</b>',
      draggingFromEditArea: false,
    })

    expect(result).toEqual({outcome: 'not-handled'})
  })
})

test('nothing may be dropped onto a tag', () => {
  const target = editorWith('ab<x id="1"/>cd')
  const {start} = tagRange(target)

  // Draft puts a drop on a chip at the offset of the tag entity
  const result = resolveDrop({
    editorState: target,
    selection: range(target, start, start),
    html: sliceHtml(select(editorWith('zz'), 0, 2)),
    draggingFromEditArea: false,
  })

  expect(result).toEqual({outcome: 'handled'})
})

describe('a drag within the target', () => {
  const move = (dragged, dropAt) =>
    resolveDrop({
      editorState: dragged,
      selection: range(dragged, dropAt, dropAt),
      html: sliceHtml(dragged),
      draggingFromEditArea: true,
    })

  // Cutting the range out shifts later text left, so a drop further along the
  // same block comes back by the length removed.
  test('moves the range forward, tags included, at the right offset', () => {
    const editorState = editorWith('ab<x id="1"/>cd efgh')
    const dragged = select(editorState, 0, tagRange(editorState).end)

    const result = move(dragged, firstBlock(dragged).getLength())

    expect(result.outcome).toBe('handled')
    expect(result.highlightTags).toBe(true)
    expect(decoded(result.editorState)).toBe('cd efghab<x id="1"/>')
  })

  test('moves the range backward', () => {
    const editorState = editorWith('abc<x id="1"/>xyz')
    const from = offsetOf(editorState, 'xyz')
    const dragged = select(editorState, from, from + 3)

    expect(decoded(move(dragged, 1).editorState)).toBe('axyzbc<x id="1"/>')
  })

  test('moves a backward selection the same way', () => {
    const editorState = editorWith('abc def')
    const dragged = select(editorState, 0, 3, {backward: true})

    expect(decoded(move(dragged, 7).editorState)).toBe(' defabc')
  })

  test('is undone in one step', () => {
    const editorState = editorWith('ab<x id="1"/>cd efgh')
    const dragged = select(editorState, 0, 2)

    const {editorState: moved} = move(dragged, firstBlock(dragged).getLength())

    expect(decoded(EditorState.undo(moved))).toBe('ab<x id="1"/>cd efgh')
  })
})
