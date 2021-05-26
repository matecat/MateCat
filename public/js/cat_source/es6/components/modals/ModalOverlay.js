import React from 'react'
import PropTypes from 'prop-types'
import $ from 'jquery'

export class ModalOverlay extends React.Component {
  closeModal = (event) => {
    event.stopPropagation()

    if (
      $(event.target).closest('.matecat-modal-content').length == 0 ||
      $(event.target).hasClass('close-matecat-modal')
    ) {
      this.props.onClose()
    }
  }

  componentDidMount() {
    document.activeElement.blur()
  }

  render() {
    const {styleContainer, title, children} = this.props

    return (
      <div
        id="matecat-modal-overlay"
        className="matecat-modal-overlay"
        onClick={this.closeModal}
      >
        <div className="matecat-modal-content" style={styleContainer}>
          <div className="matecat-modal-header">
            <div className="modal-logo" />

            <div>
              <h2>{title}</h2>
            </div>

            <div>
              <span
                className="close-matecat-modal x-popup"
                data-testid="close-button"
                onClick={this.closeModal}
              />
            </div>
          </div>

          <div className="matecat-modal-body">{children}</div>
        </div>
      </div>
    )
  }
}

ModalOverlay.propTypes = {
  onClose: PropTypes.func,
  title: PropTypes.string,
}
