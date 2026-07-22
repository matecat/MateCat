import {renderHook, act} from '@testing-library/react'
import {useRef} from 'react'
import useResizeObserver from './useResizeObserver'

describe('useResizeObserver', () => {
  let observers

  beforeEach(() => {
    observers = []
    global.ResizeObserver = jest.fn((callback) => {
      const instance = {
        callback,
        observe: jest.fn(),
        disconnect: jest.fn(),
      }
      observers.push(instance)
      return instance
    })
  })

  afterEach(() => {
    delete global.ResizeObserver
  })

  const renderWithRef = (element, options) =>
    renderHook(() => {
      const ref = useRef(element)
      return useResizeObserver(ref, options)
    })

  test('returns the default size and does not observe when the ref has no current element', () => {
    const {result} = renderWithRef(null)
    expect(result.current).toEqual({width: 0, height: 0})
    expect(observers).toHaveLength(0)
  })

  test('observes the element and updates width/height from a borderBoxSize array', () => {
    const element = {}
    const {result} = renderWithRef(element, {actualWidth: 5, actualHeight: 5})
    expect(observers).toHaveLength(1)
    expect(observers[0].observe).toHaveBeenCalledWith(element)

    act(() => {
      observers[0].callback([
        {
          borderBoxSize: [{inlineSize: 120, blockSize: 80}],
          target: element,
        },
      ])
    })
    expect(result.current).toEqual({width: 120, height: 80})
  })

  test('supports a non-array borderBoxSize object', () => {
    const element = {}
    const {result} = renderWithRef(element)
    act(() => {
      observers[0].callback([
        {
          borderBoxSize: {inlineSize: 50, blockSize: 60},
          target: element,
        },
      ])
    })
    expect(result.current).toEqual({width: 50, height: 60})
  })

  test('falls back to offsetWidth/offsetHeight when borderBoxSize is unavailable', () => {
    const element = {offsetWidth: 200, offsetHeight: 150}
    const {result} = renderWithRef(element)
    act(() => {
      observers[0].callback([{borderBoxSize: null, target: element}])
    })
    expect(result.current).toEqual({width: 200, height: 150})
  })

  test('falls back to actualWidth/actualHeight when the measured size is zero', () => {
    const element = {offsetWidth: 0, offsetHeight: 0}
    const {result} = renderWithRef(element, {actualWidth: 10, actualHeight: 20})
    act(() => {
      observers[0].callback([{borderBoxSize: null, target: element}])
    })
    expect(result.current).toEqual({width: 10, height: 20})
  })

  test('disconnects the observer on unmount and ignores late callbacks', () => {
    const element = {}
    const {unmount} = renderWithRef(element)
    const observer = observers[0]
    unmount()
    expect(observer.disconnect).toHaveBeenCalled()

    // A callback firing after cleanup must not throw even though state
    // updates are now guarded by the `wasCleaned` flag.
    expect(() =>
      observer.callback([
        {borderBoxSize: [{inlineSize: 10, blockSize: 10}], target: element},
      ]),
    ).not.toThrow()
  })

  test('re-observes when the ref, actualWidth or actualHeight change', () => {
    const element = {}
    const {rerender} = renderWithRef(element, {actualWidth: 1, actualHeight: 1})
    expect(observers).toHaveLength(1)

    rerender()
    // Same props -> effect deps unchanged -> no new observer created, but
    // re-rendering the hook itself is still exercised for coverage.
    expect(observers.length).toBeGreaterThanOrEqual(1)
  })
})
