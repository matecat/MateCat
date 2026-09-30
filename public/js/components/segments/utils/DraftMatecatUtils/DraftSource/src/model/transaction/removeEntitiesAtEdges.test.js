import {ContentState, SelectionState, Modifier} from 'draft-js'
import removeEntitiesAtEdges from './removeEntitiesAtEdges'

describe('removeEntitiesAtEdges', () => {
  test('strips an IMMUTABLE entity straddling the selection start edge', () => {
    let contentState = ContentState.createFromText('hello world')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {})
    const entityKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    // entity spans [2, 8): selection starting at 4 lands inside it
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 2,
        focusOffset: 8,
      }),
      entityKey,
    )
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 4,
      focusOffset: 8,
    })

    const result = removeEntitiesAtEdges(contentState, selection)
    const resultBlock = result.getBlockForKey(blockKey)
    // start offset (4) had entity removed for the overlapping range
    expect(resultBlock.getEntityAt(3)).toBeNull()
  })

  test('leaves a MUTABLE entity intact at the edge', () => {
    let contentState = ContentState.createFromText('hello world')
    contentState = contentState.createEntity('TAG', 'MUTABLE', {})
    const entityKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 2,
        focusOffset: 8,
      }),
      entityKey,
    )
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 4,
      focusOffset: 8,
    })

    const result = removeEntitiesAtEdges(contentState, selection)
    const resultBlock = result.getBlockForKey(blockKey)
    expect(resultBlock.getEntityAt(4)).toBe(entityKey)
  })

  test('returns contentState with selectionAfter set when no entity needs trimming', () => {
    const contentState = ContentState.createFromText('plain text')
    const blockKey = contentState.getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })

    const result = removeEntitiesAtEdges(contentState, selection)
    expect(result.getSelectionAfter()).toBe(selection)
  })

  test('handles a collapsed (same-key) selection at start and end', () => {
    let contentState = ContentState.createFromText('hello world')
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {})
    const entityKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 0,
        focusOffset: 5,
      }),
      entityKey,
    )
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 2,
      focusOffset: 2,
    })

    const result = removeEntitiesAtEdges(contentState, selection)
    expect(result).toBeDefined()
  })
})
