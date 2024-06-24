import React from 'react'

export const MenuButtonItem = ({children, className, ...restProps}) => {
  return (
    <button
      className={`menu-button-item ${className ? className : ''}`}
      {...restProps}
    >
      {children}
    </button>
  )
}
