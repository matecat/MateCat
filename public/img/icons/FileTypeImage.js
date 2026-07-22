import React from 'react'
import PropTypes from 'prop-types'

const FileTypeImage = ({size = 24}) => {
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
        fill="#B0BCF7"
      />
      <path
        d="M38.4 12.8V0L57.6 19.2H44.8C41.264 19.2 38.4 16.336 38.4 12.8Z"
        fill="#5E64EB"
      />
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M44.8 30.4C44.8 33.051 42.651 35.2 40 35.2C37.349 35.2 35.2 33.051 35.2 30.4C35.2 27.749 37.349 25.6 40 25.6C42.651 25.6 44.8 27.749 44.8 30.4ZM18 54.4C16.896 54.4 16 53.4944 16 52.4C16 47.1232 20 35.2 24 35.2C25.9434 35.2 27.9622 37.466 30.0383 39.7961C32.2354 42.2621 34.4966 44.8 36.8 44.8C38.0208 44.8 39.3193 43.863 40.511 43.0031C41.5217 42.2738 42.4555 41.6 43.2 41.6C46.4896 41.6 48 49.6736 48 52.4C48 53.5104 47.104 54.4 46 54.4H18Z"
        fill="#5E64EB"
      />
    </svg>
  )
}

FileTypeImage.propTypes = {
  size: PropTypes.number,
}

export default FileTypeImage
