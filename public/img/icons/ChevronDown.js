import React from 'react'
import PropTypes from 'prop-types'

const ChevronDown = ({size = 14}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 16 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M3.52864 5.52851C3.78899 5.26816 4.2111 5.26816 4.47145 5.52851L8.00004 9.05711L11.5286 5.52851C11.789 5.26816 12.2111 5.26816 12.4714 5.52851C12.7318 5.78886 12.7318 6.21097 12.4714 6.47132L8.47145 10.4713C8.2111 10.7317 7.78899 10.7317 7.52864 10.4713L3.52864 6.47132C3.26829 6.21097 3.26829 5.78886 3.52864 5.52851Z"
        fill="#29292D"
      />
    </svg>
  )
}

ChevronDown.propTypes = {
  size: PropTypes.number,
}

export default ChevronDown
