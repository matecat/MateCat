import React, {forwardRef, useCallback, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {useVirtualizer} from '@tanstack/react-virtual'

const VirtualList = forwardRef(
  (
    {
      items,
      overscan = 5,
      className = 'virtual-list',
      width,
      height,
      scrollToIndex = {},
      onRender,
      setFirstRowIdVisible,
      overlapHeader,
      itemStyle = () => ({}),
      onScroll = () => {},
      renderedRange = () => {},
      findFirstVisibleRow,
    },
    ref,
  ) => {
    // A start-aligned scroll to an index stops `offset` pixels short of the
    // row (the sticky bar above the list covers that much); other alignments
    // land exactly on it.
    const scrollPaddingStart =
      !scrollToIndex?.align || scrollToIndex.align === 'start'
        ? (scrollToIndex?.offset ?? 0)
        : 0

    const virtualizer = useVirtualizer({
      count: items.length,
      getScrollElement: () => ref.current,
      estimateSize: useCallback((index) => items[index].height, [items]),
      // a new key function on every change of the items makes the
      // virtualizer lay the rows out again with their new heights
      getItemKey: useCallback((index) => items[index].id ?? index, [items]),
      overscan,
      scrollPaddingStart,
    })
    const virtualItems = virtualizer.getVirtualItems()
    const totalSize = virtualizer.getTotalSize()
    const fnScrollToIndex = virtualizer.scrollToIndex

    const getFirstVisibleIndex = () => {
      const parent = ref.current

      const scrollOffset = parent?.scrollTop ?? 0

      const firstVisible = virtualItems.find((item) =>
        typeof findFirstVisibleRow === 'function'
          ? findFirstVisibleRow(item, scrollOffset)
          : item.end > scrollOffset,
      )

      return firstVisible?.index ?? null
    }

    const firstRowIdVisible = items[getFirstVisibleIndex()]?.id

    const scrollToIndexDebounceTmOut = useRef()

    // scroll to index; asked again when the number of items changes, so a
    // target requested before its rows arrived is still reached
    useEffect(() => {
      if (typeof scrollToIndex?.value !== 'number') return
      clearTimeout(scrollToIndexDebounceTmOut.current)

      if (scrollToIndex.value >= 0) {
        scrollToIndexDebounceTmOut.current = setTimeout(() => {
          fnScrollToIndex(scrollToIndex.value, {align: scrollToIndex?.align})
        }, 100)
      }
    }, [
      scrollToIndex?.value,
      scrollToIndex?.align,
      scrollToIndex?.offset,
      fnScrollToIndex,
      items.length,
    ])

    // rendered indexes
    useEffect(() => {
      renderedRange(virtualItems.map(({index}) => index))
    }, [virtualItems, renderedRange])

    // set inline style of width or height
    useEffect(() => {
      if (!ref?.current) return
      const {current} = ref
      if (width)
        current.style.width = `${width}${typeof width === 'number' ? 'px' : ''}`
      if (height)
        current.style.height = `${height}${
          typeof height === 'number' ? 'px' : ''
        }`
    }, [ref, width, height])

    useEffect(() => {
      setFirstRowIdVisible(firstRowIdVisible)
    }, [firstRowIdVisible, setFirstRowIdVisible])

    return (
      <div
        ref={ref}
        className={className}
        onScroll={() => onScroll()}
        tabIndex="1"
      >
        <div
          style={{
            height: `${totalSize}px`,
            width: '100%',
            position: 'relative',
          }}
        >
          {overlapHeader && overlapHeader}
          {virtualItems.map((item) => (
            <div
              key={items[item.index].id ? items[item.index].id : item.index}
              style={{
                position: 'absolute',
                top: 0,
                left: 0,
                width: '100%',
                height: `${items[item.index].height}px`,
                transform: `translateY(${item.start}px)`,
                ...itemStyle(item.index),
              }}
            >
              {onRender(item.index)}
            </div>
          ))}
        </div>
      </div>
    )
  },
)

VirtualList.propTypes = {
  items: PropTypes.array.isRequired,
  overscan: PropTypes.number,
  className: PropTypes.string,
  width: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  height: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  scrollToIndex: PropTypes.exact({
    value: PropTypes.number,
    align: PropTypes.string,
    offset: PropTypes.number,
  }),
  onRender: PropTypes.func.isRequired,
  setFirstRowIdVisible: PropTypes.func.isRequired,
  overlapHeader: PropTypes.node,
  itemStyle: PropTypes.func,
  onScroll: PropTypes.func,
  renderedRange: PropTypes.func,
  findFirstVisibleRow: PropTypes.func,
}

VirtualList.displayName = 'VirtualList'

export default VirtualList
