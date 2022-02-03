import React, {useCallback, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {useVirtual} from 'react-virtual'

function VirtualList({items, goToIndex, Component, alignment = 'auto'}) {
  const parentRef = useRef()
  const {virtualItems, totalSize, scrollToIndex} = useVirtual({
    size: items.length,
    parentRef,
    estimateSize: useCallback((index) => items[index].height, [items]),
    overscan: 5,
  })

  useEffect(() => {
    goToIndex >= 0 && scrollToIndex(goToIndex, {align: alignment})
  }, [goToIndex, alignment, scrollToIndex])

  return (
    <div ref={parentRef} className="virtual-list">
      <div
        style={{
          height: `${totalSize}px`,
          width: '100%',
          position: 'relative',
        }}
      >
        {virtualItems.map((item) => (
          <div
            key={item.index}
            item-index={item.index}
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              width: '100%',
              height: `${items[item.index].height}px`,
              transform: `translateY(${item.start}px)`,
            }}
            className={`${
              items[item.index].id % 2 === 0
                ? 'whitesmoke-background'
                : 'white-background'
            }`}
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
  alignment: PropTypes.string,
}

export default VirtualList
