import PropTypes from 'prop-types'
import React from 'react'

const FatalErrorModal = ({text}) => (
  <div className="fatal-error-modal">
    <p>{text}</p>
  </div>
)
FatalErrorModal.propTypes = {
  title: PropTypes.string,
  text: PropTypes.node,
}

export default FatalErrorModal
