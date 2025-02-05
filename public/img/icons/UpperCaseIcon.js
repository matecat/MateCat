import React from 'react'
import PropTypes from 'prop-types'

const UpperCaseIcon = ({size = 16}) => {
  return (
    <svg width={size} height={size} fill="none">
      <path
        fill="#000"
        d="M7.616 3H9.37L13 13h-1.768l-.884-2.647H6.587L5.702 13H4L7.616 3Zm-.58 6.022h2.876L8.5 4.722h-.04l-1.425 4.3Z"
      />
    </svg>
  )
}

UpperCaseIcon.propTypes = {
  size: PropTypes.number,
}

export default UpperCaseIcon
