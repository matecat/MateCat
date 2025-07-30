import PropTypes from 'prop-types'
import React from 'react'

class AlertModal extends React.Component {
  allowHTML(string) {
    return {__html: string}
  }
  closeModal() {
    this.props.successCallback?.()
    if (this.props.closeOnSuccess) this.props.onClose()
  }
  render() {
    return (
      <div className="message-modal">
        <div className="matecat-modal-middle">
          <div className={'ui one column grid alert_modal'}>
            <div
              className="column left aligned matecat-modal-body"
              style={{fontSize: '18px'}}
            >
              {typeof this.props.text === 'string' ? (
                <p dangerouslySetInnerHTML={this.allowHTML(this.props.text)} />
              ) : (
                this.props.text
              )}
            </div>
            <div className="column right aligned">
              <div
                className="ui primary button right floated"
                onClick={() => this.closeModal()}
              >
                {this.props.buttonText ? this.props.buttonText : 'Ok'}
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}
AlertModal.propTypes = {
  text: PropTypes.node,
}

export default AlertModal
