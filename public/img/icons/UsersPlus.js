import React from 'react'
import PropTypes from 'prop-types'

const UsersPlus = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M9.5 4a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm-5 3a5 5 0 1 1 10 0 5 5 0 0 1-10 0Zm10.073-4.084a1 1 0 0 1 1.302-.552 5.001 5.001 0 0 1 0 9.272 1 1 0 0 1-.75-1.854 3.001 3.001 0 0 0 0-5.564 1 1 0 0 1-.552-1.302ZM7.964 14H12a1 1 0 1 1 0 2H8c-.946 0-1.605 0-2.12.036-.507.034-.803.099-1.028.192a3 3 0 0 0-1.624 1.624c-.093.225-.158.52-.192 1.027C3 19.395 3 20.054 3 21a1 1 0 1 1-2 0v-.035c0-.902 0-1.63.04-2.222.042-.608.13-1.147.34-1.656a5 5 0 0 1 2.707-2.706c.51-.212 1.048-.3 1.656-.34C6.335 14 7.063 14 7.964 14ZM19 14a1 1 0 0 1 1 1v2h2a1 1 0 1 1 0 2h-2v2a1 1 0 1 1-2 0v-2h-2a1 1 0 1 1 0-2h2v-2a1 1 0 0 1 1-1Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

UsersPlus.propTypes = {
  size: PropTypes.number,
}

export default UsersPlus
