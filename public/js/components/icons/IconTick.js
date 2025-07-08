import React from 'react'

const IconTick = ({width = '42', height = '42', style, color = '#000000'}) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 16 12"
      width={`${width}px`}
      height={`${height}px`}
      style={style}
    >
      <path
        fill={color}
        fillRule="evenodd"
        stroke={color}
        strokeWidth="1"
        d="M15.735.265a.798.798 0 00-1.13 0L5.04 9.831 1.363 6.154a.798.798 0 00-1.13 1.13l4.242 4.24a.799.799 0 001.13 0l10.13-10.13a.798.798 0 000-1.129z"
        transform="translate(-266 -10) translate(266 8) translate(0 2)"
      />
    </svg>
  )
}

export default IconTick
