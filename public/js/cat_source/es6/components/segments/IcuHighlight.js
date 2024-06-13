import React from 'react'

export const IcuHighlight = ({start, end, tokens, children}) => {
  const token = tokens.find((item) => item.start === start && item.end === end)
  return (
    <div
      className={`icuItem ${token && token.type === 'error' ? 'error' : ''}`}
    >
      <span>{children}</span>
    </div>
  )
}
