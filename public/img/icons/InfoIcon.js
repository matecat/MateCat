import React from 'react'
import PropTypes from 'prop-types'

const InfoIcon = ({size = 24}) => {
  return (
    <svg
      width={size}
      height={size}
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M8.029 10.667 8.022 8m-.008-2.667h-.006M1.355 8.018a6.667 6.667 0 1 1 13.333-.036 6.667 6.667 0 0 1-13.333.036Z"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

InfoIcon.propTypes = {
  size: PropTypes.number,
}

export default InfoIcon
