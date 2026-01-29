import React from 'react'
import PropTypes from 'prop-types'

const Merge = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M12 1a1 1 0 0 1 1 1v4.086l2.293-2.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 1.414-1.414L11 6.086V2a1 1 0 0 1 1-1ZM2 12a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1Zm5.293 6.793 4-4a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1-1.414 1.414L13 17.914V22a1 1 0 1 1-2 0v-4.086l-2.293 2.293a1 1 0 0 1-1.414-1.414Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

Merge.propTypes = {
  size: PropTypes.number,
}

export default Merge
