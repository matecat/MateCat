import React from 'react'
import PropTypes from 'prop-types'

const IconArrowRight = ({size = 16}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 16 16">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M7.529 2.862c.26-.26.682-.26.942 0l4.667 4.667c.26.26.26.682 0 .942l-4.667 4.667a.667.667 0 0 1-.942-.943l3.528-3.528H3.333a.667.667 0 0 1 0-1.334h7.724L7.53 3.805a.667.667 0 0 1 0-.943Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

IconArrowRight.propTypes = {
  size: PropTypes.number,
}

export default IconArrowRight
