import React from 'react'
import PropTypes from 'prop-types'

const Check = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        d="m8.6 15.6-4.2-4.2L3 12.8l5.6 5.6 12-12L19.2 5z"
        fillRule="evenodd"
        fill="currentColor"
      />
    </svg>
  )
}

Check.propTypes = {
  size: PropTypes.number,
}

export default Check
