import * as React from 'react'

const IconClose = ({size = 12}) => (
  <svg width={size} height={size} viewBox="0 0 12 12">
    <path
      d="M11 1L1 11M1 1L11 11"
      stroke="currentColor"
      strokeWidth="1.4"
      strokeLinecap="round"
      strokeLinejoin="round"
      fill="currentColor"
    />
  </svg>
)

export default IconClose
