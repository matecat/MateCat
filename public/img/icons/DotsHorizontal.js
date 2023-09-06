import React from 'react'
import PropTypes from 'prop-types'

const DotsHorizontal = ({size = 20}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 20 20">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M10 12a2 2 0 1 1 0-4 2 2 0 1 1 0 4zm0-6a2 2 0 1 1 0-4 2 2 0 1 1 0 4zm0 12a2 2 0 1 1 0-4 2 2 0 1 1 0 4z"
      />
    </svg>
  )
}

DotsHorizontal.propTypes = {
  size: PropTypes.number,
}

export default DotsHorizontal
