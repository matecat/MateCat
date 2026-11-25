import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import getSelectedTextWithoutEntities from './getSelectedTextWithoutEntities'

const buildEditorStateWithEntity = (text, entityStart, entityEnd, entityType) => {
  let contentState = ContentState.createFromText(text)
  contentState = contentState.createEntity(entityType, 'MUTABLE', {})
  const entityKey = contentState.getLastCreatedEntityKey()
  const blockKey = contentState.getFirstBlock().getKey()
  const entitySelection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: entityStart,
    focusOffset: entityEnd,
  })
  contentState = Modifier.applyEntity(contentState, entitySelection, entityKey)

  let editorState = EditorState.createWithContent(contentState)
  const fullSelection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: 0,
    focusOffset: text.length,
  })
  return EditorState.forceSelection(editorState, fullSelection)
}

describe('getSelectedTextWithoutEntities', () => {
  test('returns a single run for plain text with no entities', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello'),
    )
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const selected = EditorState.forceSelection(
      editorState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 0,
        focusOffset: 5,
      }),
    )
    const result = getSelectedTextWithoutEntities(selected)
    expect(result).toEqual([{start: 0, value: 'hello', end: 5}])
  })

  test('a non-space entity splits the text into separate runs and contributes no value', () => {
    const editorState = buildEditorStateWithEntity('ab#cd', 2, 3, 'TAG')
    const result = getSelectedTextWithoutEntities(editorState)
    expect(result).toEqual([
      {start: 0, value: 'ab', end: 2},
      {start: 3, value: 'cd', end: 5},
    ])
  })

  test('a "space" entity contributes a literal space and merges with adjacent text', () => {
    const editorState = buildEditorStateWithEntity('a b', 1, 2, 'space')
    const result = getSelectedTextWithoutEntities(editorState)
    expect(result).toEqual([
      {start: 0, value: 'a', end: 1},
      {start: 1, value: ' b', end: 3},
    ])
  })

  test('returns an empty array for a collapsed selection', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello'),
    )
    expect(getSelectedTextWithoutEntities(editorState)).toEqual([])
  })
})
