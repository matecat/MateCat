import React from 'react'
import PropTypes from 'prop-types'

const ReviseIssuesIcon = ({size = 18}) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="18"
      height="18"
      viewBox="0 0 18 18"
      fill="none"
    >
      <g clipPath="url(#clip0_8382_8439)">
        <path
          fillRule="evenodd"
          clipRule="evenodd"
          d="M9 2.25C5.27208 2.25 2.25 5.27208 2.25 9C2.25 12.7279 5.27208 15.75 9 15.75C12.7279 15.75 15.75 12.7279 15.75 9C15.75 5.27208 12.7279 2.25 9 2.25ZM0.75 9C0.75 4.44365 4.44365 0.75 9 0.75C13.5563 0.75 17.25 4.44365 17.25 9C17.25 13.5563 13.5563 17.25 9 17.25C4.44365 17.25 0.75 13.5563 0.75 9ZM9 5.25C9.41421 5.25 9.75 5.58579 9.75 6V9C9.75 9.41421 9.41421 9.75 9 9.75C8.58579 9.75 8.25 9.41421 8.25 9V6C8.25 5.58579 8.58579 5.25 9 5.25ZM8.25 12C8.25 11.5858 8.58579 11.25 9 11.25H9.0075C9.42171 11.25 9.7575 11.5858 9.7575 12C9.7575 12.4142 9.42171 12.75 9.0075 12.75H9C8.58579 12.75 8.25 12.4142 8.25 12Z"
          fill="#29292D"
        />
      </g>
      <defs>
        <clipPath id="clip0_8382_8439">
          <rect width="18" height="18" fill="white" />
        </clipPath>
      </defs>
    </svg>
  )
}

ReviseIssuesIcon.propTypes = {
  size: PropTypes.number,
}

export default ReviseIssuesIcon
