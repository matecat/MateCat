import React from 'react'

import PropTypes from 'prop-types'

const ExternalLink = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        d="M15 3V1.5h7.5V9H21V4.06l-6.44 6.44-1.06-1.06L19.94 3H15Z"
        fillRule="evenodd"
        fill="currentColor"
      />
      <path
        d="M3.44 20.56c.281.281.662.44 1.06.44h15a1.502 1.502 0 0 0 1.5-1.5V12h-1.5v7.5h-15v-15H12V3H4.5A1.502 1.502 0 0 0 3 4.5v15c0 .398.159.779.44 1.06Z"
        fillRule="evenodd"
        fill="currentColor"
      />
    </svg>
  )
}

ExternalLink.propTypes = {
  size: PropTypes.number,
}

export default ExternalLink
