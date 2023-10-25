import * as React from 'react'

const IconClose = ({width = 12, height = 12}) => (
  <svg width={width} height={height}>
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
