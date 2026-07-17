import React from 'react'
import PropTypes from 'prop-types'

const AddWide = ({size = 32}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M31 12H20V1a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1v11H1a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h11v11a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V20h11a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1z"
      />
    </svg>
  )
}

AddWide.propTypes = {
  size: PropTypes.number,
}

export default AddWide
