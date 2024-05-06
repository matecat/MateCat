import React from 'react'
import PropTypes from 'prop-types'

const Close = ({size = 24}) => {
  return (
    <svg width={size} height={size} fill="none" viewBox="0 0 24 24">
      <g clipPath="url(#a)">
        <path
          stroke="currentColor"
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth="2.5"
          d="M7 7l10 10M7 17L17 7"
        />
      </g>
      <defs>
        <clipPath id="a">
          <path fill="#fff" d="M0 0h24v24H0z" />
        </clipPath>
      </defs>
    </svg>
  )
}

Close.propTypes = {
  size: PropTypes.number,
}

export default Close
