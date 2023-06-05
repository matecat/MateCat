import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {MenuButtonItem} from './MenuButtonItem'
import usePortal from '../../../hooks/usePortal'
import ArrowDown from '../../../../../../img/icons/ArrowDown'

export const MenuButton = ({label, onClick, itemsTarget, children}) => {
  const ItemsPortal = usePortal(itemsTarget ? itemsTarget : document.body)
  const [itemsCoords, setItemsCoords] = useState()

  const ref = useRef()

  useEffect(() => {
    const handler = () => setItemsCoords(undefined)
    document.addEventListener('mouseup', handler)

    return () => document.removeEventListener('mouseup', handler)
  }, [])

  const onShowingItems = () => {
    const rect = ref.current.getBoundingClientRect()
    const difference = itemsTarget
      ? itemsTarget.getBoundingClientRect().left
      : 0

    setItemsCoords({
      left: rect.left - difference,
      top: rect.y + rect.height,
    })
  }

  return (
    <div className="menu-button">
      <div ref={ref} className="menu-button-wrapper">
        <button onClick={onClick}>{label}</button>
        <button onClick={onShowingItems}>
          <ArrowDown />
        </button>
      </div>
      {itemsCoords && (
        <ItemsPortal>
          <div
            className="menu-button-items"
            style={{
              left: itemsCoords.left,
              top: itemsCoords.top,
              minWidth: ref.current.offsetWidth,
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
  itemsTarget: PropTypes.object,
  children: PropTypes.arrayOf(MenuButtonItemType),
}
