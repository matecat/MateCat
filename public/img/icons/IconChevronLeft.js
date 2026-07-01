import React from 'react'

const IconChevronLeft = ({size = 24}) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 16 16"
      width={size}
      height={size}
    >
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M10.354 3.646a.5.5 0 0 1 0 .708L6.707 8l3.647 3.646a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 0 1 .708 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

export default IconChevronLeft
