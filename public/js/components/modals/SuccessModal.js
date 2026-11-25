import PropTypes from 'prop-types'
import React from 'react'

const SuccessModal = ({text}) => (
  <div className="success-modal">
    <p>{text}</p>
  </div>
)
SuccessModal.propTypes = {
  title: PropTypes.string,
  text: PropTypes.string,
}

export default SuccessModal
