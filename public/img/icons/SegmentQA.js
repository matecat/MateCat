import React from 'react'
import PropTypes from 'prop-types'

const SegmentQA = ({size = 18}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 16 16" fill="none">
      <g clipPath="url(#a)">
        <path
          fill="#fff"
          fillRule="evenodd"
          d="M8 2a6 6 0 1 0 0 12A6 6 0 0 0 8 2ZM.667 8a7.333 7.333 0 1 1 14.666 0A7.333 7.333 0 0 1 .667 8Zm4.862-2.471c.26-.26.682-.26.942 0L8 7.057 9.529 5.53a.667.667 0 0 1 .942.942L8.943 8l1.528 1.529a.667.667 0 0 1-.942.942L8 8.943 6.471 10.47a.667.667 0 0 1-.942-.942L7.057 8 5.53 6.471a.667.667 0 0 1 0-.942Z"
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

SegmentQA.propTypes = {
  size: PropTypes.number,
}

export default SegmentQA
