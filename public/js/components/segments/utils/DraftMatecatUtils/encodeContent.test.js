import {EditorState} from 'draft-js'
import encodeContent from './encodeContent'
import {initTagSignature} from './tagModel'

// The "space" tag signature is enumerable by default until explicitly turned
// off (it mirrors the "show whitespace" editor setting); disable it so plain
// text round-trips without every space being encoded as a placeholder tag.
beforeAll(() => {
  initTagSignature({show_whitespace: 0})
})

describe('encodeContent', () => {
  test('returns an editorState with the plain text and no tag entities', () => {
    const {editorState, tagRange} = encodeContent(
      EditorState.createEmpty(),
      'hello world',
    )
    expect(editorState.getCurrentContent().getPlainText()).toBe('hello world')
    expect(tagRange).toEqual([])
  })

  test('splits blocks on embedded newlines', () => {
    const {editorState} = encodeContent(
      EditorState.createEmpty(),
      'line one\nline two',
    )
    const blocks = editorState.getCurrentContent().getBlocksAsArray()
    expect(blocks).toHaveLength(2)
    expect(blocks[0].getText()).toBe('line one')
    expect(blocks[1].getText()).toBe('line two')
  })

  test('defaults plainText to an empty string when omitted', () => {
    const {editorState} = encodeContent(EditorState.createEmpty())
    expect(editorState.getCurrentContent().getPlainText()).toBe('')
  })

  test('moves the selection to the end of the content', () => {
    const {editorState} = encodeContent(EditorState.createEmpty(), 'hello')
    const selection = editorState.getSelection()
    expect(selection.getAnchorOffset()).toBe(5)
    expect(selection.getFocusOffset()).toBe(5)
  })
})
