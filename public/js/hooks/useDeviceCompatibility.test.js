import {renderHook} from '@testing-library/react'
import useDeviceCompatibility from './useDeviceCompatibility'
import {useMediaQuery} from './useMediaQuery'

jest.mock('./useMediaQuery', () => ({
  useMediaQuery: jest.fn(),
}))

describe('useDeviceCompatibility', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('is compatible when the device has a fine pointer', () => {
    useMediaQuery.mockImplementation((query) => query === '(pointer:fine)')
    const {result} = renderHook(() => useDeviceCompatibility())
    expect(result.current).toBe(true)
  })

  test('is compatible when any pointer is fine, even if the primary is not', () => {
    useMediaQuery.mockImplementation((query) => query === '(any-pointer:fine)')
    const {result} = renderHook(() => useDeviceCompatibility())
    expect(result.current).toBe(true)
  })

  test('is compatible when the device is not mobile/tablet sized, regardless of pointer', () => {
    useMediaQuery.mockImplementation(
      (query) => query === '(min-device-width:1024px)',
    )
    const {result} = renderHook(() => useDeviceCompatibility())
    expect(result.current).toBe(true)
  })

  test('is not compatible when no signal matches', () => {
    useMediaQuery.mockImplementation(() => false)
    const {result} = renderHook(() => useDeviceCompatibility())
    expect(result.current).toBe(false)
  })
})
