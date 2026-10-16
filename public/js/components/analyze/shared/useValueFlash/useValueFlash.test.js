import {act, renderHook} from '@testing-library/react'
import {useValueFlash} from './useValueFlash'

beforeEach(() => {
  jest.useFakeTimers()
})

afterEach(() => {
  jest.useRealTimers()
})

const renderFlash = (value, enabled = true) =>
  renderHook(({value, enabled}) => useValueFlash(value, {enabled}), {
    initialProps: {value, enabled},
  })

test('does not flash on mount', () => {
  const {result} = renderFlash(10)
  expect(result.current).toBe(false)
})

test('flashes for 400 ms when the value changes', () => {
  const {result, rerender} = renderFlash(10)

  rerender({value: 20, enabled: true})
  expect(result.current).toBe(true)

  act(() => {
    jest.advanceTimersByTime(399)
  })
  expect(result.current).toBe(true)

  act(() => {
    jest.advanceTimersByTime(1)
  })
  expect(result.current).toBe(false)
})

test('restarts the 400 ms when the value changes again mid-flash', () => {
  const {result, rerender} = renderFlash(10)
  rerender({value: 20, enabled: true})
  act(() => {
    jest.advanceTimersByTime(300)
  })
  rerender({value: 30, enabled: true})
  act(() => {
    jest.advanceTimersByTime(300)
  })
  expect(result.current).toBe(true)
  act(() => {
    jest.advanceTimersByTime(100)
  })
  expect(result.current).toBe(false)
})

test('does not flash when the value is the same', () => {
  const {result, rerender} = renderFlash(10)
  rerender({value: 10, enabled: true})
  expect(result.current).toBe(false)
})

test('never flashes when disabled', () => {
  const {result, rerender} = renderFlash(10, false)
  rerender({value: 20, enabled: false})
  expect(result.current).toBe(false)
  rerender({value: 30, enabled: false})
  expect(result.current).toBe(false)
})

test('stops flashing when disabled mid-flash', () => {
  const {result, rerender} = renderFlash(10)
  rerender({value: 20, enabled: true})
  expect(result.current).toBe(true)
  rerender({value: 20, enabled: false})
  expect(result.current).toBe(false)
})

test('clears its timer on unmount', () => {
  const {rerender, unmount} = renderFlash(10)
  rerender({value: 20, enabled: true})
  expect(jest.getTimerCount()).toBe(1)
  unmount()
  expect(jest.getTimerCount()).toBe(0)
})
