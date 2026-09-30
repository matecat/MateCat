import React from 'react'
import PropTypes from 'prop-types'

const FileTypeFolder = ({size = 24}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M54.4001 60.8H9.60012C4.29772 60.8 0.00012207 56.5024 0.00012207 51.2V12.8H54.4001C59.7025 12.8 64.0001 17.0976 64.0001 22.4V51.2C64.0001 56.5024 59.7025 60.8 54.4001 60.8Z"
        fill="#FEDF80"
      />
      <path
        d="M28.8001 12.8H6.10352e-05V9.60001C6.10352e-05 6.06401 2.86406 3.20001 6.40006 3.20001H20.9857C23.7409 3.20001 26.1857 4.96321 27.0561 7.57761L28.8001 12.8Z"
        fill="#D9943E"
      />
    </svg>
  )
}

FileTypeFolder.propTypes = {
  size: PropTypes.number,
}

export default FileTypeFolder
