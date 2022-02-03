import {useEffect, useState} from 'react'
import PropTypes from 'prop-types'

function useResizeObserver(ref, {defaultWidth, defaultHeight} = {}) {
  const [width, setWidth] = useState(defaultWidth)
  const [height, setHeight] = useState(defaultHeight)

  useEffect(() => {
    if (!ref?.current) return
    const {current} = ref
    const resizeObserver = new ResizeObserver((entries) => {
      const {borderBoxSize} = entries[0]

      const width = Array.isArray(borderBoxSize)
        ? borderBoxSize[0]?.inlineSize
        : borderBoxSize.inlineSize
      setWidth(
        defaultWidth ? (width > defaultWidth ? width : defaultWidth) : width,
      )

      const height = Array.isArray(borderBoxSize)
        ? borderBoxSize[0]?.blockSize
        : borderBoxSize.blockSize
      setHeight(
        defaultHeight
          ? height > defaultHeight
            ? height
            : defaultHeight
          : height,
      )
    })
    resizeObserver.observe(current)

    return () => resizeObserver.disconnect()
  }, [ref, defaultWidth, defaultHeight])

  return {width, height}
}

useResizeObserver.propTypes = {
  ref: PropTypes.shape({current: PropTypes.elementType}).isRequired,
  defaultSize: PropTypes.shape({
    defaultWidth: PropTypes.number,
    defaultHeight: PropTypes.number,
  }),
}

export default useResizeObserver
