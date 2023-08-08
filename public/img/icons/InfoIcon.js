import React from 'react'
import PropTypes from 'prop-types'

const InfoIcon = ({size = 16}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      fillRule="evenodd"
      fill="currentColor"
    >
      <path d="M14 9.5c0-.825.675-1.5 1.5-1.5h1c.825 0 1.5.675 1.5 1.5v1c0 .825-.675 1.5-1.5 1.5h-1c-.825 0-1.5-.675-1.5-1.5v-1zM20 24h-8v-2h2v-6h-2v-2h6v8h2z" />
      <path d="M16 0C7.163 0 0 7.163 0 16s7.163 16 16 16 16-7.163 16-16S24.837 0 16 0zm0 29C8.82 29 3 23.18 3 16S8.82 3 16 3s13 5.82 13 13-5.82 13-13 13z" />
    </svg>
  )
}

InfoIcon.propTypes = {
  size: PropTypes.number,
}

export default InfoIcon
