import React from 'react'

const IconDown = ({width = '42', height = '42', style}) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 16 10"
      width={`${width}px`}
      height={`${height}px`}
      style={style}
    >
      <path
        fill="#788190"
        fillRule="evenodd"
        stroke="none"
        strokeWidth="1"
        d="M15.768.219a.561.561 0 00-.794 0L8 7.207 1.012.219a.561.561 0 00-.793.793l7.37 7.37c.11.11.247.165.397.165a.57.57 0 00.397-.164l7.37-7.371a.55.55 0 00.015-.793z"
        transform="translate(-121 -16) translate(121 13) translate(0 3.667)"
      />
    </svg>
  )
}

export default IconDown
