import React from 'react'
import PropTypes from 'prop-types'

const Globe = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M3.055 11H7.05a16.3 16.3 0 0 1 2.676-7.71A9.008 9.008 0 0 0 3.055 11ZM12 3.55A14.3 14.3 0 0 0 9.058 11h5.884A14.3 14.3 0 0 0 12 3.55ZM14.942 13A14.3 14.3 0 0 1 12 20.45 14.3 14.3 0 0 1 9.058 13h5.884ZM7.05 13H3.055a9.008 9.008 0 0 0 6.67 7.71A16.3 16.3 0 0 1 7.05 13Zm7.224 7.71A16.3 16.3 0 0 0 16.95 13h3.995a9.008 9.008 0 0 1-6.67 7.71ZM20.945 11H16.95a16.3 16.3 0 0 0-2.676-7.71A9.008 9.008 0 0 1 20.945 11ZM1 12C1 5.925 5.925 1 12 1s11 4.925 11 11-4.925 11-11 11S1 18.075 1 12Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

Globe.propTypes = {
  size: PropTypes.number,
}

export default Globe
