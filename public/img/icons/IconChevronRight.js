import React from 'react'

const IconChevronRight = ({size = 24}) => {
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
        d="M5.646 12.354a.5.5 0 0 1 0-.708L9.293 8 5.646 4.354a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

export default IconChevronRight
