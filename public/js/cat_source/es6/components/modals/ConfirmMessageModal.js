import PropTypes from 'prop-types'
import React from 'react'

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
              <p dangerouslySetInnerHTML={this.allowHTML(this.props.text)} />
            </div>
            <div className="column right aligned">
              {this.props.cancelCallback ? (
                <div
                  className="ui button cancel-button"
                  onClick={this.props.cancelCallback}
                >
                  {this.props.cancelText}
                </div>
              ) : (
                ''
              )}
              {this.props.warningCallback ? (
                <div
                  className="ui primary button button-modal warning-button orange margin left-10 right-20"
                  onClick={this.props.warningCallback}
                >
                  {this.props.warningText}
                </div>
              ) : (
                ''
              )}
              {this.props.successCallback ? (
                <div
                  className="ui primary button right floated"
                  onClick={this.props.successCallback}
                >
                  {this.props.successText}
                </div>
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
  text: PropTypes.string,
}

export default ConfirmMessageModal
