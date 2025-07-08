import PropTypes from 'prop-types'
import React from 'react'

class SuccessModal extends React.Component {
  render() {
    return (
      <div className="success-modal">
        <p>{this.props.text}</p>
      </div>
    )
  }
}
SuccessModal.propTypes = {
  title: PropTypes.string,
  text: PropTypes.string,
}

export default SuccessModal
