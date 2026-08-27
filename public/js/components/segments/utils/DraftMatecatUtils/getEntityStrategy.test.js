import getEntityStrategy from './getEntityStrategy'

describe('getEntityStrategy', () => {
  test('returns a strategy function', () => {
    const strategy = getEntityStrategy('IMMUTABLE')
    expect(typeof strategy).toBe('function')
  })

  test('filter passed to findEntityRanges returns false when there is no entity', () => {
    const findEntityRanges = jest.fn()
    const contentBlock = {findEntityRanges}
    const contentState = {getEntity: jest.fn()}
    const callback = jest.fn()

    const strategy = getEntityStrategy('IMMUTABLE')
    strategy(contentBlock, callback, contentState)

    const filterFn = findEntityRanges.mock.calls[0][0]
    const character = {getEntity: () => null}
    expect(filterFn(character)).toBe(false)
    expect(contentState.getEntity).not.toHaveBeenCalled()
  })

  test('filter returns true only when entity mutability matches', () => {
    const findEntityRanges = jest.fn()
    const contentBlock = {findEntityRanges}
    const contentState = {
      getEntity: (key) => ({
        getMutability: () => (key === 'immutable-key' ? 'IMMUTABLE' : 'MUTABLE'),
      }),
    }
    const callback = jest.fn()

    const strategy = getEntityStrategy('IMMUTABLE')
    strategy(contentBlock, callback, contentState)

    const filterFn = findEntityRanges.mock.calls[0][0]
    expect(filterFn({getEntity: () => 'immutable-key'})).toBe(true)
    expect(filterFn({getEntity: () => 'mutable-key'})).toBe(false)
  })

  test('passes the callback through to findEntityRanges unchanged', () => {
    const findEntityRanges = jest.fn()
    const contentBlock = {findEntityRanges}
    const contentState = {getEntity: jest.fn()}
    const callback = jest.fn()

    const strategy = getEntityStrategy('MUTABLE')
    strategy(contentBlock, callback, contentState)

    expect(findEntityRanges.mock.calls[0][1]).toBe(callback)
  })
})
