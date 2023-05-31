import React from 'react'
import PropTypes from 'prop-types'

const Users = ({size = 32}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 36 32">
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M24 24.082v-1.649c2.203-1.241 4-4.337 4-7.432 0-4.971 0-9-6-9s-6 4.029-6 9c0 3.096 1.797 6.191 4 7.432v1.649C13.216 24.637 8 27.97 8 32h28c0-4.03-5.216-7.364-12-7.918z"
      />
      <path
        fillRule="evenodd"
        fill="currentColor"
        d="M10.225 24.854c1.728-1.13 3.877-1.989 6.243-2.513a11.33 11.33 0 0 1-1.265-1.844c-.95-1.726-1.453-3.627-1.453-5.497 0-2.689 0-5.228.956-7.305.928-2.016 2.598-3.265 4.976-3.734C19.153 1.571 17.746 0 14 0 8 0 8 4.029 8 9c0 3.096 1.797 6.191 4 7.432v1.649c-6.784.555-12 3.888-12 7.918h8.719c.454-.403.956-.787 1.506-1.146z"
      />
    </svg>
  )
}

Users.propTypes = {
  size: PropTypes.number,
}

export default Users
