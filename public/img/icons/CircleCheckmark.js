import React from 'react'
import PropTypes from 'prop-types'

const CircleCheckmark = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18M1 12C1 5.925 5.925 1 12 1s11 4.925 11 11-4.925 11-11 11S1 18.075 1 12m16.207-3.707a1 1 0 0 1 0 1.414l-6 6a1 1 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414l2.293 2.293 5.293-5.293a1 1 0 0 1 1.414 0"
        clipRule="evenodd"
      />
    </svg>
  )
}

CircleCheckmark.propTypes = {
  size: PropTypes.number,
}

export default CircleCheckmark
