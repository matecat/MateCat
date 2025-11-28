import React from 'react'
import PropTypes from 'prop-types'

const Share = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24">
      <path
        fill="currentColor"
        fillRule="evenodd"
        d="M18 3a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-2.842 4.815a4 4 0 1 0-1.008-1.727L8.842 9.185a4 4 0 1 0 0 5.63l5.309 3.093A4.003 4.003 0 0 0 18 23a4 4 0 1 0-2.839-6.818l-5.31-3.095a4.003 4.003 0 0 0 0-2.175l5.307-3.097ZM7.7 10.945a.973.973 0 0 0 .056.096c.155.285.244.612.244.959a1.99 1.99 0 0 1-.3 1.055A1.999 1.999 0 0 1 4 12a2 2 0 0 1 3.7-1.055Zm8.515 7.151a1.012 1.012 0 0 0 .123-.209 2 2 0 1 1-.122.209Z"
        clipRule="evenodd"
      />
    </svg>
  )
}

Share.propTypes = {
  size: PropTypes.number,
}

export default Share
