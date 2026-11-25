import React from 'react'
import PropTypes from 'prop-types'

const CheckCircleBroken = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M15.663 3.78A9 9 0 1 0 21 12.004v-.92a1 1 0 1 1 2 0v.92A11.002 11.002 0 0 1 8.188 22.319a11 11 0 1 1 8.289-20.366 1 1 0 1 1-.814 1.827Zm7.044-.487a1 1 0 0 1 0 1.414l-10 10.01a1 1 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L12 12.595l9.293-9.302a1 1 0 0 1 1.414 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

CheckCircleBroken.propTypes = {
  size: PropTypes.number,
}

export default CheckCircleBroken
