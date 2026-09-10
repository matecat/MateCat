import PropTypes from 'prop-types'
import React from 'react'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'

const allowHTML = (string) => {
  return {__html: string}
}

const ConfirmMessageModal = (props) => {
  return (
    <div className="message-modal">
      <div className="matecat-modal-middle">
        <div className={'modal-grid ' + props.modalName}>
          <div className="modal-grid__body" style={{fontSize: '18px'}}>
            {typeof props.text === 'string' ? (
              <p dangerouslySetInnerHTML={allowHTML(props.text)} />
            ) : (
              props.text
            )}
          </div>
          <div className="buttons-container">
            {props.cancelCallback || props.cancelText ? (
              <Button
                type={BUTTON_TYPE.DEFAULT}
                mode={BUTTON_MODE.OUTLINE}
                onClick={() => {
                  if (props.closeOnSuccess) props.onClose()
                  props.cancelCallback?.()
                }}
              >
                {props.cancelText ? props.cancelText : 'Cancel'}
              </Button>
            ) : (
              ''
            )}
            {props.warningCallback ? (
              <Button
                type={BUTTON_TYPE.WARNING}
                onClick={() => {
                  if (props.closeOnSuccess) props.onClose()
                  props.warningCallback?.()
                }}
              >
                {props.warningText}
              </Button>
            ) : (
              ''
            )}
            {props.successCallback || props.successText ? (
              <Button
                type={BUTTON_TYPE.PRIMARY}
                onClick={() => {
                  if (props.closeOnSuccess) props.onClose()
                  props.successCallback?.()
                }}
              >
                {props.successText ? props.successText : 'Confirm'}
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
ConfirmMessageModal.propTypes = {
  text: PropTypes.node,
}

export default ConfirmMessageModal
