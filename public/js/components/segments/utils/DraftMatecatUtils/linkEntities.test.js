import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import linkEntities from './linkEntities'

const applyEntity = (contentState, blockKey, start, end, data) => {
  contentState = contentState.createEntity('TAG', 'IMMUTABLE', data)
  const entityKey = contentState.getLastCreatedEntityKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: start,
    focusOffset: end,
  })
  return {
    contentState: Modifier.applyEntity(contentState, selection, entityKey),
    entityKey,
  }
}

test('links a matching open/close entity pair via openTagKey/closeTagKey', () => {
  let contentState = ContentState.createFromText('hello world')
  const blockKey = contentState.getFirstBlock().getKey()

  const open = applyEntity(contentState, blockKey, 0, 5, {
    closeTagId: 'pair-1',
  })
  contentState = open.contentState

  const close = applyEntity(contentState, blockKey, 6, 11, {
    openTagId: 'pair-1',
  })
  contentState = close.contentState

  const editorState = EditorState.createWithContent(contentState)
  const result = linkEntities(editorState)

  expect(result.getEntity(open.entityKey).getData().closeTagKey).toBe(
    close.entityKey,
  )
  expect(result.getEntity(close.entityKey).getData().openTagKey).toBe(
    open.entityKey,
  )
})

test('leaves entities untouched when there is no matching pair', () => {
  let contentState = ContentState.createFromText('hello world')
  const blockKey = contentState.getFirstBlock().getKey()

  const open = applyEntity(contentState, blockKey, 0, 5, {
    closeTagId: 'orphan',
  })
  contentState = open.contentState

  const editorState = EditorState.createWithContent(contentState)
  const result = linkEntities(editorState)

  expect(result.getEntity(open.entityKey).getData().closeTagKey).toBeUndefined()
})

test('returns the content state unchanged when there are no linkable entities', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('plain text'),
  )

  const result = linkEntities(editorState)

  expect(result).toBe(editorState.getCurrentContent())
})
