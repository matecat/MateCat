import React from 'react'
import PropTypes from 'prop-types'

import Checkmark from '../../../../../img/icons/Checkmark'
import Close from '../../../../../img/icons/Close'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../../../common/Button/Button'

export const DeleteResource = ({row, onClose, onConfirm, footerContent}) => {
  const onClickConfirm = () => {
    onConfirm()
  }

  const onClickClose = () => {
    onClose()
  }

  return (
    <div className="translation-memory-glossary-tab-delete">
      <div
        className={`action-form ${footerContent ? 'action-form-remove-from' : ''}`}
      >
        <div>
          <span>
            Do you really want to delete this resource (<b>{row.name}</b>) from
            your Matecat account?
          </span>
          {footerContent}
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.SMALL}
            onClick={onClickConfirm}
          >
            <Checkmark size={12} />
            Confirm
          </Button>

          <Button
            type={BUTTON_TYPE.WARNING}
            size={BUTTON_SIZE.ICON_SMALL}
            onClick={onClickClose}
          >
            <Close size={18} />
          </Button>
        </div>
      </div>
    </div>
  )
}

DeleteResource.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
  onConfirm: PropTypes.func,
  footerContent: PropTypes.node,
}
