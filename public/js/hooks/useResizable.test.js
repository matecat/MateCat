import {renderHook, act} from '@testing-library/react'
import useResizable from './useResizable'

const mouseMove = (clientY) => {
  const event = new Event('mousemove')
  event.clientY = clientY
  window.dispatchEvent(event)
}

const mouseUp = () => {
  window.dispatchEvent(new Event('mouseup'))
}

describe('useResizable', () => {
  afterEach(() => {
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  })

  test('returns the initial height and dragging state', () => {
    const {result} = renderHook(() => useResizable({}))
    expect(result.current.height).toBe(500)
    expect(result.current.isDragging).toBe(false)
  })

  test('respects a custom initialHeight', () => {
    const {result} = renderHook(() => useResizable({initialHeight: 250}))
    expect(result.current.height).toBe(250)
  })

  test('handleMouseDown starts dragging and applies body styles', () => {
    const {result} = renderHook(() => useResizable({initialHeight: 300}))
    const preventDefault = jest.fn()

    act(() => {
      result.current.handleMouseDown({clientY: 100, preventDefault})
    })

    expect(preventDefault).toHaveBeenCalled()
    expect(result.current.isDragging).toBe(true)
    expect(document.body.style.cursor).toBe('row-resize')
    expect(document.body.style.userSelect).toBe('none')
  })

  test('dragging upward increases height, clamped to minHeight/maxHeight', () => {
    const {result} = renderHook(() =>
      useResizable({initialHeight: 300, minHeight: 100, maxHeight: 400}),
    )

    act(() => {
      result.current.handleMouseDown({clientY: 200, preventDefault: jest.fn()})
    })

    // dragging up (mouse moves to a smaller clientY) increases height
    act(() => {
      mouseMove(150)
    })
    expect(result.current.height).toBe(350)

    // clamp against maxHeight
    act(() => {
      mouseMove(-1000)
    })
    expect(result.current.height).toBe(400)

    // clamp against minHeight (dragging down a lot)
    act(() => {
      mouseMove(5000)
    })
    expect(result.current.height).toBe(100)
  })

  test('mousemove is a no-op when not dragging', () => {
    const {result} = renderHook(() => useResizable({initialHeight: 300}))
    act(() => {
      mouseMove(50)
    })
    expect(result.current.height).toBe(300)
  })

  test('mouseup stops dragging and resets body styles', () => {
    const {result} = renderHook(() => useResizable({initialHeight: 300}))
    act(() => {
      result.current.handleMouseDown({clientY: 200, preventDefault: jest.fn()})
    })
    expect(result.current.isDragging).toBe(true)

    act(() => {
      mouseUp()
    })
    expect(result.current.isDragging).toBe(false)
    expect(document.body.style.cursor).toBe('')
    expect(document.body.style.userSelect).toBe('')
  })

  test('mouseup is a no-op when not dragging', () => {
    const {result} = renderHook(() => useResizable({initialHeight: 300}))
    act(() => {
      mouseUp()
    })
    expect(result.current.isDragging).toBe(false)
  })

  test('falls back to window.innerHeight - 200 when maxHeight is not provided', () => {
    const originalInnerHeight = window.innerHeight
    Object.defineProperty(window, 'innerHeight', {
      configurable: true,
      value: 900,
    })

    const {result} = renderHook(() =>
      useResizable({initialHeight: 300, minHeight: 50}),
    )
    act(() => {
      result.current.handleMouseDown({clientY: 200, preventDefault: jest.fn()})
    })
    act(() => {
      mouseMove(-10000)
    })
    // computedMax = 900 - 200 = 700
    expect(result.current.height).toBe(700)

    Object.defineProperty(window, 'innerHeight', {
      configurable: true,
      value: originalInnerHeight,
    })
  })

  test('cleans up window listeners and resets styles when unmounted mid-drag', () => {
    const removeSpy = jest.spyOn(window, 'removeEventListener')
    const {result, unmount} = renderHook(() =>
      useResizable({initialHeight: 300}),
    )
    act(() => {
      result.current.handleMouseDown({clientY: 200, preventDefault: jest.fn()})
    })
    expect(document.body.style.cursor).toBe('row-resize')

    unmount()

    expect(removeSpy).toHaveBeenCalledWith('mousemove', expect.any(Function))
    expect(removeSpy).toHaveBeenCalledWith('mouseup', expect.any(Function))
    expect(document.body.style.cursor).toBe('')
    expect(document.body.style.userSelect).toBe('')
    removeSpy.mockRestore()
  })

  test('re-attaches listeners when minHeight/maxHeight change', () => {
    const addSpy = jest.spyOn(window, 'addEventListener')
    const {rerender} = renderHook((props) => useResizable(props), {
      initialProps: {initialHeight: 300, minHeight: 100},
    })
    const callsBefore = addSpy.mock.calls.length

    rerender({initialHeight: 300, minHeight: 150})
    expect(addSpy.mock.calls.length).toBeGreaterThan(callsBefore)
    addSpy.mockRestore()
  })
})
