import React from 'react'
import PropTypes from 'prop-types'

const SwitchHorizontal = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        d="M16.707 2.293a1 1 0 1 0-1.414 1.414L17.586 6H4a1 1 0 0 0 0 2h13.586l-2.293 2.293a1 1 0 0 0 1.414 1.414l4-4a1 1 0 0 0 0-1.414l-4-4ZM8.707 13.707a1 1 0 1 0-1.414-1.414l-4 4a1 1 0 0 0 0 1.414l4 4a1 1 0 0 0 1.414-1.414L6.414 18H20a1 1 0 1 0 0-2H6.414l2.293-2.293Z"
      />
    </svg>
  )
}

SwitchHorizontal.propTypes = {
  size: PropTypes.number,
}

export default SwitchHorizontal
