import React from 'react'

export const MenuButtonItem = ({children, ...restProps}) => {
  return (
    <button className="menu-button-item" {...restProps}>
      {children}
    </button>
  )
}
