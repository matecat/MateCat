import React from 'react'
import PropTypes from 'prop-types'

const ChevronDown = ({size = 14}) => {
  return (
    <svg width={size} height={size} fill="none" viewBox="0 0 14 8">
      <path
        d="m1 1 6 6 6-6"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

ChevronDown.propTypes = {
  size: PropTypes.number,
}

export default ChevronDown
