import {renderHook, act} from '@testing-library/react'
import useShowMore from './useShowMore'

describe('useShowMore', () => {
  test('returns all items as visible and no extra items when under the limit', () => {
    const items = [1, 2, 3]
    const {result} = renderHook(() => useShowMore(items, 5))

    expect(result.current.visibleItems).toEqual([1, 2, 3])
    expect(result.current.extraItems).toEqual([])
    expect(result.current.hasMore).toBe(false)
    expect(result.current.isExpanded).toBe(false)
  })

  test('splits visible (first N) from extra items when over the limit', () => {
    const items = [1, 2, 3, 4, 5, 6, 7]
    const {result} = renderHook(() => useShowMore(items, 5))

    expect(result.current.visibleItems).toEqual([1, 2, 3, 4, 5])
    expect(result.current.extraItems).toEqual([6, 7])
    expect(result.current.hasMore).toBe(true)
  })

  test('toggle flips isExpanded without changing the visible/extra split', () => {
    const items = [1, 2, 3, 4, 5, 6, 7]
    const {result} = renderHook(() => useShowMore(items, 5))

    act(() => result.current.toggle())
    expect(result.current.isExpanded).toBe(true)
    expect(result.current.visibleItems).toEqual([1, 2, 3, 4, 5])
    expect(result.current.extraItems).toEqual([6, 7])

    act(() => result.current.toggle())
    expect(result.current.isExpanded).toBe(false)
  })

  test('defaults the limit to 5', () => {
    const items = [1, 2, 3, 4, 5, 6]
    const {result} = renderHook(() => useShowMore(items))

    expect(result.current.visibleItems).toEqual([1, 2, 3, 4, 5])
    expect(result.current.extraItems).toEqual([6])
    expect(result.current.hasMore).toBe(true)
  })
})
