import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import getEntitiesInFragment from './getEntitiesInFragment'

describe('getEntitiesInFragment', () => {
  test('collects entities referenced by characters in the fragment blocks', () => {
    let contentState = ContentState.createFromText('hello world')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
    const entityKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })
    contentState = Modifier.applyEntity(contentState, selection, entityKey)
    const editorState = EditorState.createWithContent(contentState)

    const fragment = contentState.getBlockMap()
    const entities = getEntitiesInFragment(fragment, editorState)

    expect(Object.keys(entities)).toContain(entityKey)
    expect(entities[entityKey].getData()).toEqual({name: 'g'})
  })

  test('returns an empty object when no characters carry an entity', () => {
    const contentState = ContentState.createFromText('plain text')
    const editorState = EditorState.createWithContent(contentState)
    const entities = getEntitiesInFragment(contentState.getBlockMap(), editorState)
    expect(entities).toEqual({})
  })

  test('swallows a TypeError thrown by a malformed fragment and returns {}', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('x'),
    )
    // `null.forEach` throws a TypeError inside the try block
    expect(getEntitiesInFragment(null, editorState)).toEqual({})
  })
})
