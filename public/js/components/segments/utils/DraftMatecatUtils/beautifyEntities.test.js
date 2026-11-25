import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import beautifyEntities from './beautifyEntities'

describe('beautifyEntities', () => {
  test('replaces entity text with its placeholder', () => {
    let contentState = ContentState.createFromText('hello <tag> world')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {
      placeholder: '#',
    })
    const entityKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 6,
      focusOffset: 11,
    })
    contentState = Modifier.applyEntity(contentState, selection, entityKey)
    const editorState = EditorState.createWithContent(contentState)

    const result = beautifyEntities(editorState)
    expect(result.getCurrentContent().getPlainText()).toBe('hello # world')
  })

  test('returns the editorState unchanged when there are no entities', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('plain text'),
    )
    const result = beautifyEntities(editorState)
    expect(result.getCurrentContent().getPlainText()).toBe('plain text')
  })

  test('replaces multiple entities in the same block', () => {
    let contentState = ContentState.createFromText('aXbYc')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {
      placeholder: '1',
    })
    const key1 = contentState.getLastCreatedEntityKey()
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {
      placeholder: '2',
    })
    const key2 = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 1,
        focusOffset: 2,
      }),
      key1,
    )
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 3,
        focusOffset: 4,
      }),
      key2,
    )
    const editorState = EditorState.createWithContent(contentState)

    const result = beautifyEntities(editorState)
    expect(result.getCurrentContent().getPlainText()).toBe('a1b2c')
  })
})
