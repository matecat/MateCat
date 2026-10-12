import {EditorState, ContentState} from 'draft-js'
import decodeSegment from './decodeSegment'

beforeAll(() => {
  global.config.lfPlaceholder = '\\n'
})

describe('decodeSegment', () => {
  test('returns an empty segment for content with no text', () => {
    const editorState = EditorState.createEmpty()
    const result = decodeSegment(editorState)
    expect(result).toEqual({entities: [], decodedSegment: ''})
  })

  test('returns the plain text unchanged when there are no entities', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    const result = decodeSegment(editorState)
    expect(result.decodedSegment).toBe('hello world')
    expect(result.entitiesRange).toEqual([])
  })

  test('re-encodes an entity back into its encodedText form', () => {
    let contentState = ContentState.createFromText('hello world')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {
      encodedText: '<g id="1">',
    })
    const entityKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    const {SelectionState, Modifier} = require('draft-js')
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 6,
        focusOffset: 11,
      }),
      entityKey,
    )
    const editorState = EditorState.createWithContent(contentState)

    const result = decodeSegment(editorState)
    expect(result.decodedSegment).toBe('hello <g id="1">')
  })
})
