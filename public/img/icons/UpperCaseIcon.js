import React from 'react'
import PropTypes from 'prop-types'

const UpperCaseIcon = ({size = 16}) => {
  return (
    <svg width={size} height={size} fill="none">
      <path fill="#fff" d="M0 0h16v16H0z" />
      <path
        fill="#000"
        d="M5.214 4h1.56L10 12H8.428l-.786-2.118H4.3L3.513 12H2l3.214-8Zm-.516 4.818h2.557L6 5.378h-.035l-1.267 3.44Z"
      />
      <path
        stroke="#000"
        strokeLinecap="round"
        strokeWidth="1.2"
        d="M12 5v6M10 7l2-2M14 7l-2-2"
      />
    </svg>
  )
}

UpperCaseIcon.propTypes = {
  size: PropTypes.number,
}

export default UpperCaseIcon
