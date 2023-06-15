import React from 'react'
import PropTypes from 'prop-types'

const Upload = ({size = 32}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M14 18h4v-8h6l-8-8-8 8h6zm6-4.5v3.085L29.158 20 16 24.907 2.842 20 12 16.585V13.5L0 18v8l16 6 16-6v-8z"
      />
    </svg>
  )
}

Upload.propTypes = {
  size: PropTypes.number,
}

export default Upload
