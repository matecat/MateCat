import React from 'react'
import PropTypes from 'prop-types'

const InfoIcon = ({size = 16}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 22 22">
      <path
        fill="currentCOlor"
        fillRule="evenodd"
        d="M11 2a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM0 11C0 4.925 4.925 0 11 0s11 4.925 11 11-4.925 11-11 11S0 17.075 0 11Zm10-4a1 1 0 0 1 1-1h.01a1 1 0 1 1 0 2H11a1 1 0 0 1-1-1Zm1 3a1 1 0 0 1 1 1v4a1 1 0 1 1-2 0v-4a1 1 0 0 1 1-1Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

InfoIcon.propTypes = {
  size: PropTypes.number,
}

export default InfoIcon
