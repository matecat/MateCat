import React, {useCallback, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {useVirtual} from 'react-virtual'

function VirtualList({
  items,
  goToIndex,
  Component,
  itemStyle = () => ({}),
  width,
  height,
  onScroll = () => {},
  renderedRange = () => {},
  alignment = 'auto',
  overscan = 5,
}) {
  const parentRef = useRef()
  const {virtualItems, totalSize, scrollToIndex} = useVirtual({
    size: items.length,
    parentRef,
    estimateSize: useCallback((index) => items[index].height, [items]),
    overscan,
  })

  // go to index
  useEffect(() => {
    goToIndex >= 0 && scrollToIndex(goToIndex, {align: alignment})
  }, [goToIndex, alignment, scrollToIndex])

  // rendered indexes
  useEffect(() => {
    renderedRange(virtualItems.map(({index}) => index))
  }, [virtualItems, renderedRange])

  // set inline style of width or heigh
  useEffect(() => {
    if (!parentRef?.current) return
    const {current} = parentRef
    if (width)
      current.style.width = `${width}${typeof width === 'number' ? 'px' : ''}`
    if (height)
      current.style.height = `${height}${
        typeof height === 'number' ? 'px' : ''
      }`
  }, [parentRef, width, height])

  return (
    <div ref={parentRef} className="virtual-list" onScroll={() => onScroll()}>
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
}

VirtualList.propTypes = {
  items: PropTypes.array.isRequired,
  goToIndex: PropTypes.number,
  Component: PropTypes.elementType.isRequired,
  itemStyle: PropTypes.func,
  width: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  height: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  onScroll: PropTypes.func,
  renderedRange: PropTypes.func,
  alignment: PropTypes.string,
  overscan: PropTypes.number,
}

export default VirtualList
