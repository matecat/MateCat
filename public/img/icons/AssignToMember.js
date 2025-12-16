import React from 'react'
import PropTypes from 'prop-types'

const AssignToMember = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="1.333"
        d="m4 14 2-2m0 0-2-2m2 2H2m6-1.667h3c.93 0 1.396 0 1.774.115.852.259 1.52.926 1.778 1.778.115.378.115.844.115 1.774M6.333 5a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z"
      />
    </svg>
  )
}

AssignToMember.propTypes = {
  size: PropTypes.number,
}

export default AssignToMember
