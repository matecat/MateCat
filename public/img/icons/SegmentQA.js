import React from 'react'
import PropTypes from 'prop-types'

const SegmentQA = ({size = 18}) => {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none">
      <g clipPath="url(#a)">
        <path
          fill="currentColor"
          fillRule="evenodd"
          d="M3.726 4.787a6.75 6.75 0 0 0 9.487 9.487L3.726 4.787Zm1.06-1.06 9.488 9.486a6.75 6.75 0 0 0-9.487-9.487ZM.75 9a8.25 8.25 0 1 1 16.5 0A8.25 8.25 0 0 1 .75 9Z"
        />
      </g>
      <defs>
        <clipPath id="a">
          <path fill="currentColor" d="M0 0h18v18H0z" />
        </clipPath>
      </defs>
    </svg>
  )
}

SegmentQA.propTypes = {
  size: PropTypes.number,
}

export default SegmentQA
