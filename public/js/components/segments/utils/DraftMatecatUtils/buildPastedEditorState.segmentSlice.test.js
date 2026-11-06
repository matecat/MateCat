import {EditorState, SelectionState} from 'draft-js'
import {Base64} from 'js-base64'
import buildPastedEditorState from './buildPastedEditorState'
import encodeContent from './encodeContent'
import decodeSegment from './decodeSegment'
import {segmentClipboardHtml} from '../../../segmentView/segmentModel'
import {initTagSignature, setTagSignatureMiddleware} from './tagModel'

// The space placeholder is a user option (show_whitespace), off here.
beforeAll(() => initTagSignature({show_whitespace: 0}))
afterAll(() => setTagSignatureMiddleware('space', () => undefined))

// A paste of a copy made in a SegmentView, through the real Draft conversion.

const editorWith = (segment) => {
  const {editorState} = encodeContent(EditorState.createEmpty(), segment)
  return editorState
}

// Caret at `offset` in the first block.
const caretAt = (editorState, offset) => {
  const key = editorState.getCurrentContent().getFirstBlock().getKey()
  return EditorState.forceSelection(
    editorState,
    SelectionState.createEmpty(key).merge({
      anchorOffset: offset,
      focusOffset: offset,
    }),
  )
}

const paste = (editorState, raw, text = 'visible') =>
  buildPastedEditorState({
    text,
    html: segmentClipboardHtml({raw, text}),
    clipboardFragment: null,
    clipboardPlainText: '',
    editorState,
  })

const decoded = (editorState) => decodeSegment(editorState).decodedSegment

const tagEntities = (editorState) => {
  const names = []
  editorState
    .getCurrentContent()
    .getBlockMap()
    .forEach((block) =>
      block.findEntityRanges(
        (character) => character.getEntity() !== null,
        (start) => {
          const entity = editorState
            .getCurrentContent()
            .getEntity(block.getEntityAt(start))
          names.push(entity.getData().name)
        },
      ),
    )
  return names
}

const ph = (html, id) =>
  `<ph id="${id}" ctype="x-html" equiv-text="base64:${Base64.encode(html)}"/>`

describe('pasting a SegmentView copy into the editor', () => {
  test('inserts its tags as tag entities and the segment reads back the slice', () => {
    const raw = `bold <g id="1">t &amp; c</g>${ph('&lt;br/&gt;', 'p1')}`
    const pasted = paste(caretAt(editorWith('Hi '), 3), raw)

    expect(decoded(pasted)).toBe(`Hi ${raw}`)
    expect(tagEntities(pasted)).toEqual(['g', 'gCl', 'ph'])
  })

  test('inserts at the caret, and whitespace placeholders as their tags', () => {
    const pasted = paste(caretAt(editorWith('ab'), 1), 'x##$_A0$##y##$_09$##')

    expect(decoded(pasted)).toBe('ax##$_A0$##y##$_09$##b')
    expect(tagEntities(pasted)).toEqual(['nbsp', 'tab'])
  })

  test('keeps forged markup as text, never as a tag', () => {
    const pasted = paste(
      caretAt(editorWith(''), 0),
      '<img src=x onerror=alert(1)><x id="1"/>',
    )

    expect(tagEntities(pasted)).toEqual(['x'])
    expect(pasted.getCurrentContent().getPlainText()).toContain(
      '<img src=x onerror=alert(1)>',
    )
  })

  test('is undone back to the text before the paste', () => {
    const before = caretAt(editorWith('Hi'), 2)
    const pasted = paste(before, '<x id="1"/>')

    expect(EditorState.undo(pasted).getCurrentContent().getPlainText()).toBe(
      before.getCurrentContent().getPlainText(),
    )
  })

  test('wins over a stale copy from the editor with the same plain text', () => {
    const pasted = buildPastedEditorState({
      text: 'same',
      html: segmentClipboardHtml({raw: '<x id="9"/>', text: 'same'}),
      clipboardFragment: '{not json',
      clipboardPlainText: 'same',
      editorState: caretAt(editorWith(''), 0),
    })

    expect(decoded(pasted)).toBe('<x id="9"/>')
  })
})

describe('pastes that are not a SegmentView copy', () => {
  test('html from elsewhere goes the external way: tags dropped', () => {
    const pasted = buildPastedEditorState({
      text: 'a<x id="1"/>b',
      html: '<b>a</b>',
      clipboardFragment: null,
      clipboardPlainText: '',
      editorState: caretAt(editorWith(''), 0),
    })

    expect(decoded(pasted)).toBe('ab')
    expect(tagEntities(pasted)).toEqual([])
  })
})
