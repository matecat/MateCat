import PropTypes from 'prop-types'
import React from 'react'
import {Button, BUTTON_TYPE} from '../common/Button/Button'

const allowHTML = (string) => {
  return {__html: string}
}

const AlertModal = ({
  text,
  successCallback,
  closeOnSuccess,
  onClose,
  buttonText,
}) => {
  const closeModal = () => {
    successCallback?.()
    if (closeOnSuccess) onClose()
  }

  return (
    <div className="message-modal">
      <div className="matecat-modal-middle">
        <div className={'modal-grid alert_modal'}>
          <div
            className="matecat-modal-body"
            style={{fontSize: '18px'}}
          >
            {typeof text === 'string' ? (
              <p dangerouslySetInnerHTML={allowHTML(text)} />
            ) : (
              text
            )}
          </div>
          <div className="modal-grid__footer">
            <Button
              type={BUTTON_TYPE.PRIMARY}
              onClick={() => closeModal()}
            >
              {buttonText ? buttonText : 'Ok'}
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}

AlertModal.propTypes = {
  text: PropTypes.node,
}

export default AlertModal
