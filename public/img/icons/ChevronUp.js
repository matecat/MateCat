import React from 'react'
import PropTypes from 'prop-types'

const ChevronUp = ({size = 14}) => {
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
        d="M7.52851 5.52864C7.78886 5.26829 8.21097 5.26829 8.47132 5.52864L12.4713 9.52864C12.7317 9.78899 12.7317 10.2111 12.4713 10.4714C12.211 10.7318 11.7889 10.7318 11.5285 10.4714L7.99992 6.94285L4.47132 10.4714C4.21097 10.7318 3.78886 10.7318 3.52851 10.4714C3.26816 10.2111 3.26816 9.78899 3.52851 9.52864L7.52851 5.52864Z"
        fill="currentColor"
      />
    </svg>
  )
}

ChevronUp.propTypes = {
  size: PropTypes.number,
}

export default ChevronUp
