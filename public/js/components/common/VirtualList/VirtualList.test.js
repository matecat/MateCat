import React, {createRef} from 'react'
import {render, act, fireEvent} from '@testing-library/react'
import VirtualList from './VirtualList'

// --- Mocks ---

global.ResizeObserver = class ResizeObserver {
  constructor(cb) {
    this.cb = cb
  }
  observe() {}
  unobserve() {}
  disconnect() {}
}

// react-virtual needs a scrollable parent with measurable size
jest.mock('react-virtual', () => ({
  useVirtual: ({size, estimateSize}) => {
    const virtualItems = Array.from({length: size}, (_, i) => ({
      index: i,
      start: i * 50,
      end: (i + 1) * 50,
      size: 50,
      measureRef: jest.fn(),
    }))
    return {
      virtualItems,
      totalSize: size * 50,
      scrollToIndex: jest.fn(),
    }
  },
}))

// --- Helpers ---

const createItems = (count = 5) =>
  Array.from({length: count}, (_, i) => ({
    id: `item-${i}`,
    height: 50,
  }))

const defaultProps = {
  items: createItems(),
  width: 800,
  height: 600,
  onRender: jest.fn(),
  setFirstRowIdVisible: jest.fn(),
  renderedRange: jest.fn(),
  onScroll: jest.fn(),
  itemStyle: jest.fn(() => ({})),
}

const renderVirtualList = (props = {}) => {
  const ref = createRef()
  const result = render(<VirtualList {...defaultProps} {...props} ref={ref} />)
  return {...result, ref}
}

// --- Tests ---

describe('VirtualList', () => {
  afterEach(() => jest.clearAllMocks())

  describe('rendering', () => {
    it('renders without crashing', () => {
      expect(() => renderVirtualList()).not.toThrow()
    })

    it('applies default className', () => {
      const {container} = renderVirtualList()
      expect(container.firstChild).toHaveClass('virtual-list')
    })

    it('applies custom className', () => {
      const {container} = renderVirtualList({className: 'my-list'})
      expect(container.firstChild).toHaveClass('my-list')
    })

    it('renders all virtual items', () => {
      const items = createItems(5)
      const {container} = renderVirtualList({items})
      // each item is rendered inside the virtual container
      const rows = container.querySelectorAll('[style*="position: absolute"]')
      expect(rows.length).toBe(5)
    })

    it('renders header when provided', () => {
      const header = <div data-testid="list-header">Header</div>
      const {getByTestId} = renderVirtualList({header})
      expect(getByTestId('list-header')).toBeInTheDocument()
    })

    it('does not render header when not provided', () => {
      const {queryByTestId} = renderVirtualList({header: undefined})
      expect(queryByTestId('list-header')).not.toBeInTheDocument()
    })

    it('renders empty list without crashing', () => {
      expect(() => renderVirtualList({items: []})).not.toThrow()
    })
  })

  describe('scroll behavior', () => {
    it('calls onScroll when scroll event fires', () => {
      const onScroll = jest.fn()
      const {container} = renderVirtualList({onScroll})

      act(() => {
        fireEvent.scroll(container.firstChild, {target: {scrollTop: 100}})
      })

      expect(onScroll).toHaveBeenCalled()
    })

    it('does not crash when onScroll is not provided', () => {
      const {container} = renderVirtualList({onScroll: undefined})
      expect(() =>
        fireEvent.scroll(container.firstChild, {target: {scrollTop: 100}}),
      ).not.toThrow()
    })
  })

  describe('setFirstRowIdVisible', () => {
    it('calls setFirstRowIdVisible on render', () => {
      const setFirstRowIdVisible = jest.fn()
      renderVirtualList({setFirstRowIdVisible})
      expect(setFirstRowIdVisible).toHaveBeenCalled()
    })

    it('passes correct item id to setFirstRowIdVisible', () => {
      const items = createItems(5)
      const setFirstRowIdVisible = jest.fn()
      renderVirtualList({items, setFirstRowIdVisible})
      // first visible item from mocked virtualItems at scrollTop=0
      expect(setFirstRowIdVisible).toHaveBeenCalledWith(expect.any(String))
    })
  })

  describe('scrollToIndex', () => {
    it('does not crash when scrollToIndex prop is empty object', () => {
      expect(() => renderVirtualList({scrollToIndex: {}})).not.toThrow()
    })

    it('does not crash when scrollToIndex has index and align', () => {
      expect(() =>
        renderVirtualList({scrollToIndex: {index: 2, align: 'start'}}),
      ).not.toThrow()
    })
  })

  describe('onRender', () => {
    it('calls onRender with rendered virtual items', () => {
      const onRender = jest.fn()
      renderVirtualList({onRender})
      expect(onRender).toHaveBeenCalled()
    })
  })

  // ...existing code...
  describe('renderedRange', () => {
    it('calls renderedRange with rendered indexes', () => {
      const renderedRange = jest.fn()
      renderVirtualList({renderedRange})
      expect(renderedRange).toHaveBeenCalledWith(
        expect.arrayContaining([expect.any(Number)]),
      )
    })
  })

  describe('itemStyle', () => {
    it('calls itemStyle for each rendered item', () => {
      const itemStyle = jest.fn(() => ({}))
      const items = createItems(3)
      renderVirtualList({items, itemStyle})
      expect(itemStyle).toHaveBeenCalledTimes(3)
    })

    it('passes item index to itemStyle', () => {
      const itemStyle = jest.fn(() => ({}))
      const items = createItems(3)
      renderVirtualList({items, itemStyle})
      expect(itemStyle).toHaveBeenCalledWith(expect.any(Number))
    })
  })
})
