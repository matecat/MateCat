import {useEffect, useState} from 'react'
import PropTypes from 'prop-types'

function useResizeObserver(ref, {actualWidth = 0, actualHeight = 0} = {}) {
  const [width, setWidth] = useState(actualWidth)
  const [height, setHeight] = useState(actualHeight)

  useEffect(() => {
    let wasCleaned = false
    if (!ref?.current) return
    const {current} = ref
    const resizeObserver = new ResizeObserver((entries) => {
      const {borderBoxSize} = entries[0]

      const width = Array.isArray(borderBoxSize)
        ? borderBoxSize[0]?.inlineSize
        : borderBoxSize.inlineSize
      !wasCleaned && setWidth(width ? width : actualWidth)

      const height = Array.isArray(borderBoxSize)
        ? borderBoxSize[0]?.blockSize
        : borderBoxSize.blockSize
      !wasCleaned && setHeight(height ? height : actualHeight)
    })
    resizeObserver.observe(current)

    return () => {
      wasCleaned = true
      resizeObserver.disconnect()
    }
  }, [ref, actualWidth, actualHeight])

  return {width, height}
}

useResizeObserver.propTypes = {
  ref: PropTypes.shape({current: PropTypes.elementType}).isRequired,
  defaultSize: PropTypes.exact({
    actualWidth: PropTypes.number,
    actualHeight: PropTypes.number,
  }),
}

export default useResizeObserver
