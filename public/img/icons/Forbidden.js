import React from 'react'
import PropTypes from 'prop-types'

const Forbidden = ({size = 16}) => {
  return (
    <svg width={size} height={size} fill="none">
      <path
        d="M3.758 3.758L12.242 12.242M14 8C14 11.3137 11.3137 14 8 14C4.68629 14 2 11.3137 2 8C2 4.68629 4.68629 2 8 2C11.3137 2 14 4.68629 14 8Z"
        stroke="currentColor"
        strokeWidth="1.33"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

Forbidden.propTypes = {
  size: PropTypes.number,
}

export default Forbidden
