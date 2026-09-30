import canDecorateRange from './canDecorateRange'

const makeContentBlock = (entityKeyAtPosition) => ({
  getEntityAt: () => entityKeyAtPosition,
})

const makeContentState = (tagName) => ({
  getEntity: () => ({
    getData: () => ({name: tagName}),
  }),
})

describe('canDecorateRange', () => {
  test('returns true when no entity is present in the range (lexiqa)', () => {
    const contentBlock = makeContentBlock(null)
    const contentState = makeContentState(null)
    expect(canDecorateRange(0, 3, contentBlock, contentState, 'lexiqa')).toBe(
      true,
    )
  })

  test('returns false when a no-lexiqa tag overlaps the range', () => {
    const contentBlock = makeContentBlock('entity-1')
    const contentState = makeContentState('ph') // ph is not lexiqaAvailable
    expect(canDecorateRange(0, 3, contentBlock, contentState, 'lexiqa')).toBe(
      false,
    )
  })

  test('returns true when a lexiqa-available tag overlaps the range', () => {
    const contentBlock = makeContentBlock('entity-1')
    const contentState = makeContentState('tab') // tab is lexiqaAvailable
    expect(canDecorateRange(0, 3, contentBlock, contentState, 'lexiqa')).toBe(
      true,
    )
  })

  test('returns false when a no-glossary tag overlaps the range', () => {
    const contentBlock = makeContentBlock('entity-1')
    const contentState = makeContentState('ph') // ph is not glossaryAvailable
    expect(canDecorateRange(0, 3, contentBlock, contentState, 'glossary')).toBe(
      false,
    )
  })

  test('returns true for an unknown decorator name (default branch)', () => {
    const contentBlock = makeContentBlock('entity-1')
    const contentState = makeContentState('ph')
    expect(
      canDecorateRange(0, 3, contentBlock, contentState, 'unknown'),
    ).toBe(true)
  })

  test('handles a zero-length range (loop never runs)', () => {
    const contentBlock = makeContentBlock(null)
    const contentState = makeContentState(null)
    expect(canDecorateRange(5, 5, contentBlock, contentState, 'lexiqa')).toBe(
      true,
    )
  })
})
