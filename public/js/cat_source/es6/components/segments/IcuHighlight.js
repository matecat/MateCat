import React from 'react'

export const IcuHighlight = ({text, sid, children}) => {
  return (
    <div className={'icuItem'}>
      <span>{children}</span>
    </div>
  )
}
