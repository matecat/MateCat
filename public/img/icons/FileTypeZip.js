import React from 'react'
import PropTypes from 'prop-types'

const FileTypeZip = ({size = 24}) => {
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
        fill="#D0D0D5"
      />
      <path
        d="M38.4 12.8V0L57.6 19.2H44.8C41.264 19.2 38.4 16.336 38.4 12.8Z"
        fill="#85858D"
      />
      <rect x="26" width="8" height="6" rx="1" fill="#85858D" />
      <rect x="26" y="10" width="8" height="6" rx="1" fill="#85858D" />
      <rect x="26" y="20" width="8" height="6" rx="1" fill="#85858D" />
      <rect x="26" y="30" width="8" height="6" rx="1" fill="#85858D" />
      <rect x="22" y="5" width="8" height="6" rx="1" fill="#85858D" />
      <rect x="22" y="15" width="8" height="6" rx="1" fill="#85858D" />
      <rect x="22" y="25" width="8" height="6" rx="1" fill="#85858D" />
      <rect x="22" y="35" width="8" height="6" rx="1" fill="#85858D" />
    </svg>
  )
}

FileTypeZip.propTypes = {
  size: PropTypes.number,
}

export default FileTypeZip
