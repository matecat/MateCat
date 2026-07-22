import React from 'react'
import PropTypes from 'prop-types'

const FileTypeText = ({size = 24}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M6.40002 54.4V9.6C6.40002 4.2976 10.6976 0 16 0H38.4L57.6 19.2V54.4C57.6 59.7024 53.3024 64 48 64H16C10.6976 64 6.40002 59.7024 6.40002 54.4Z"
        fill="#CAE0FD"
      />
      <path
        d="M38.4 12.8V0L57.6 19.2H44.8C41.264 19.2 38.4 16.336 38.4 12.8Z"
        fill="#2985EC"
      />
      <path
        d="M41.6 35.2H22.4C20.6304 35.2 19.2 33.7664 19.2 32C19.2 30.2336 20.6304 28.8 22.4 28.8H41.6C43.3696 28.8 44.8 30.2336 44.8 32C44.8 33.7664 43.3696 35.2 41.6 35.2Z"
        fill="#2985EC"
      />
      <path
        d="M35.2 48H22.4C20.6304 48 19.2 46.5664 19.2 44.8C19.2 43.0336 20.6304 41.6 22.4 41.6H35.2C36.9696 41.6 38.4 43.0336 38.4 44.8C38.4 46.5664 36.9696 48 35.2 48Z"
        fill="#2985EC"
      />
    </svg>
  )
}

FileTypeText.propTypes = {
  size: PropTypes.number,
}

export default FileTypeText
