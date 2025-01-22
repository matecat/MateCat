import React from 'react'
import PropTypes from 'prop-types'

const AddTagsIcon = ({size = 16}) => {
  return (
    <svg width={size} height={size} fill="none">
      <path
        fill="#000"
        fillRule="evenodd"
        d="M5.138 3.529c.26.26.26.682 0 .942L1.61 8l3.53 3.529a.667.667 0 1 1-.944.942l-4-4a.667.667 0 0 1 0-.942l4-4c.26-.26.683-.26.943 0Zm5.057 0c.26-.26.683-.26.943 0l4 4c.26.26.26.682 0 .942l-4 4a.667.667 0 1 1-.943-.942L13.724 8l-3.529-3.529a.667.667 0 0 1 0-.942Z"
        clipRule="evenodd"
      />
      <path
        fill="#000"
        d="M7 3.529c.26-.26.682-.26.943 0l4 4c.26.26.26.682 0 .942l-4 4A.667.667 0 1 1 7 11.53L10.529 8 7 4.471a.667.667 0 0 1 0-.942Z"
      />
    </svg>
  )
}

AddTagsIcon.propTypes = {
  size: PropTypes.number,
}

export default AddTagsIcon
