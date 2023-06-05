import React from 'react'
import PropTypes from 'prop-types'

const ArrowDown = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M6.984 9.984h10.031L11.999 15z"
      />
    </svg>
  )
}

ArrowDown.propTypes = {
  size: PropTypes.number,
}

export default ArrowDown
