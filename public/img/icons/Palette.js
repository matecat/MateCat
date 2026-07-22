import React from 'react'
import PropTypes from 'prop-types'

const Palette = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M12 3a9 9 0 0 0 0 18 2 2 0 0 0 2-2v-.569c0-.397 0-.692.034-.953a4 4 0 0 1 3.444-3.444c.261-.034.556-.034.953-.034H19a2 2 0 0 0 2-2 9 9 0 0 0-9-9ZM1 12C1 5.925 5.925 1 12 1s11 4.925 11 11a4 4 0 0 1-4 4h-.5c-.496 0-.648.002-.761.017a2 2 0 0 0-1.722 1.722c-.015.113-.017.265-.017.761v.5a4 4 0 0 1-4 4C5.925 23 1 18.075 1 12Zm7-5a2 2 0 1 1 4 0 2 2 0 0 1-4 0Zm6 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0Zm-9 4a2 2 0 1 1 4 0 2 2 0 0 1-4 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

Palette.propTypes = {
  size: PropTypes.number,
}

export default Palette
