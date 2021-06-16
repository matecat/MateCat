import React from 'react'
import PropTypes from 'prop-types'

const allowHTML = (__html) => ({__html})

class ConfirmMessageModal extends React.Component {
  render() {
    const {
      modalName,
      text,
      cancelCallback,
      cancelText,
      warningCallback,
      warningText,
      successCallback,
      successText,
    } = this.props

    return (
      <div className="message-modal">
        <div className="matecat-modal-middle">
          <div className={'ui one column grid ' + modalName}>
            <div className="column left aligned" style={{fontSize: '18px'}}>
              <p dangerouslySetInnerHTML={allowHTML(text)} />
            </div>

            <div className="column right aligned">
              {cancelCallback ? (
                <div
                  className="ui button cancel-button"
                  onClick={cancelCallback}
                >
                  {cancelText}
                </div>
              ) : (
                ''
              )}

              {warningCallback ? (
                <div
                  className="ui primary button button-modal warning-button orange margin left-10 right-20"
                  onClick={warningCallback}
                >
                  {warningText}
                </div>
              ) : (
                ''
              )}

              {successCallback ? (
                <div
                  className="ui primary button right floated"
                  onClick={successCallback}
                >
                  {successText}
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
