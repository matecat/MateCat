import React from 'react'
import PropTypes from 'prop-types'

const SegmentQA = ({size = 18}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 22 22"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M3.9681 5.38231C2.73647 6.92199 2 8.87499 2 11C2 15.9706 6.02944 20 11 20C13.125 20 15.078 19.2635 16.6177 18.0319L3.9681 5.38231ZM5.38231 3.9681L18.0319 16.6177C19.2635 15.078 20 13.125 20 11C20 6.02944 15.9706 2 11 2C8.87499 2 6.92199 2.73647 5.38231 3.9681ZM0 11C0 4.92487 4.92487 0 11 0C17.0751 0 22 4.92487 22 11C22 17.0751 17.0751 22 11 22C4.92487 22 0 17.0751 0 11Z"
        fill="currentColor"
      />
    </svg>
  )
}

SegmentQA.propTypes = {
  size: PropTypes.number,
}

export default SegmentQA
