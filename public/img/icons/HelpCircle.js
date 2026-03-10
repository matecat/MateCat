import React from 'react'
import PropTypes from 'prop-types'

const HelpCircle = ({size = 16}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM1 12C1 5.925 5.925 1 12 1s11 4.925 11 11-4.925 11-11 11S1 18.075 1 12Zm11.258-3.976a2 2 0 0 0-2.225 1.308 1 1 0 1 1-1.886-.664 4 4 0 0 1 7.773 1.333c0 1.53-1.135 2.54-1.945 3.081a8.044 8.044 0 0 1-1.686.848l-.035.013-.011.003-.004.002h-.002c-.002.001-.529-1.583 0 0a1 1 0 0 1-.634-1.896l.016-.006.074-.027a6.051 6.051 0 0 0 1.172-.6c.69-.46 1.055-.95 1.055-1.419v-.001a2 2 0 0 0-1.662-1.975ZM11 17a1 1 0 0 1 1-1h.01a1 1 0 1 1 0 2H12a1 1 0 0 1-1-1Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

HelpCircle.propTypes = {
  size: PropTypes.number,
}

export default HelpCircle
