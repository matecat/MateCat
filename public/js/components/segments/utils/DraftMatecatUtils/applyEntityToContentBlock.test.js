import {ContentState} from 'draft-js'
import applyEntityToContentBlock from './applyEntityToContentBlock'

describe('applyEntityToContentBlock', () => {
  test('applies the entity key to every character in the [start, end) range', () => {
    const contentState = ContentState.createFromText('hello world')
    const block = contentState.getFirstBlock()

    const updated = applyEntityToContentBlock(block, 0, 5, 'entity-key-1')

    expect(updated.getEntityAt(0)).toBe('entity-key-1')
    expect(updated.getEntityAt(4)).toBe('entity-key-1')
    expect(updated.getEntityAt(5)).toBeNull()
  })

  test('does not touch characters outside the range', () => {
    const contentState = ContentState.createFromText('hello world')
    const block = contentState.getFirstBlock()

    const updated = applyEntityToContentBlock(block, 6, 11, 'entity-key-2')

    expect(updated.getEntityAt(0)).toBeNull()
    expect(updated.getEntityAt(6)).toBe('entity-key-2')
    expect(updated.getEntityAt(10)).toBe('entity-key-2')
  })

  test('returns the block unchanged when start === end', () => {
    const contentState = ContentState.createFromText('hello')
    const block = contentState.getFirstBlock()

    const updated = applyEntityToContentBlock(block, 2, 2, 'entity-key-3')

    expect(updated.getEntityAt(2)).toBeNull()
  })
})
