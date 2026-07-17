import React from 'react'

export const IconQR = ({
  width = '42',
  height = width,
  color1 = '#FAFAFA',
  color2 = '#FFFFFF',
}) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      x="0"
      y="0"
      enableBackground="new 0 0 42 42"
      version="1.1"
      viewBox="0 0 42 42"
      xmlSpace="preserve"
      width={`${width}px`}
      height={`${height}px`}
    >
      <path
        d="M2 0h36c1.1 0 2 .9 2 2v36c0 1.1-.9 2-2 2H2c-1.1 0-2-.9-2-2V2C0 .9.9 0 2 0z"
        className="st0"
        transform="translate(1 1)"
        fill="none"
        stroke={color1}
      />
      <g className="st1">
        <path
          fill={color2}
          d="M17.5 25.4l1.8 2.1-1.9 1.5-1.9-2.3c-1 .5-2.2.7-3.5.7-4.9 0-7.9-3.6-7.9-8.3 0-4.7 3-8.3 7.9-8.3s7.9 3.6 7.9 8.3c.1 2.6-.9 4.8-2.4 6.3zM12 13.5c-3.2 0-5 2.4-5 5.7 0 3.3 1.8 5.7 5 5.7.6 0 1.2-.1 1.7-.4l-1.6-1.9 1.8-1.4 1.8 2.1c.9-1 1.4-2.4 1.4-4.1-.1-3.3-1.9-5.7-5.1-5.7zM33 27.2l-3.2-6.1h-4v6.1H23v-16h7.2c3.5 0 5.6 1.9 5.6 4.8 0 2.3-1.4 3.8-3.2 4.5l3.6 6.6H33zm-2.9-13.6h-4.3v5.1h4.3c1.6 0 2.7-1 2.7-2.5 0-1.6-1.1-2.6-2.7-2.6z"
          className="st2"
          transform="translate(1 1)"
        />
      </g>
    </svg>
  )
}
