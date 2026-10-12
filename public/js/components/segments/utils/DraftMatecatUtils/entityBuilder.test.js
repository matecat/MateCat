import {EditorState} from 'draft-js'
import entityBuilder from './entityBuilder'

describe('entityBuilder', () => {
  test('creates a new entity and returns its key alongside the updated editorState', () => {
    const editorState = EditorState.createEmpty()
    const entityBlueprint = {
      type: 'TAG',
      mutability: 'IMMUTABLE',
      data: {placeholder: 'X', name: 'g'},
    }

    const {editorState: updatedEditorState, entityKey} = entityBuilder(
      editorState,
      entityBlueprint,
    )

    expect(entityKey).toBeDefined()
    const entity = updatedEditorState.getCurrentContent().getEntity(entityKey)
    expect(entity.getType()).toBe('TAG')
    expect(entity.getMutability()).toBe('IMMUTABLE')
    expect(entity.getData()).toEqual({placeholder: 'X', name: 'g'})
  })

  test('creates a second, distinct entity key on a subsequent call', () => {
    const editorState = EditorState.createEmpty()
    const {editorState: firstState, entityKey: firstKey} = entityBuilder(
      editorState,
      {type: 'TAG', mutability: 'MUTABLE', data: {}},
    )
    const {entityKey: secondKey} = entityBuilder(firstState, {
      type: 'TAG',
      mutability: 'MUTABLE',
      data: {},
    })

    expect(secondKey).not.toBe(firstKey)
  })
})
