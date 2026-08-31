import {List} from 'immutable'
import findRangesImmutable from './findRangesimmutable'

describe('findRangesImmutable', () => {
  test('does nothing for an empty haystack', () => {
    const foundFn = jest.fn()
    findRangesImmutable(List(), () => true, () => true, foundFn)
    expect(foundFn).not.toHaveBeenCalled()
  })

  test('reports contiguous ranges that pass the filter', () => {
    const haystack = List([1, 1, 2, 2, 2, 3])
    const areEqualFn = (a, b) => a === b
    const filterFn = (value) => value !== 2
    const foundFn = jest.fn()

    findRangesImmutable(haystack, areEqualFn, filterFn, foundFn)

    // [1,1] -> (0,2) passes filter; [2,2,2] -> (2,5) filtered out; [3] -> (5,6) passes
    expect(foundFn).toHaveBeenCalledWith(0, 2)
    expect(foundFn).toHaveBeenCalledWith(5, 6)
    expect(foundFn).not.toHaveBeenCalledWith(2, 5)
    expect(foundFn).toHaveBeenCalledTimes(2)
  })

  test('reports a single range spanning the whole list when all elements are equal', () => {
    const haystack = List([1, 1, 1])
    const foundFn = jest.fn()
    findRangesImmutable(haystack, (a, b) => a === b, () => true, foundFn)
    expect(foundFn).toHaveBeenCalledWith(0, 3)
    expect(foundFn).toHaveBeenCalledTimes(1)
  })

  test('does not call foundFn for the trailing range when the filter rejects it', () => {
    const haystack = List([1, 2])
    const foundFn = jest.fn()
    findRangesImmutable(
      haystack,
      (a, b) => a === b,
      (value) => value !== 2,
      foundFn,
    )
    expect(foundFn).toHaveBeenCalledWith(0, 1)
    expect(foundFn).toHaveBeenCalledTimes(1)
  })
})
