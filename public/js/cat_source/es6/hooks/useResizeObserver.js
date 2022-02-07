import {useEffect, useState} from 'react'
import PropTypes from 'prop-types'

function useResizeObserver(ref, {minWidth, minHeight} = {}) {
  const [width, setWidth] = useState(minWidth)
  const [height, setHeight] = useState(minHeight)

  useEffect(() => {
    if (!ref?.current) return
    const {current} = ref
    const resizeObserver = new ResizeObserver((entries) => {
      const {borderBoxSize} = entries[0]

      const width = Array.isArray(borderBoxSize)
        ? borderBoxSize[0]?.inlineSize
        : borderBoxSize.inlineSize
      setWidth(minWidth ? (width > minWidth ? width : minWidth) : width)

      const height = Array.isArray(borderBoxSize)
        ? borderBoxSize[0]?.blockSize
        : borderBoxSize.blockSize
      setHeight(minHeight ? (height > minHeight ? height : minHeight) : height)
    })
    resizeObserver.observe(current)

    return () => resizeObserver.disconnect()
  }, [ref, minWidth, minHeight])

  return {width, height}
}

useResizeObserver.propTypes = {
  ref: PropTypes.shape({current: PropTypes.elementType}).isRequired,
  defaultSize: PropTypes.shape({
    minWidth: PropTypes.number,
    minHeight: PropTypes.number,
  }),
}

export default useResizeObserver
