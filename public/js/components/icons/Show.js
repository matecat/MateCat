import React from 'react'

import PropTypes from 'prop-types'

const Show = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fillRule="evenodd"
        d="M9.5 8.258a4.5 4.5 0 1 1 5 7.483 4.5 4.5 0 0 1-5-7.483Zm.833 6.236a3 3 0 1 0 3.334-4.988 3 3 0 0 0-3.334 4.988Z"
        fill="currentColor"
      />
      <path
        fillRule="evenodd"
        d="M18.8 6.07a12.517 12.517 0 0 1 4.405 5.675.75.75 0 0 1 0 .51A12.517 12.517 0 0 1 12 20.25 12.517 12.517 0 0 1 .795 12.255a.75.75 0 0 1 0-.51A12.518 12.518 0 0 1 12 3.75c2.445.092 4.809.898 6.8 2.32ZM2.303 12c1.522 3.803 5.722 6.75 9.697 6.75 3.975 0 8.175-2.947 9.698-6.75C20.175 8.197 15.975 5.25 12 5.25c-3.975 0-8.175 2.947-9.697 6.75Z"
        fill="currentColor"
      />
    </svg>
  )
}

Show.propTypes = {
  size: PropTypes.number,
}

export default Show
