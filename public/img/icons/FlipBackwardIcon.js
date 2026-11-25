import React from 'react'
import PropTypes from 'prop-types'

const FlipBackwardIcon = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <path
        d="M7.70711 5.70711C8.09763 5.31658 8.09763 4.68342 7.70711 4.29289C7.31658 3.90237 6.68342 3.90237 6.29289 4.29289L2.29289 8.29289C1.90237 8.68342 1.90237 9.31658 2.29289 9.70711L6.29289 13.7071C6.68342 14.0976 7.31658 14.0976 7.70711 13.7071C8.09763 13.3166 8.09763 12.6834 7.70711 12.2929L5.41421 10H16.5C18.433 10 20 11.567 20 13.5C20 15.433 18.433 17 16.5 17H12C11.4477 17 11 17.4477 11 18C11 18.5523 11.4477 19 12 19H16.5C19.5376 19 22 16.5376 22 13.5C22 10.4624 19.5376 8 16.5 8H5.41421L7.70711 5.70711Z"
        fill="currentColor"
      />
    </svg>
  )
}

FlipBackwardIcon.propTypes = {
  size: PropTypes.number,
}

export default FlipBackwardIcon
