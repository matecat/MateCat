import React from 'react'
import PropTypes from 'prop-types'

const SearchFilled = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <path
        d="M11 2C15.9706 2 20 6.02944 20 11C20 13.125 19.2619 15.0766 18.0303 16.6162L21.707 20.293C22.0976 20.6835 22.0976 21.3165 21.707 21.707C21.3165 22.0976 20.6835 22.0976 20.293 21.707L16.6162 18.0303C15.0766 19.2619 13.125 20 11 20C6.02944 20 2 15.9706 2 11C2 6.02944 6.02944 2 11 2ZM11 5C10.4477 5 10 5.44772 10 6C10 6.55228 10.4477 7 11 7C13.2091 7 15 8.79086 15 11C15 11.5523 15.4477 12 16 12C16.5523 12 17 11.5523 17 11C17 7.68629 14.3137 5 11 5Z"
        fill="currentColor"
      />
    </svg>
  )
}

SearchFilled.propTypes = {
  size: PropTypes.number,
}

export default SearchFilled
