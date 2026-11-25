import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import getEntities from './getEntities'

const buildEditorStateWithEntity = (text, start, end, name) => {
  let contentState = ContentState.createFromText(text)
  contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name})
  const entityKey = contentState.getLastCreatedEntityKey()
  const blockKey = contentState.getFirstBlock().getKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: start,
    focusOffset: end,
  })
  contentState = Modifier.applyEntity(contentState, selection, entityKey)
  return EditorState.createWithContent(contentState)
}

describe('getEntities', () => {
  test('returns an empty array when there are no entities', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('plain text'),
    )
    expect(getEntities(editorState)).toEqual([])
  })

  test('finds an entity and reports its block-based start/end offsets', () => {
    const editorState = buildEditorStateWithEntity('hello world', 6, 11, 'g')
    const entities = getEntities(editorState)
    expect(entities).toHaveLength(1)
    expect(entities[0].start).toBe(6)
    expect(entities[0].end).toBe(11)
    expect(entities[0].entity.getData()).toEqual({name: 'g'})
  })

  test('filters entities by name when entityName is provided', () => {
    const editorState = buildEditorStateWithEntity('hello world', 0, 5, 'g')
    expect(getEntities(editorState, 'g')).toHaveLength(1)
    expect(getEntities(editorState, 'ph')).toHaveLength(0)
  })
})
