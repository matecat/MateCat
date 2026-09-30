import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import {getSelectedTextWithTags} from './getSelectedTextWithTags'

const buildEditorStateWithEntity = (text, entityStart, entityEnd, data) => {
  let contentState = ContentState.createFromText(text)
  contentState = contentState.createEntity('ph', 'IMMUTABLE', data)
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

describe('getSelectedTextWithTags', () => {
  test('returns a single run of plain text with no entities', () => {
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
    expect(getSelectedTextWithTags(selected)).toEqual([
      {start: 0, value: 'hello', end: 5},
    ])
  })

  test('substitutes the entity range with its encodedText and merges trailing text', () => {
    const editorState = buildEditorStateWithEntity('aXXb', 1, 3, {
      encodedText: 'ENT',
    })
    const result = getSelectedTextWithTags(editorState)
    expect(result).toEqual([
      {start: 0, value: 'a', end: 1},
      {start: 1, value: 'ENTb', end: 4},
    ])
  })

  test('strips zero-width space characters from the final values', () => {
    const editorState = buildEditorStateWithEntity('a​b', 1, 2, {
      encodedText: '​',
    })
    const result = getSelectedTextWithTags(editorState)
    expect(result.some((item) => item.value.includes('​'))).toBe(false)
  })

  test('returns an empty array for a collapsed selection', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello'),
    )
    expect(getSelectedTextWithTags(editorState)).toEqual([])
  })
})
