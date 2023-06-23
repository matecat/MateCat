import React from 'react'
import PropTypes from 'prop-types'

const Lock = ({size = 32}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M15 21.915a1.5 1.5 0 1 1 1 0v2.594a.501.501 0 0 1-1 0v-2.594zM8 14c-1.658.005-3 1.34-3 3.009v9.981a3.008 3.008 0 0 0 3.009 3.009h14.982A3.002 3.002 0 0 0 26 26.99v-9.981A3.008 3.008 0 0 0 23 14v-3.501a7.5 7.5 0 0 0-15 0V14zm3 0v-3.499A4.497 4.497 0 0 1 15.5 6c2.48 0 4.5 2.015 4.5 4.501V14h-9z"
      />
    </svg>
  )
}

Lock.propTypes = {
  size: PropTypes.number,
}

export default Lock
