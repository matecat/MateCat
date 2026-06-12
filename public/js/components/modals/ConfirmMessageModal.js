import PropTypes from 'prop-types'
import React from 'react'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'

class ConfirmMessageModal extends React.Component {
  allowHTML(string) {
    return {__html: string}
  }

  render() {
    return (
      <div className="message-modal">
        <div className="matecat-modal-middle">
          <div className={'ui one column grid ' + this.props.modalName}>
            <div className="column left aligned" style={{fontSize: '18px'}}>
              {typeof this.props.text === 'string' ? (
                <p dangerouslySetInnerHTML={this.allowHTML(this.props.text)} />
              ) : (
                this.props.text
              )}
            </div>
            <div className="buttons-container">
              {this.props.cancelCallback || this.props.cancelText ? (
                <Button
                  type={BUTTON_TYPE.DEFAULT}
                  mode={BUTTON_MODE.OUTLINE}
                  onClick={() => {
                    if (this.props.closeOnSuccess) this.props.onClose()
                    this.props.cancelCallback?.()
                  }}
                >
                  {this.props.cancelText ? this.props.cancelText : 'Cancel'}
                </Button>
              ) : (
                ''
              )}
              {this.props.warningCallback ? (
                <Button
                  type={BUTTON_TYPE.WARNING}
                  onClick={() => {
                    if (this.props.closeOnSuccess) this.props.onClose()
                    this.props.warningCallback?.()
                  }}
                >
                  {this.props.warningText}
                </Button>
              ) : (
                ''
              )}
              {this.props.successCallback || this.props.successText ? (
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  onClick={() => {
                    if (this.props.closeOnSuccess) this.props.onClose()
                    this.props.successCallback?.()
                  }}
                >
                  {this.props.successText ? this.props.successText : 'Confirm'}
                </Button>
              ) : (
                ''
              )}
            </div>
          </div>
        </div>
      </div>
    )
  }
}
ConfirmMessageModal.propTypes = {
  text: PropTypes.node,
}

export default ConfirmMessageModal
