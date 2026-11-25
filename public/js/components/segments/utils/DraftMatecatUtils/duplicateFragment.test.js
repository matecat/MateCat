import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import duplicateFragment from './duplicateFragment'

describe('duplicateFragment', () => {
  test('clones entities referenced by the fragment into the target editor', () => {
    let contentState = ContentState.createFromText('hello world')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
    const entityKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    const entitySelection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })
    contentState = Modifier.applyEntity(contentState, entitySelection, entityKey)

    const fragment = contentState.getBlockMap()
    let editorState = EditorState.createWithContent(contentState)
    // collapse selection at the end so replaceWithFragment inserts (not deletes)
    editorState = EditorState.moveSelectionToEnd(editorState)

    const result = duplicateFragment(fragment, editorState)

    const resultText = result.getCurrentContent().getPlainText()
    expect(resultText).toBe('hello worldhello world')
  })

  test('accepts a pre-computed entitiesMap instead of deriving one', () => {
    let contentState = ContentState.createFromText('ab')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
    const entityKey = contentState.getLastCreatedEntityKey()
    const entity = contentState.getEntity(entityKey)
    const blockKey = contentState.getFirstBlock().getKey()
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 0,
        focusOffset: 1,
      }),
      entityKey,
    )

    const fragment = contentState.getBlockMap()
    let editorState = EditorState.createWithContent(contentState)
    editorState = EditorState.moveSelectionToEnd(editorState)

    const result = duplicateFragment(fragment, editorState, {
      [entityKey]: entity,
    })

    expect(result.getCurrentContent().getPlainText()).toBe('abab')
  })

  test('duplicates a fragment with no entities', () => {
    const contentState = ContentState.createFromText('xy')
    const fragment = contentState.getBlockMap()
    let editorState = EditorState.createWithContent(contentState)
    editorState = EditorState.moveSelectionToEnd(editorState)

    const result = duplicateFragment(fragment, editorState)
    expect(result.getCurrentContent().getPlainText()).toBe('xyxy')
  })
})
