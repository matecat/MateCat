import React from 'react'
import PropTypes from 'prop-types'

const PencilLine = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M16.293 2.293a3.829 3.829 0 0 1 5.414 5.414l-11.28 11.28-.036.037c-.234.233-.41.41-.61.564a4.006 4.006 0 0 1-.56.365c-.223.12-.456.209-.764.327l-.049.019-.102.04-.067.025a6.75 6.75 0 0 0-.029.011L2.86 22.433a1 1 0 0 1-1.292-1.292L3.7 15.59l.019-.048a6.61 6.61 0 0 1 .327-.764 4 4 0 0 1 .364-.56c.155-.2.332-.376.565-.61l.037-.037 11.28-11.28ZM5.38 16.795l-1.14 2.964 2.964-1.14-1.824-1.824ZM9 17.586l-.142-.142-.021-.022-2.26-2.259-.021-.022-.142-.14.013-.014 11.28-11.28a1.828 1.828 0 1 1 2.586 2.586l-11.28 11.28-.013.013ZM12 21a1 1 0 0 1 1-1h8a1 1 0 0 1 0 2h-8a1 1 0 0 1-1-1Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

PencilLine.propTypes = {
  size: PropTypes.number,
}

export default PencilLine
