import React from 'react'
import PropTypes from 'prop-types'

const Refresh = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M17.657 6.343a8 8 0 0 0-13.458 3.878 1 1 0 1 1-1.95-.442 9.958 9.958 0 0 1 2.68-4.85c3.905-3.905 10.237-3.905 14.142 0 .798.798 1.43 1.466 1.929 2.025V4a1 1 0 1 1 2 0v6a1 1 0 0 1-1 1h-6a1 1 0 1 1 0-2h4.126c-.512-.617-1.29-1.478-2.47-2.657Zm3.34 6.682a1 1 0 0 1 .754 1.196 9.959 9.959 0 0 1-2.68 4.85c-3.905 3.905-10.237 3.905-14.142 0A50.862 50.862 0 0 1 3 17.046V20a1 1 0 1 1-2 0v-6a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H3.874c.512.617 1.29 1.479 2.47 2.657A8 8 0 0 0 19.8 13.779a1 1 0 0 1 1.196-.754Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

Refresh.propTypes = {
  size: PropTypes.number,
}

export default Refresh
