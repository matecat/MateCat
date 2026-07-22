import React from 'react'
import PropTypes from 'prop-types'

const Microsoft = ({size = 16}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <g clip-path="url(#clip0_8282_8394)">
        <path d="M0 0H11.4286V11.4286H0V0Z" fill="#F35325" />
        <path d="M12.5713 0H23.9999V11.4286H12.5713V0Z" fill="#81BC06" />
        <path d="M0 12.5714H11.4286V24H0V12.5714Z" fill="#05A6F0" />
        <path d="M12.5713 12.5714H23.9999V24H12.5713V12.5714Z" fill="#FFBA08" />
      </g>
      <defs>
        <clipPath id="clip0_8282_8394">
          <rect width="24" height="24" fill="white" />
        </clipPath>
      </defs>
    </svg>
  )
}

Microsoft.propTypes = {
  size: PropTypes.number,
}

export default Microsoft
