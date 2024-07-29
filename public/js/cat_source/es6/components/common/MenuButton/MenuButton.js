import React, {useEffect, useLayoutEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {MenuButtonItem} from './MenuButtonItem'
import usePortal from '../../../hooks/usePortal'
import ArrowDown from '../../../../../../img/icons/ArrowDown'

export const MenuButton = ({
  label,
  icon = <ArrowDown />,
  onClick,
  className = '',
  itemsTarget,
  children,
  disabled,
  isVisibleRectArrow = true,
}) => {
  const ItemsPortal = usePortal(itemsTarget ? itemsTarget : document.body)
  const [itemsCoords, setItemsCoords] = useState()
  const [isReversed, setIsReversed] = useState(false)

  const ref = useRef()
  const portalRef = useRef()
  const setPortalPosition = useRef()
  setPortalPosition.current = (shouldUpdate) => {
    if (shouldUpdate && !itemsCoords) return

    const rect = ref.current.getBoundingClientRect()
    const difference = itemsTarget
      ? itemsTarget.getBoundingClientRect().left /* - itemsTarget.offsetLeft */
      : 0
    const bottom = rect.y + rect.height + window.scrollY
    const availableHeightDelta = Math.max(
      0,
      bottom +
        200 +
        16 -
        (itemsTarget?.offsetParent?.clientHeight || document.body.clientHeight),
    )
    let result = {
      left: rect.left - difference,
      top: rect.y + rect.height,
    }

    if (availableHeightDelta > 0) {
      if (!isReversed) setIsReversed(true)
      result.top = rect.y + window.scrollY
    } else {
      if (isReversed) setIsReversed(false)
      result.top = bottom
    }

    if (shouldUpdate) setItemsCoords(result)
    else setItemsCoords((prevState) => (!prevState ? result : undefined))
  }

  useEffect(() => {
    const handler = (e) => {
      const target = ref.current?.children[1]
        ? ref.current?.children[1]
        : ref.current?.children[0]

      if (!target || !target.contains(e.target)) setItemsCoords(undefined)
    }
    const handlerResize = () => setPortalPosition.current(true)

    document.addEventListener('mouseup', handler)
    window.addEventListener('resize', handlerResize)

    return () => {
      document.removeEventListener('mouseup', handler)
      window.removeEventListener('resize', handlerResize)
    }
  }, [])

  useLayoutEffect(() => {
    if (portalRef.current) {
      const {x, width} = portalRef.current.getBoundingClientRect()

      if (x + width > document.body.clientWidth) {
        setItemsCoords((prevState) => ({
          ...prevState,
          left: document.body.clientWidth - 10 - width,
        }))
      }
    }
  }, [itemsCoords])

  const onShowingItems = (e) => {
    const documentMouseUpEvent = new Event('mouseup', {
      bubbles: true,
      cancelable: false,
    })
    if (!itemsCoords) document.dispatchEvent(documentMouseUpEvent)

    setPortalPosition.current()
    e.stopPropagation()
  }

  return (
    <div className={`menu-button ${className}`}>
      <div ref={ref} className="menu-button-wrapper">
        {label && (
          <button
            className="label"
            disabled={disabled}
            onClick={onClick}
            data-testid="menu-button"
          >
            {label}
          </button>
        )}
        <button
          className={`icon ${itemsCoords ? 'active' : ''}`}
          data-testid="menu-button-show-items"
          disabled={disabled}
          onMouseUp={onShowingItems}
        >
          {icon}
        </button>
      </div>
      {itemsCoords && (
        <ItemsPortal>
          <div
            ref={portalRef}
            className={`menu-button-items${
              isVisibleRectArrow ? ' menu-button-items-rect-arrow' : ''
            } ${isReversed ? 'menu-button-items-reversed' : ''}`}
            style={{
              left: itemsCoords.left,
              top: itemsCoords.top,
              minWidth: ref.current.firstChild.offsetWidth + 20,
            }}
          >
            {children.map((item) => item)}
          </div>
        </ItemsPortal>
      )}
    </div>
  )
}

const MenuButtonItemType = PropTypes.shape({
  type: PropTypes.oneOf([MenuButtonItem]),
})

MenuButton.propTypes = {
  label: PropTypes.string,
  icon: PropTypes.node,
  onClick: PropTypes.func,
  className: PropTypes.string,
  itemsTarget: PropTypes.object,
  children: PropTypes.arrayOf(MenuButtonItemType),
  disabled: PropTypes.bool,
  isVisibleRectArrow: PropTypes.bool,
}
