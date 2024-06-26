import React, {useRef, useState} from 'react'
import PropTypes from 'prop-types'
import usePortal from '../../hooks/usePortal'

export const TOOLTIP_POSITION = {
  TOP: 'top',
  BOTTOM: 'bottom',
  LEFT: 'left',
  RIGHT: 'right',
}
export const TOOLTIP_MODE = {
  AUTO: 'auto',
  STATIC: 'static',
}

const Tooltip = ({
  position = TOOLTIP_POSITION.TOP,
  mode = TOOLTIP_MODE.AUTO,
  delay = 300,
  className = '',
  style,
  children,
  content,
}) => {
  const TooltipTimeout = useRef()

  const [isVisible, setVisibility] = useState(false)
  const [coords, setCoordinate] = useState()
  const Portal = usePortal(document.body)

  // FUNCTIONS
  const showToolTip = () => {
    if (content && content !== '') {
      setCoordinate(getCoords())
      TooltipTimeout.current = setTimeout(() => {
        setVisibility(true)
      }, delay)
    }
  }
  const hideToolTip = () => {
    setVisibility(false)
    clearInterval(TooltipTimeout.current)
  }

  // RENDER
  const getCoords = () => {
    const boundingRect =
      children &&
      children.ref?.current &&
      children.ref.current.getBoundingClientRect()
    if (!boundingRect) return {}
    const top =
      position === TOOLTIP_POSITION.LEFT || position === TOOLTIP_POSITION.RIGHT
        ? boundingRect.y + window.scrollY + boundingRect.height / 2
        : position === TOOLTIP_POSITION.TOP
          ? boundingRect.y + window.scrollY - 7 // 7 = tooltip arrow height
          : boundingRect.y + window.scrollY + boundingRect.height + 7 // 7 = tooltip arrow height
    const left =
      position === TOOLTIP_POSITION.TOP || position === TOOLTIP_POSITION.BOTTOM
        ? boundingRect.x + window.scrollX + boundingRect.width / 2
        : position === TOOLTIP_POSITION.LEFT
          ? boundingRect.x + window.scrollX - 7 // 7 = tooltip arrow width
          : boundingRect.x + window.scrollX + boundingRect.width + 7 // 7 = tooltip arrow width
    return {top, left}
  }

  return mode === TOOLTIP_MODE.STATIC || !children ? (
    <div className={`tooltip-container ${position} ${className}`} style={style}>
      {content}
    </div>
  ) : children ? (
    <div onPointerEnter={showToolTip} onPointerLeave={hideToolTip}>
      {children}
      {isVisible && (
        <Portal id="portal-root">
          <div
            className={`tooltip-container ${position} ${className}`}
            style={{...style, ...coords}}
          >
            {content}
          </div>
        </Portal>
      )}
    </div>
  ) : (
    false
  )
}

Tooltip.propTypes = {
  className: PropTypes.string,
  position: PropTypes.oneOf([...Object.values(TOOLTIP_POSITION)]),
  mode: PropTypes.oneOf([...Object.values(TOOLTIP_MODE)]),
  style: PropTypes.object,
  delay: PropTypes.number,
  children: PropTypes.node,
  content: PropTypes.node.isRequired,
}

export default Tooltip
