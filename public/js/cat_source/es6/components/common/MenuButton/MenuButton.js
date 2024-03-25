import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {MenuButtonItem} from './MenuButtonItem'
import usePortal from '../../../hooks/usePortal'
import ArrowDown from '../../../../../../img/icons/ArrowDown'

export const MenuButton = ({
  label,
  onClick,
  icon = <ArrowDown />,
  className = '',
  itemsTarget,
  children,
  disabled,
}) => {
  const ItemsPortal = usePortal(itemsTarget ? itemsTarget : document.body)
  const [itemsCoords, setItemsCoords] = useState()
  const [isReversed, setIsReversed] = useState(false)

  const ref = useRef()

  useEffect(() => {
    const handler = (e) => {
      if (!ref.current?.children[1].contains(e.target))
        setItemsCoords(undefined)
    }
    document.addEventListener('mousedown', handler)

    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const onShowingItems = (e) => {
    const rect = ref.current.getBoundingClientRect()
    const difference = itemsTarget
      ? itemsTarget.getBoundingClientRect().left - itemsTarget.offsetLeft
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
    setItemsCoords((prevState) => (!prevState ? result : undefined))
    e.stopPropagation()
  }

  return (
    <div className={`menu-button ${className}`}>
      <div ref={ref} className="menu-button-wrapper">
        <button disabled={disabled} onClick={onClick}>
          {label}
        </button>
        <button disabled={disabled} onMouseUp={onShowingItems}>
          {icon}
        </button>
      </div>
      {itemsCoords && (
        <ItemsPortal>
          <div
            className={`menu-button-items ${
              isReversed ? 'menu-button-items-reversed' : ''
            }`}
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
  label: PropTypes.string.isRequired,
  onClick: PropTypes.func.isRequired,
  icon: PropTypes.node,
  className: PropTypes.string,
  itemsTarget: PropTypes.object,
  children: PropTypes.arrayOf(MenuButtonItemType),
  disabled: PropTypes.bool,
}
