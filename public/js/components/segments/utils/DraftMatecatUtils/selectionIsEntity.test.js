import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import selectionIsEntity from './selectionIsEntity'

const editorStateWithEntity = (text, start, end) => {
  let contentState = ContentState.createFromText(text)
  contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
  const entityKey = contentState.getLastCreatedEntityKey()
  const blockKey = contentState.getFirstBlock().getKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: start,
    focusOffset: end,
  })
  contentState = Modifier.applyEntity(contentState, selection, entityKey)
  return {
    editorState: EditorState.createWithContent(contentState),
    entityKey,
  }
}

const withSelection = (editorState, options) =>
  EditorState.forceSelection(
    editorState,
    SelectionState.createEmpty(
      editorState.getCurrentContent().getFirstBlock().getKey(),
    ).merge(options),
  )

test('returns null values when anchor and focus are on different blocks', () => {
  let contentState = ContentState.createFromText('foo\nbar')
  const [firstBlock, secondBlock] = contentState.getBlocksAsArray()
  const selection = new SelectionState({
    anchorKey: firstBlock.getKey(),
    anchorOffset: 1,
    focusKey: secondBlock.getKey(),
    focusOffset: 1,
  })
  const editorState = EditorState.forceSelection(
    EditorState.createWithContent(contentState),
    selection,
  )

  expect(selectionIsEntity(editorState)).toEqual({
    entityKey: null,
    onEdge: null,
  })
})

test('flags onEdge when a collapsed caret sits at the start of an entity', () => {
  const {editorState, entityKey} = editorStateWithEntity('hello world', 2, 7)
  const withCaret = withSelection(editorState, {
    anchorOffset: 2,
    focusOffset: 2,
  })

  expect(selectionIsEntity(withCaret)).toEqual({entityKey, onEdge: true})
})

test('does not flag onEdge when a collapsed caret sits in the middle of an entity', () => {
  const {editorState, entityKey} = editorStateWithEntity('hello world', 2, 7)
  const withCaret = withSelection(editorState, {
    anchorOffset: 4,
    focusOffset: 4,
  })

  expect(selectionIsEntity(withCaret)).toEqual({entityKey, onEdge: false})
})

test('does not evaluate edges for a non-collapsed selection', () => {
  const {editorState, entityKey} = editorStateWithEntity('hello world', 2, 7)
  const withRange = withSelection(editorState, {
    anchorOffset: 2,
    focusOffset: 5,
  })

  expect(selectionIsEntity(withRange)).toEqual({entityKey, onEdge: false})
})

test('returns undefined entityKey when the caret is outside any entity', () => {
  const {editorState} = editorStateWithEntity('hello world', 2, 7)
  const withCaret = withSelection(editorState, {
    anchorOffset: 9,
    focusOffset: 9,
  })

  const result = selectionIsEntity(withCaret)
  expect(result.entityKey).toBeNull()
  expect(result.onEdge).toBe(false)
})
