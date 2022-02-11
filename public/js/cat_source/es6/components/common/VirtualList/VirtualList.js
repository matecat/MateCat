import React, {forwardRef, useCallback, useEffect} from 'react'
import PropTypes from 'prop-types'
import {useVirtual} from 'react-virtual'

const VirtualList = forwardRef(
  (
    {
      items,
      scrollToIndex = {},
      scrollToOffset = {},
      Component,
      itemStyle = () => ({}),
      width,
      height,
      onScroll = () => {},
      renderedRange = () => {},
      overscan = 5,
    },
    ref,
  ) => {
    const {
      virtualItems,
      totalSize,
      scrollToIndex: fnScrollToIndex,
      scrollToOffset: fnScrollToOffset,
    } = useVirtual({
      size: items.length,
      parentRef: ref,
      estimateSize: useCallback((index) => items[index].height, [items]),
      overscan,
    })

    // scroll to index
    useEffect(() => {
      if (typeof scrollToIndex?.value !== 'number') return
      const {value, align} = scrollToIndex
      value >= 0 && fnScrollToIndex(value, {align})
    }, [scrollToIndex, fnScrollToIndex])

    // scroll to offset
    useEffect(() => {
      if (typeof scrollToOffset?.value !== 'number') return
      const {value, align} = scrollToOffset
      value >= 0 && fnScrollToOffset(value, {align})
    }, [scrollToOffset, fnScrollToOffset])

    // rendered indexes
    useEffect(() => {
      renderedRange(virtualItems.map(({index}) => index))
    }, [virtualItems, renderedRange])

    // set inline style of width or heigh
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

    return (
      <div ref={ref} className="virtual-list" onScroll={() => onScroll()}>
        <div
          style={{
            height: `${totalSize}px`,
            width: '100%',
            position: 'relative',
          }}
        >
          {virtualItems.map((item) => (
            <div
              key={items[item.index].id ? items[item.index].id : item.index}
              item-index={item.index}
              style={{
                position: 'absolute',
                top: 0,
                left: 0,
                width: '100%',
                height: `${items[item.index].height}px`,
                transform: `translateY(${item.start}px)`,
                ...itemStyle(items[item.index]),
              }}
            >
              <Component {...items[item.index]} />
            </div>
          ))}
        </div>
      </div>
    )
  },
)

VirtualList.propTypes = {
  items: PropTypes.array.isRequired,
  scrollToIndex: PropTypes.exact({
    value: PropTypes.number,
    align: PropTypes.string,
  }),
  scrollToOffset: PropTypes.exact({
    value: PropTypes.number,
    align: PropTypes.string,
  }),
  Component: PropTypes.elementType.isRequired,
  itemStyle: PropTypes.func,
  width: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  height: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  onScroll: PropTypes.func,
  renderedRange: PropTypes.func,
  overscan: PropTypes.number,
}

VirtualList.displayName = 'VirtualList'

export default VirtualList
