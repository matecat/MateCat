import React, {forwardRef, useCallback, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {useVirtual} from 'react-virtual'
import {st} from 'make-plural'

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
      header,
      itemStyle = () => ({}),
      onScroll = () => {},
      renderedRange = () => {},
    },
    ref,
  ) => {
    const {
      virtualItems,
      totalSize,
      scrollToIndex: fnScrollToIndex,
    } = useVirtual({
      size: items.length,
      parentRef: ref,
      estimateSize: useCallback((index) => items[index].height, [items]),
      overscan,
    })

    const getFirstVisibleIndex = () => {
      const parent = ref.current

      const scrollOffset = parent?.scrollTop ?? 0

      const firstVisible = virtualItems.find((item) => item.end > scrollOffset)

      return firstVisible?.index ?? null
    }

    const firstRowIdVisible = items[getFirstVisibleIndex()]?.id

    const scrollToIndexDebounceTmOut = useRef()

    // scroll to index
    useEffect(() => {
      if (typeof scrollToIndex?.value !== 'number') return
      clearTimeout(scrollToIndexDebounceTmOut.current)

      if (scrollToIndex.value >= 0) {
        scrollToIndexDebounceTmOut.current = setTimeout(
          () =>
            fnScrollToIndex(scrollToIndex.value, {align: scrollToIndex?.align}),
          100,
        )
      }
    }, [scrollToIndex?.value, scrollToIndex?.align, fnScrollToIndex])

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
          {header && header}
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
  }),
  onRender: PropTypes.func.isRequired,
  setFirstRowIdVisible: PropTypes.func.isRequired,
  header: PropTypes.node,
  itemStyle: PropTypes.func,
  onScroll: PropTypes.func,
  renderedRange: PropTypes.func,
}

VirtualList.displayName = 'VirtualList'

export default VirtualList
