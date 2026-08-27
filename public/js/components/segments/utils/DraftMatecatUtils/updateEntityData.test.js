import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import updateEntityData from './updateEntityData'

const editorStateWithEntity = (text, start, end, name = 'g') => {
  let contentState = ContentState.createFromText(text)
  contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name})
  const entityKey = contentState.getLastCreatedEntityKey()
  const blockKey = contentState.getFirstBlock().getKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: start,
    focusOffset: end,
  })
  contentState = Modifier.applyEntity(contentState, selection, entityKey)
  return {editorState: EditorState.createWithContent(contentState), entityKey}
}

beforeEach(() => {
  jest.spyOn(console, 'log').mockImplementation(() => {})
})

afterEach(() => {
  jest.restoreAllMocks()
})

test('returns the editor state unchanged when there is no text', () => {
  const editorState = EditorState.createEmpty()
  const lastSelection = editorState.getSelection()

  const result = updateEntityData(editorState, [], lastSelection)

  expect(result).toBe(editorState)
})

test('replaces the entity data and text with the placeholder, restoring the selection', () => {
  const {editorState, entityKey} = editorStateWithEntity('hello world', 2, 5)
  const lastSelection = SelectionState.createEmpty(
    editorState.getCurrentContent().getFirstBlock().getKey(),
  ).merge({anchorOffset: 1, focusOffset: 1})

  const tagRange = [{offset: 2, data: {placeholder: 'X', name: 'g'}}]

  const result = updateEntityData(editorState, tagRange, lastSelection)

  expect(result.getCurrentContent().getPlainText()).toBe('heX world')
  expect(result.getSelection().getAnchorOffset()).toBe(1)
  expect(result.getSelection().getFocusOffset()).toBe(1)

  const entityInBlock = result
    .getCurrentContent()
    .getFirstBlock()
    .getEntityAt(2)
  expect(result.getCurrentContent().getEntity(entityInBlock).getData()).toEqual(
    expect.objectContaining({placeholder: 'X', name: 'g'}),
  )
})

test('sorts multiple entities and tag ranges before applying replacements', () => {
  let contentState = ContentState.createFromText('hello world')
  const blockKey = contentState.getFirstBlock().getKey()

  contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
  const firstKey = contentState.getLastCreatedEntityKey()
  contentState = Modifier.applyEntity(
    contentState,
    SelectionState.createEmpty(blockKey).merge({anchorOffset: 0, focusOffset: 2}),
    firstKey,
  )

  contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
  const secondKey = contentState.getLastCreatedEntityKey()
  contentState = Modifier.applyEntity(
    contentState,
    SelectionState.createEmpty(blockKey).merge({anchorOffset: 6, focusOffset: 8}),
    secondKey,
  )

  const editorState = EditorState.createWithContent(contentState)
  const lastSelection = editorState.getSelection()
  const tagRange = [
    {offset: 6, data: {placeholder: 'Y'}},
    {offset: 0, data: {placeholder: 'X'}},
  ]

  const result = updateEntityData(editorState, tagRange, lastSelection)

  expect(result.getCurrentContent().getPlainText()).toBe('Xllo Yrld')
})

test('uses explicitly provided entities instead of recomputing them', () => {
  const {editorState, entityKey} = editorStateWithEntity('hello world', 2, 5)
  const lastSelection = editorState.getSelection()
  const providedEntities = [
    {entityKey, blockKey: editorState.getCurrentContent().getFirstBlock().getKey(), start: 2, end: 5},
  ]
  const tagRange = [{offset: 2, data: {placeholder: 'Y'}}]

  const result = updateEntityData(
    editorState,
    tagRange,
    lastSelection,
    providedEntities,
  )

  expect(result.getCurrentContent().getPlainText()).toBe('heY world')
})
