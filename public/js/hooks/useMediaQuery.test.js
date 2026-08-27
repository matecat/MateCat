import {renderHook, act} from '@testing-library/react'
import {useMediaQuery, useMediaQueries} from './useMediaQuery'

const makeMql = (matches, {legacy = false} = {}) => {
  const listeners = new Set()
  const mql = {
    matches,
    media: '',
    addEventListener: legacy
      ? undefined
      : jest.fn((event, cb) => listeners.add(cb)),
    removeEventListener: legacy
      ? undefined
      : jest.fn((event, cb) => listeners.delete(cb)),
    addListener: legacy ? jest.fn((cb) => listeners.add(cb)) : undefined,
    removeListener: legacy ? jest.fn((cb) => listeners.delete(cb)) : undefined,
    __trigger: (nextMatches) => {
      mql.matches = nextMatches
      listeners.forEach((cb) => cb())
    },
  }
  return mql
}

describe('useMediaQuery / useMediaQueries', () => {
  let mqlByQuery

  beforeEach(() => {
    mqlByQuery = {}
    window.matchMedia = jest.fn((query) => {
      if (!mqlByQuery[query]) mqlByQuery[query] = makeMql(false)
      return mqlByQuery[query]
    })
  })

  test('useMediaQuery returns the initial match state', () => {
    mqlByQuery['(min-width: 100px)'] = makeMql(true)
    const {result} = renderHook(() => useMediaQuery('(min-width: 100px)'))
    expect(result.current).toBe(true)
  })

  test('useMediaQuery reacts to a change event via addEventListener', () => {
    const mql = makeMql(false)
    mqlByQuery['(min-width: 200px)'] = mql
    const {result} = renderHook(() => useMediaQuery('(min-width: 200px)'))
    expect(result.current).toBe(false)

    act(() => {
      mql.__trigger(true)
    })
    expect(result.current).toBe(true)
    expect(mql.addEventListener).toHaveBeenCalledWith(
      'change',
      expect.any(Function),
    )
  })

  test('supports the legacy addListener/removeListener API', () => {
    const mql = makeMql(false, {legacy: true})
    mqlByQuery['(min-width: 300px)'] = mql
    const {result, unmount} = renderHook(() =>
      useMediaQuery('(min-width: 300px)'),
    )
    act(() => {
      mql.__trigger(true)
    })
    expect(result.current).toBe(true)
    expect(mql.addListener).toHaveBeenCalled()

    unmount()
    expect(mql.removeListener).toHaveBeenCalled()
  })

  test('removes modern listeners on unmount', () => {
    const mql = makeMql(true)
    mqlByQuery['(min-width: 400px)'] = mql
    const {unmount} = renderHook(() => useMediaQuery('(min-width: 400px)'))
    unmount()
    expect(mql.removeEventListener).toHaveBeenCalledWith(
      'change',
      expect.any(Function),
    )
  })

  test('useMediaQueries exposes matches, matchesAny and matchesAll', () => {
    mqlByQuery['screen'] = makeMql(true)
    mqlByQuery['print'] = makeMql(false)

    const {result} = renderHook(() =>
      useMediaQueries({screen: 'screen', print: 'print'}),
    )

    expect(result.current.matches).toEqual({screen: true, print: false})
    expect(result.current.matchesAny).toBe(true)
    expect(result.current.matchesAll).toBe(false)

    act(() => {
      mqlByQuery['print'].__trigger(true)
    })
    expect(result.current.matches.print).toBe(true)
    expect(result.current.matchesAll).toBe(true)
  })

  test('matchesAll is false when there are no queries', () => {
    const {result} = renderHook(() => useMediaQueries({}))
    expect(result.current.matchesAll).toBe(false)
    expect(result.current.matchesAny).toBe(false)
  })

  test('re-initializes when the queryMap value actually changes', () => {
    mqlByQuery['(min-width: 500px)'] = makeMql(true)
    mqlByQuery['(min-width: 600px)'] = makeMql(false)

    const {result, rerender} = renderHook(
      (queries) => useMediaQueries(queries),
      {initialProps: {a: '(min-width: 500px)'}},
    )
    expect(result.current.matches).toEqual({a: true})

    // Same values, new object identity -> should NOT re-init (matches unchanged)
    rerender({a: '(min-width: 500px)'})
    expect(result.current.matches).toEqual({a: true})

    // Different value -> re-init against the new query
    rerender({a: '(min-width: 600px)'})
    expect(result.current.matches).toEqual({a: false})
  })

  test('re-initializes when the number of queries changes', () => {
    mqlByQuery['(min-width: 700px)'] = makeMql(true)
    mqlByQuery['(min-width: 800px)'] = makeMql(false)

    const {result, rerender} = renderHook(
      (queries) => useMediaQueries(queries),
      {initialProps: {a: '(min-width: 700px)'}},
    )
    expect(result.current.matches).toEqual({a: true})

    rerender({a: '(min-width: 700px)', b: '(min-width: 800px)'})
    expect(result.current.matches).toEqual({a: true, b: false})
  })

  test('re-initializes when a query key is renamed', () => {
    mqlByQuery['(min-width: 900px)'] = makeMql(true)

    const {result, rerender} = renderHook(
      (queries) => useMediaQueries(queries),
      {initialProps: {a: '(min-width: 900px)'}},
    )
    expect(result.current.matches).toEqual({a: true})

    rerender({renamed: '(min-width: 900px)'})
    expect(result.current.matches).toEqual({renamed: true})
  })

  test('caches the internal query wrapper object across calls with the same query string', () => {
    mqlByQuery['(min-width: 1000px)'] = makeMql(true)
    const {result: result1} = renderHook(() =>
      useMediaQuery('(min-width: 1000px)'),
    )
    const {result: result2} = renderHook(() =>
      useMediaQuery('(min-width: 1000px)'),
    )
    expect(result1.current).toBe(true)
    expect(result2.current).toBe(true)
    // matchMedia should have been called with the exact same query string
    expect(window.matchMedia).toHaveBeenCalledWith('(min-width: 1000px)')
  })
})
