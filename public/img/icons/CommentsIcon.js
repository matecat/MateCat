import React from 'react'
import PropTypes from 'prop-types'

const CommentsIcon = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10a10 10 0 0 1-3.76-.732l-.23-.088-.183.03-3.558.592-.029.005c-.16.027-.34.057-.496.069-.17.012-.44.017-.727-.106a1.5 1.5 0 0 1-.787-.787 1.5 1.5 0 0 1-.106-.727c.012-.156.042-.336.069-.496l.005-.03.593-3.557.03-.188-.005-.014-.084-.21A10 10 0 0 1 2 12m10-8a8 8 0 0 0-7.415 11.008l.018.045c.061.15.134.327.17.494.035.15.05.282.05.437 0 .17-.03.34-.053.48l-.006.038-.547 3.281 3.281-.547.039-.006c.138-.023.31-.052.48-.052.154 0 .286.014.436.048a3.3 3.3 0 0 1 .539.19A8 8 0 1 0 12 4M7 9.5a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2H8a1 1 0 0 1-1-1M7 13a1 1 0 0 1 1-1h7a1 1 0 1 1 0 2H8a1 1 0 0 1-1-1"
        clipRule="evenodd"
      />
    </svg>
  )
}

CommentsIcon.propTypes = {
  size: PropTypes.number,
}

export default CommentsIcon
