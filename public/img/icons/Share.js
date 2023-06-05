import React from 'react'
import PropTypes from 'prop-types'

const Share = ({size = 32}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M27 22a4.985 4.985 0 0 0-3.594 1.526L9.937 16.792a5.035 5.035 0 0 0 0-1.582l13.469-6.734a5 5 0 1 0-1.343-2.683L8.594 12.527A5 5 0 1 0 5 21.001a4.985 4.985 0 0 0 3.594-1.526l13.469 6.734A5 5 0 1 0 27 22z"
      />
    </svg>
  )
}

Share.propTypes = {
  size: PropTypes.number,
}

export default Share
