import React from 'react'
import PropTypes from 'prop-types'

const FileTypePresentation = ({size = 24}) => {
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
        fill="#FEDF80"
      />
      <path
        d="M47.9968 38.4C47.9968 34.304 46.4352 30.2112 43.312 27.088C37.0656 20.8416 26.9312 20.8416 20.6848 27.088C14.4384 33.3344 14.4384 43.4688 20.6848 49.7152C23.808 52.8384 27.904 54.4 32 54.4V38.4H47.9968Z"
        fill="#D9943E"
      />
      <path
        d="M38.4 12.8V0L57.6 19.2H44.8C41.264 19.2 38.4 16.336 38.4 12.8Z"
        fill="#D9943E"
      />
    </svg>
  )
}

FileTypePresentation.propTypes = {
  size: PropTypes.number,
}

export default FileTypePresentation
