import {EditorState, ContentState, SelectionState} from 'draft-js'
import insertTag, {getEditorStateWithTag} from './insertTag'

const buildEditorState = (text, anchorOffset, focusOffset) => {
  let editorState = EditorState.createWithContent(
    ContentState.createFromText(text),
  )
  const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset,
    focusOffset,
  })
  return EditorState.forceSelection(editorState, selection)
}

describe('insertTag', () => {
  test('inserts the tag placeholder wrapped in ZWSP characters at the selection', () => {
    const editorState = buildEditorState('hello world', 5, 5)
    const tagSuggestion = {
      type: 'TAG',
      mutability: 'IMMUTABLE',
      data: {placeholder: 'X'},
    }

    const updated = insertTag(tagSuggestion, editorState)

    const text = updated.getCurrentContent().getPlainText()
    expect(text).toBe('hello​X​ world')
  })

  test('replaces the trigger text before inserting the tag', () => {
    const editorState = buildEditorState('type {{', 7, 7)
    const tagSuggestion = {
      type: 'TAG',
      mutability: 'IMMUTABLE',
      data: {placeholder: 'Y'},
    }

    const updated = insertTag(tagSuggestion, editorState, '{{')

    const text = updated.getCurrentContent().getPlainText()
    expect(text).toBe('type ​Y​')
  })
})

describe('getEditorStateWithTag', () => {
  test('creates an entity and applies it to the inserted placeholder', () => {
    const editorState = buildEditorState('hello', 5, 5)
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const selectionState = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 5,
      focusOffset: 5,
    })

    const updated = getEditorStateWithTag({
      tag: {type: 'TAG', mutability: 'IMMUTABLE', data: {placeholder: 'Z'}},
      editorState,
      selectionState,
    })

    const content = updated.getCurrentContent()
    expect(content.getPlainText()).toBe('hello​Z​')
    const block = content.getFirstBlock()
    const entityKey = block.getEntityAt(6)
    expect(entityKey).toBeTruthy()
    expect(content.getEntity(entityKey).getType()).toBe('TAG')
  })
})
