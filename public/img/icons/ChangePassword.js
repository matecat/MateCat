import React from 'react'
import PropTypes from 'prop-types'

const ChangePassword = ({size = 24}) => {
  return (
    <svg width={size} height={size} viewBox="0 0 16 16" fill="none">
      <path
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="1.333"
        d="M5.457 13.482a6.49 6.49 0 0 0 5.673-.287C14.123 11.54 15.149 7.87 13.42 5l-.184-.305M2.58 11C.851 8.13 1.877 4.46 4.87 2.805a6.49 6.49 0 0 1 5.673-.287M1 11.058l2.012.517.539-1.928m8.898-3.294.54-1.929L15 4.941"
      />
      <path
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="1.333"
        d="M9.333 7.467v-1.4c0-.774-.597-1.4-1.333-1.4s-1.333.626-1.333 1.4v1.4m-.115 3.2h2.896c.426 0 .64 0 .803-.088.143-.076.26-.199.333-.35.083-.17.083-.394.083-.842v-.64c0-.448 0-.672-.083-.844a.782.782 0 0 0-.333-.35c-.163-.086-.377-.086-.803-.086H6.552c-.426 0-.64 0-.803.087a.782.782 0 0 0-.333.35c-.083.17-.083.395-.083.843v.64c0 .448 0 .672.083.843.073.15.19.273.333.35.163.087.377.087.803.087Z"
      />
    </svg>
  )
}

ChangePassword.propTypes = {
  size: PropTypes.number,
}

export default ChangePassword
