import React, {createRef} from 'react'
import {render, act, fireEvent, screen} from '@testing-library/react'
import VirtualList from './VirtualList'

// jsdom lays nothing out: the list gets a 300px viewport, a scroll height equal
// to its rows, and a scrollTo that moves it and fires scroll, as a browser does.
const VIEWPORT = 300
const isList = (el) => el.classList?.contains('virtual-list')
const descriptors = {}
const define = (name, get) => {
  descriptors[name] = Object.getOwnPropertyDescriptor(
    HTMLElement.prototype,
    name,
  )
  Object.defineProperty(HTMLElement.prototype, name, {
    configurable: true,
    get() {
      return isList(this)
        ? get(this)
        : (descriptors[name]?.get?.call(this) ?? 0)
    },
  })
}

beforeAll(() => {
  define('offsetHeight', () => VIEWPORT)
  define('clientHeight', () => VIEWPORT)
  define('offsetWidth', () => 800)
  // eslint-disable-next-line testing-library/no-node-access
  define('scrollHeight', (el) => parseFloat(el.firstChild.style.height))
  const getRect = Element.prototype.getBoundingClientRect
  jest
    .spyOn(Element.prototype, 'getBoundingClientRect')
    .mockImplementation(function () {
      return isList(this)
        ? {top: 0, left: 0, width: 800, height: VIEWPORT, bottom: VIEWPORT}
        : getRect.call(this)
    })
  Element.prototype.scrollTo = function ({top}) {
    this.scrollTop = top
    this.dispatchEvent(new Event('scroll'))
  }
})

afterAll(() => {
  Object.entries(descriptors).forEach(([name, descriptor]) =>
    descriptor
      ? Object.defineProperty(HTMLElement.prototype, name, descriptor)
      : delete HTMLElement.prototype[name],
  )
  delete Element.prototype.scrollTo
})

const createItems = (count = 100, height = 50) =>
  Array.from({length: count}, (_, i) => ({id: `item-${i}`, height}))

const renderList = (props = {}) => {
  const ref = createRef()
  const allProps = {
    items: createItems(),
    overscan: 2,
    onRender: (index) => <span>{`row ${index}`}</span>,
    setFirstRowIdVisible: jest.fn(),
    renderedRange: jest.fn(),
    ...props,
  }
  const result = render(<VirtualList {...allProps} ref={ref} />)
  const rerender = (next) =>
    result.rerender(<VirtualList {...allProps} {...next} ref={ref} />)
  return {...result, rerender, ref, props: allProps}
}

const scrollListTo = (ref, top) =>
  act(() => {
    ref.current.scrollTop = top
    fireEvent.scroll(ref.current)
  })

const rowStyle = (index) =>
  // eslint-disable-next-line testing-library/no-node-access
  screen.getByText(`row ${index}`).parentElement.style

const lastRange = (renderedRange) => renderedRange.mock.calls.at(-1)[0]

describe('VirtualList', () => {
  test('is the element the stylesheets target, by its class name', () => {
    const {ref, rerender} = renderList()
    expect(ref.current).toHaveClass('virtual-list')
    rerender({className: 'my-list'})
    expect(ref.current).toHaveClass('my-list')
  })

  test('renders the header given to it above the rows', () => {
    renderList({overlapHeader: <div>Project header</div>})
    expect(screen.getByText('Project header')).toBeInTheDocument()
  })

  test('calls onScroll when the list scrolls', () => {
    const onScroll = jest.fn()
    const {ref} = renderList({onScroll})
    scrollListTo(ref, 100)
    expect(onScroll).toHaveBeenCalled()
  })

  test('renders the visible rows plus the overscan, and reports their indexes', () => {
    const {renderedRange} = renderList().props

    // 300px show rows 0-5; two more below
    expect(lastRange(renderedRange)).toEqual([0, 1, 2, 3, 4, 5, 6, 7])
    expect(screen.getByText('row 7')).toBeInTheDocument()
    expect(screen.queryByText('row 8')).not.toBeInTheDocument()
  })

  test('moves the rendered rows with the scroll', () => {
    const {ref, props} = renderList()

    scrollListTo(ref, 1000)

    // 1000px down rows 20-25 are visible; two more on each side
    expect(lastRange(props.renderedRange)).toEqual([
      18, 19, 20, 21, 22, 23, 24, 25, 26, 27,
    ])
    expect(screen.queryByText('row 17')).not.toBeInTheDocument()
  })

  test('places each row after the heights of the rows above it', () => {
    const items = createItems()
    items[0].height = 120
    renderList({items})

    expect(rowStyle(1).transform).toBe('translateY(120px)')
    expect(rowStyle(2).transform).toBe('translateY(170px)')
    expect(rowStyle(1).height).toBe('50px')
  })

  test('lays the rows out again, in the same render, when a height changes', () => {
    const {rerender} = renderList()
    expect(rowStyle(2).transform).toBe('translateY(100px)')

    const items = createItems()
    items[1].height = 200
    rerender({items})

    expect(rowStyle(1).height).toBe('200px')
    expect(rowStyle(2).transform).toBe('translateY(250px)')
  })

  test('merges the style given for a row', () => {
    renderList({itemStyle: (index) => (index === 3 ? {zIndex: 1} : {})})
    expect(rowStyle(3).zIndex).toBe('1')
    expect(rowStyle(2).zIndex).toBe('')
  })

  test('reports the first visible row, by default the first one not scrolled past', () => {
    const {ref, props} = renderList()
    expect(props.setFirstRowIdVisible).toHaveBeenLastCalledWith('item-0')

    scrollListTo(ref, 120)

    expect(props.setFirstRowIdVisible).toHaveBeenLastCalledWith('item-2')
  })

  test('asks findFirstVisibleRow which row is the first visible one', () => {
    const findFirstVisibleRow = jest.fn(
      (item, scrollOffset) => item.end > scrollOffset + 60,
    )
    const {ref, props} = renderList({findFirstVisibleRow})

    scrollListTo(ref, 100)

    expect(findFirstVisibleRow).toHaveBeenCalledWith(
      expect.objectContaining({index: 3, start: 150, end: 200}),
      100,
    )
    expect(props.setFirstRowIdVisible).toHaveBeenLastCalledWith('item-3')
  })

  describe('scrollToIndex', () => {
    beforeEach(() => jest.useFakeTimers())
    afterEach(() => jest.useRealTimers())

    const settle = () =>
      act(() => {
        jest.advanceTimersByTime(100)
        jest.runOnlyPendingTimers()
        jest.runOnlyPendingTimers()
        jest.runOnlyPendingTimers()
      })

    test('scrolls 100ms after it is asked, not before', () => {
      const {ref} = renderList({scrollToIndex: {value: 10, align: 'start'}})

      act(() => jest.advanceTimersByTime(99))
      expect(ref.current.scrollTop).toBe(0)

      settle()
      expect(ref.current.scrollTop).toBe(500)
    })

    test('stops the offset short of a start-aligned row, for the sticky bar', () => {
      const {ref} = renderList({
        scrollToIndex: {value: 10, align: 'start', offset: 56},
      })
      settle()
      expect(ref.current.scrollTop).toBe(444)
    })

    test('without an align, keeps the offset when it scrolls up to a row', () => {
      const {ref} = renderList({scrollToIndex: {value: 2, offset: 56}})
      scrollListTo(ref, 1000)
      settle()
      // row 2 starts at 100px, above the viewport
      expect(ref.current.scrollTop).toBe(44)
    })

    test('never scrolls above the top of the list', () => {
      const {ref} = renderList({
        scrollToIndex: {value: 0, align: 'start', offset: 56},
      })
      settle()
      expect(ref.current.scrollTop).toBe(0)
    })

    test('ignores the offset when the alignment is not start', () => {
      const {ref} = renderList({
        scrollToIndex: {value: 10, align: 'auto', offset: 56},
      })
      settle()
      // row 10 is below the viewport: auto aligns its end with the bottom
      expect(ref.current.scrollTop).toBe(550 - VIEWPORT)
    })

    test('reaches a row asked for before the rows arrived', () => {
      const {ref, rerender} = renderList({
        items: [],
        scrollToIndex: {value: 10, align: 'start'},
      })
      settle()
      expect(ref.current.scrollTop).toBe(0)

      rerender({items: createItems()})
      settle()

      expect(ref.current.scrollTop).toBe(500)
    })
  })
})
