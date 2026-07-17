import React from 'react'
import PropTypes from 'prop-types'

const RemoveTagsIcon = ({size = 16}) => {
  return (
    <svg width={size} height={size} fill="none">
      <path
        fill="#000"
        fillRule="evenodd"
        d="m6.195 12.471-.333-.333.943-.943.333.334a.667.667 0 1 1-.943.942ZM10.805 5.471a.667.667 0 0 1-.943 0l-1-1a.667.667 0 1 1 .943-.942l1 1c.26.26.26.682 0 .942ZM7.138 3.529c.26.26.26.682 0 .942L3.61 8l1.862 1.862a.667.667 0 1 1-.942.943L2.195 8.47a.667.667 0 0 1 0-.942l4-4c.26-.26.683-.26.943 0ZM8.862 12.471a.667.667 0 0 1 0-.942L12.39 8l-1.195-1.195.943-.943 1.667 1.667a.666.666 0 0 1 0 .942l-4 4a.667.667 0 0 1-.943 0Z"
        clipRule="evenodd"
      />
      <path
        fill="#000"
        fillRule="evenodd"
        d="M12.471 3.529c.26.26.26.682 0 .942l-8 8a.667.667 0 0 1-.942-.942l8-8c.26-.26.682-.26.942 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

RemoveTagsIcon.propTypes = {
  size: PropTypes.number,
}

export default RemoveTagsIcon
