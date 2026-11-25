import React from 'react'

const IconUserLogout = ({width = '42', height = '42', style}) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 42 42"
      width={`${width}px`}
      height={`${height}px`}
      style={style}
    >
      <path
        fill="#09C"
        fillRule="evenodd"
        stroke="none"
        strokeWidth="1"
        d="M11.878 0C5.318 0 0 5.319 0 11.879c0 6.56 5.318 11.877 11.878 11.877 6.56 0 11.878-5.317 11.878-11.877C23.756 5.318 18.438 0 11.878 0zm0 3.552a3.929 3.929 0 110 7.858 3.929 3.929 0 010-7.858zm-.003 17.098A8.717 8.717 0 016.2 18.557a1.674 1.674 0 01-.588-1.273c0-2.2 1.781-3.96 3.982-3.96h4.571a3.956 3.956 0 013.975 3.96c0 .49-.214.954-.587 1.272a8.714 8.714 0 01-5.677 2.094z"
        transform="translate(9 9)"
      />
    </svg>
  )
}

export default IconUserLogout
