import {useEffect, useState} from 'react'
import PropTypes from 'prop-types'

/**
 * A React hook that simplify the usage of ResizeObserver API
 *
 * @param {Object} ref - React Ref target
 * @param {Object} options
 * @param {number} [options.actualWidth=0]
 * @param {number} [options.actualHeight=0]
 * @returns {Object} size
 * @returns {number} size.width
 * @returns {number} size.height
 */

function useResizeObserver(ref, {actualWidth = 0, actualHeight = 0} = {}) {
  const [width, setWidth] = useState(actualWidth)
  const [height, setHeight] = useState(actualHeight)

  useEffect(() => {
    let wasCleaned = false
    if (!ref?.current) return
    const {current} = ref
    const resizeObserver = new ResizeObserver((entries) => {
      const {borderBoxSize, target} = entries[0]

      const width = borderBoxSize
        ? Array.isArray(borderBoxSize)
          ? borderBoxSize[0].inlineSize
          : borderBoxSize.inlineSize
        : target.offsetWidth
      !wasCleaned && setWidth(width ? width : actualWidth)

      const height = borderBoxSize
        ? Array.isArray(borderBoxSize)
          ? borderBoxSize[0].blockSize
          : borderBoxSize.blockSize
        : target.offsetHeight
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
