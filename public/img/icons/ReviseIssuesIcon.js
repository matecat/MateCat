import React from 'react'
import PropTypes from 'prop-types'

const ReviseIssuesIcon = ({size = 18}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 16 16" fill="none">
      <g clipPath="url(#a)">
        <path
          fill="#29292D"
          fillRule="evenodd"
          d="M8 2a6 6 0 1 0 0 12A6 6 0 0 0 8 2ZM.667 8a7.333 7.333 0 1 1 14.666 0A7.333 7.333 0 0 1 .667 8ZM8 4.667c.368 0 .667.298.667.666V8a.667.667 0 0 1-1.334 0V5.333c0-.368.299-.666.667-.666Zm-.667 6c0-.369.299-.667.667-.667h.007a.667.667 0 1 1 0 1.333H8a.667.667 0 0 1-.667-.666Z"
          clipRule="evenodd"
        />
      </g>
      <defs>
        <clipPath id="a">
          <path fill="#fff" d="M0 0h16v16H0z" />
        </clipPath>
      </defs>
    </svg>
  )
}

ReviseIssuesIcon.propTypes = {
  size: PropTypes.number,
}

export default ReviseIssuesIcon
