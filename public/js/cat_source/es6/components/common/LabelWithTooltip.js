import React from 'react'
import {useRef} from 'react'
import PropTypes from 'prop-types'
import TEXT_UTILS from '../../utils/textUtils'

const LabelWithTooltip = ({children, tooltip, tooltipPosition, className}) => {
  const ref = useRef()

  const getContentText = () => {
    let textContent = children
    while (typeof textContent !== 'string' && textContent) {
      const next = textContent?.props?.children
      if (typeof next === 'string') {
        return next
      }
      textContent = next
    }
    return textContent
  }

  const getOverflowChildren = () => {
    let element = ref?.current
    while (
      element &&
      window.getComputedStyle(element)?.textOverflow !== 'ellipsis'
    ) {
      const next = element.firstChild
      if (window.getComputedStyle(next)?.textOverflow === 'ellipsis') {
        return next
      }
      element = next
    }
    return element
  }

  const shouldShowTooltip = TEXT_UTILS.isContentTextEllipsis(
    getOverflowChildren(),
  )

  return (
    <div
      ref={ref}
      {...(className && {className})}
      {...(shouldShowTooltip && {
        'aria-label': tooltip ? tooltip : getContentText(),
      })}
      {...(tooltipPosition && {'tooltip-position': tooltipPosition})}
    >
      {children}
    </div>
  )
}

LabelWithTooltip.propTypes = {
  children: PropTypes.node.isRequired,
  tooltip: PropTypes.string,
  tooltipPosition: PropTypes.string,
  className: PropTypes.string,
}

export default LabelWithTooltip
