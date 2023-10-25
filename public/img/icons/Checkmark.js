import React from 'react'
import PropTypes from 'prop-types'

const Checkmark = ({size = 32}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M27 4l-15 15-7-7-5 5 12 12 20-20z"
      ></path>
    </svg>
  )
}

Checkmark.propTypes = {
  size: PropTypes.number,
}

export default Checkmark
