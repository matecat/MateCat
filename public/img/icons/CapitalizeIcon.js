import React from 'react'
import PropTypes from 'prop-types'

const CapitalizeIcon = ({size = 16}) => {
  return (
    <svg width={size} height={size} fill="none">
      <path d="M4 3H12V4.51261H8.85813V13H7.12803V4.51261H4V3Z" fill="black" />
    </svg>
  )
}

CapitalizeIcon.propTypes = {
  size: PropTypes.number,
}

export default CapitalizeIcon
