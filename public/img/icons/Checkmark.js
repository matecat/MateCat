import React from 'react'
import PropTypes from 'prop-types'

const Checkmark = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M20.707 5.293a1 1 0 0 1 0 1.414l-11 11a1 1 0 0 1-1.414 0l-5-5a1 1 0 1 1 1.414-1.414L9 15.586 19.293 5.293a1 1 0 0 1 1.414 0"
        clipRule="evenodd"
      />
    </svg>
  )
}

Checkmark.propTypes = {
  size: PropTypes.number,
}

export default Checkmark
