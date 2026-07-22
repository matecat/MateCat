import React from 'react'
import PropTypes from 'prop-types'

const FlipBackward = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M7.707 4.293a1 1 0 0 1 0 1.414L5.414 8H16.5a5.5 5.5 0 1 1 0 11H12a1 1 0 1 1 0-2h4.5a3.5 3.5 0 1 0 0-7H5.414l2.293 2.293a1 1 0 1 1-1.414 1.414l-4-4a1 1 0 0 1 0-1.414l4-4a1 1 0 0 1 1.414 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

FlipBackward.propTypes = {
  size: PropTypes.number,
}

export default FlipBackward
