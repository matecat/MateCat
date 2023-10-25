import React from 'react'
import PropTypes from 'prop-types'

const Download = ({size = 32}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="m16 18 8-8h-6V2h-4v8H8zm7.273-3.273-2.242 2.242L29.159 20l-13.158 4.907L2.843 20l8.127-3.031-2.242-2.242L.001 18v8l16 6 16-6v-8z"
      />
    </svg>
  )
}

Download.propTypes = {
  size: PropTypes.number,
}

export default Download
