import PropTypes from 'prop-types'
import React from 'react'

class FatalErrorModal extends React.Component {
  render() {
    return (
      <div className="fatal-error-modal">
        <p>{this.props.text}</p>
      </div>
    )
  }
}
FatalErrorModal.propTypes = {
  title: PropTypes.string,
  text: PropTypes.node,
}

export default FatalErrorModal
