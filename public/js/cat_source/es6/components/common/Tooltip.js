import React, {useEffect, useRef, useState} from 'react'
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
  stylePointerElement,
  style,
  children,
  content,
  isInteractiveContent,
}) => {
  const [isVisible, setVisibility] = useState(false)
  const [coords, setCoordinate] = useState()
  const Portal = usePortal(document.body)

  const tooltipTimeout = useRef()
  const refContainer = useRef()
  const isCursorInsideTooltipContainer = useRef()

  useEffect(() => {
    if (!isInteractiveContent) return

    const onMouseMoveHandler = ({clientX, clientY}) => {
      const rect = refContainer.current.getBoundingClientRect()
      if (
        clientX >= rect.left &&
        clientX <= rect.right &&
        clientY >= rect.top &&
        clientY <= rect.bottom
      ) {
        isCursorInsideTooltipContainer.current = true
      } else {
        if (isCursorInsideTooltipContainer.current) {
          isCursorInsideTooltipContainer.current = false
          clearTimeout(tooltipTimeout.current)
          setVisibility(false)
        }
      }
    }

    if (isVisible) document.addEventListener('mousemove', onMouseMoveHandler)

    return () => document.removeEventListener('mousemove', onMouseMoveHandler)
  }, [isInteractiveContent, isVisible])

  // FUNCTIONS
  const showToolTip = () => {
    if (content && content !== '') {
      setCoordinate(getCoords())
      clearTimeout(tooltipTimeout.current)
      tooltipTimeout.current = setTimeout(() => {
        setVisibility(true)
      }, delay)
    }
  }
  const hideToolTip = () => {
    isCursorInsideTooltipContainer.current = false
    clearTimeout(tooltipTimeout.current)
    setVisibility(false)
  }
  const hideTooltipWithTimeout = () => {
    clearTimeout(tooltipTimeout.current)
    tooltipTimeout.current = setTimeout(() => {
      if (!isCursorInsideTooltipContainer.current) setVisibility(false)
    }, delay)
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
    <div
      className={`tooltip-container ${position} ${className} ${isInteractiveContent ? 'tooltip-container-interactive' : ''}`}
      style={style}
    >
      {content}
    </div>
  ) : children ? (
    <div
      onPointerEnter={showToolTip}
      onPointerLeave={
        isInteractiveContent ? hideTooltipWithTimeout : hideToolTip
      }
      style={stylePointerElement}
    >
      {children}
      {isVisible && (
        <Portal id="portal-root">
          <div
            ref={refContainer}
            className={`tooltip-container ${position} ${className} ${isInteractiveContent ? 'tooltip-container-interactive' : ''}`}
            style={{...style, ...coords}}
          >
            {content}
          </div>
        </Portal>
      )}
    </div>
  ) : null
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
