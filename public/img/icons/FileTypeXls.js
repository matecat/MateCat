import React from 'react'
import PropTypes from 'prop-types'

const FileTypeXls = ({size = 24}) => {
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
        fill="#AAEEE1"
      />
      <path
        d="M38.4 12.8V0L57.6 19.2H44.8C41.264 19.2 38.4 16.336 38.4 12.8Z"
        fill="#33A193"
      />
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M45.5029 27C46.6073 27.0002 47.5029 27.8956 47.5029 29V47C47.5029 48.0355 46.7155 48.8868 45.707 48.9893L45.5029 49H18.5029C18.4338 49 18.3652 48.9961 18.2979 48.9893C17.2894 48.8867 16.5029 48.0355 16.5029 47V29C16.5029 27.8954 17.3984 27 18.5029 27H45.5029ZM20.5029 45H25V40H20.5029V45ZM29 45H43.5029V40H29V45ZM20.5029 36H25V31H20.5029V36ZM29 36H43.5029V31H29V36Z"
        fill="#33A193"
      />
    </svg>
  )
}

FileTypeXls.propTypes = {
  size: PropTypes.number,
}

export default FileTypeXls
