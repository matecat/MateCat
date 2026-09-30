import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import matchTagInEditor from './matchTagInEditor'

const editorStateWithEntity = (text) => {
  let contentState = ContentState.createFromText(text)
  contentState = contentState.createEntity('g', 'IMMUTABLE', {
    id: '1',
    name: 'g',
    originalOffset: 0,
    encodedText: '<g id="1">',
  })
  const entityKey = contentState.getLastCreatedEntityKey()
  const blockKey = contentState.getFirstBlock().getKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: 0,
    focusOffset: 5,
  })
  contentState = Modifier.applyEntity(contentState, selection, entityKey)
  return EditorState.createWithContent(contentState)
}

test('returns an empty array when the editor has no text', () => {
  const editorState = EditorState.createEmpty()

  expect(matchTagInEditor(editorState)).toEqual([])
})

test('discovers entities from the editor state when none are provided', () => {
  const editorState = editorStateWithEntity('hello world')

  const result = matchTagInEditor(editorState)

  expect(result).toHaveLength(1)
  expect(result[0].type).toBe('g')
  expect(result[0].data.id).toBe('1')
})

test('uses the explicitly provided entities instead of recomputing them', () => {
  const editorState = editorStateWithEntity('hello world')
  const providedEntity = {
    entity: editorState.getCurrentContent().getFirstBlock().getEntityAt(0),
  }
  const providedEntityInstance = editorState
    .getCurrentContent()
    .getEntity(providedEntity.entity)

  const result = matchTagInEditor(editorState, [
    {entity: providedEntityInstance},
  ])

  expect(result).toHaveLength(1)
  expect(result[0].data.id).toBe('1')
})

test('returns an empty array when there are no entities to match', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('plain text'),
  )

  expect(matchTagInEditor(editorState)).toEqual([])
})
