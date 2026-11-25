import React from 'react'
import PropTypes from 'prop-types'

const FileTypeHtml = ({size = 24}) => {
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
        d="M16 38H48M16 38C16 46.8366 23.1634 54 32 54M16 38C16 29.1634 23.1634 22 32 22M48 38C48 46.8366 40.8366 54 32 54M48 38C48 29.1634 40.8366 22 32 22M32 22C36.002 26.3814 38.2764 32.0673 38.4 38C38.2764 43.9327 36.002 49.6186 32 54M32 22C27.998 26.3814 25.7236 32.0673 25.6 38C25.7236 43.9327 27.998 49.6186 32 54"
        stroke="#2985EC"
        strokeWidth="4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

FileTypeHtml.propTypes = {
  size: PropTypes.number,
}

export default FileTypeHtml
