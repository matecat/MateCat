import React from 'react'

const IconFilter = ({
  width = '42',
  height = '42',
  style,
  color = '#000000',
}) => {
  return (
    <svg
      width={`${width}px`}
      height={`${height}px`}
      style={style}
      xmlns="http://www.w3.org/2000/svg"
      xmlnsXlink="http://www.w3.org/1999/xlink"
      viewBox={`0 0 42 42`}
    >
      <path
        fill={color}
        fillRule="evenodd"
        stroke={color}
        strokeWidth="0.5"
        d="M22.966 0H1.034C.647 0 .292.21.115.546c-.179.34-.147.75.082 1.06L8.232 12.65l.008.012c.292.384.45.85.45 1.329v9.003a.988.988 0 00.299.712c.193.189.455.295.728.295.139 0 .276-.027.404-.079l4.516-1.68c.404-.12.673-.492.673-.941v-7.31c0-.479.159-.945.45-1.33l.009-.01 8.034-11.044c.23-.31.26-.72.082-1.06A1.034 1.034 0 0022.966 0zm-8.213 11.939a3.412 3.412 0 00-.693 2.05v7.163l-4.12 1.533V13.99a3.41 3.41 0 00-.694-2.051l-7.8-10.72h21.108l-7.8 10.72z"
        transform="translate(9 9)"
      />
    </svg>
  )
}

export default IconFilter
