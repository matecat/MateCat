import {EditorState, ContentState, SelectionState} from 'draft-js'
import insertEntityAtSelection from './insertEntityAtSelection'

describe('insertEntityAtSelection', () => {
  test('inserts the entity placeholder at a collapsed selection', () => {
    const editorState = EditorState.createEmpty()
    const entityBlueprint = {
      type: 'TAG',
      mutability: 'IMMUTABLE',
      data: {placeholder: 'X'},
    }

    const updated = insertEntityAtSelection(editorState, entityBlueprint)

    const content = updated.getCurrentContent()
    expect(content.getPlainText()).toBe('X')
    const block = content.getFirstBlock()
    const entityKey = block.getEntityAt(0)
    expect(entityKey).toBeTruthy()
    expect(content.getEntity(entityKey).getType()).toBe('TAG')
  })

  test('replaces selected text with the entity placeholder for a non-collapsed selection', () => {
    let editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })
    editorState = EditorState.forceSelection(editorState, selection)

    const updated = insertEntityAtSelection(editorState, {
      type: 'TAG',
      mutability: 'IMMUTABLE',
      data: {placeholder: 'Y'},
    })

    expect(updated.getCurrentContent().getPlainText()).toBe('Y world')
  })

  test('accepts an explicit selectionState overriding the current selection', () => {
    let editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const explicitSelection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 6,
      focusOffset: 11,
    })

    const updated = insertEntityAtSelection(
      editorState,
      {type: 'TAG', mutability: 'IMMUTABLE', data: {placeholder: 'Z'}},
      explicitSelection,
    )

    expect(updated.getCurrentContent().getPlainText()).toBe('hello Z')
  })
})
