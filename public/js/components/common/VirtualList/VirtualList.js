import React, {forwardRef, useCallback, useEffect} from 'react'
import PropTypes from 'prop-types'
import {useVirtual} from 'react-virtual'

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

    // scroll to index
    useEffect(() => {
      if (typeof scrollToIndex?.value !== 'number') return
      scrollToIndex.value >= 0 &&
        fnScrollToIndex(scrollToIndex.value, {align: scrollToIndex?.align})
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
  itemStyle: PropTypes.func,
  onScroll: PropTypes.func,
  renderedRange: PropTypes.func,
}

VirtualList.displayName = 'VirtualList'

export default VirtualList
