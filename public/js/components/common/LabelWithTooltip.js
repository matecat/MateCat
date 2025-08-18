import React, {useState} from 'react'
import {useRef} from 'react'
import PropTypes from 'prop-types'
import usePortal from '../../hooks/usePortal'
import TEXT_UTILS from '../../utils/textUtils'

const LabelWithTooltip = ({
  children,
  className,
  tooltipTarget,
  isPositionBottom = false,
}) => {
  const TooltipPortal = usePortal(tooltipTarget ? tooltipTarget : document.body)

  const [tooltipCoords, setTooltipCoords] = useState()

  const ref = useRef()
  const tooltipDelayRef = useRef()

  const getContentText = () => {
    let textContent = children
    while (typeof textContent !== 'string' && textContent) {
      const next = textContent?.props?.children

      if (typeof next === 'string') {
        return next
      }
      if (textContent.type === 'input') {
        return textContent.props.value
      }
      if (next?.type === 'input') {
        return next.props.value
      }
      textContent = next
    }
    return textContent
  }

  const getOverflowChildren = () => {
    let element = ref?.current
    while (
      element &&
      element.nodeName !== '#text' &&
      window.getComputedStyle(element)?.textOverflow !== 'ellipsis'
    ) {
      const next = element.firstChild
      if (
        next &&
        next.nodeName !== '#text' &&
        window.getComputedStyle(next)?.textOverflow === 'ellipsis'
      ) {
        return next
      }
      element = next
    }
    return element
  }

  const overflowChildren = getOverflowChildren()
  const shouldShowTooltip =
    overflowChildren && TEXT_UTILS.isContentTextEllipsis(overflowChildren)

  const mouseEnter = () => {
    const rect = ref.current.getBoundingClientRect()
    tooltipDelayRef.current = setTimeout(() => {
      setTooltipCoords({
        left: rect.left + rect.width / 2,
        top: isPositionBottom ? rect.y + rect.height + 35 : rect.y - 10,
      })
    }, 200)
  }

  const mouseLeave = () => {
    setTooltipCoords(undefined)
    clearTimeout(tooltipDelayRef.current)
  }

  return (
    <div
      ref={ref}
      {...(className && {className})}
      onMouseEnter={mouseEnter}
      onMouseLeave={mouseLeave}
    >
      {children}
      {shouldShowTooltip && tooltipCoords && (
        <TooltipPortal>
          <div
            className={`label-with-tooltip ${isPositionBottom ? 'label-with-tooltip-bottom' : ''}`}
            style={{left: tooltipCoords.left, top: tooltipCoords.top}}
          >
            <p className="label-with-tooltip-content">{getContentText()}</p>
          </div>
        </TooltipPortal>
      )}
    </div>
  )
}

LabelWithTooltip.propTypes = {
  children: PropTypes.node.isRequired,
  className: PropTypes.string,
  tooltipTarget: PropTypes.object,
  isPositionBottom: PropTypes.bool,
}

export default LabelWithTooltip
