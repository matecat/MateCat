import React from 'react'
import PropTypes from 'prop-types'

import Checkmark from '../../../../../img/icons/Checkmark'
import Close from '../../../../../img/icons/Close'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../../../common/Button/Button'

export const DeleteResource = ({row, onClose, onConfirm, type = 'mt'}) => {
  const onClickConfirm = () => {
    onConfirm()
  }

  const onClickClose = () => {
    onClose()
  }

  return (
    <div className="translation-memory-glossary-tab-delete">
      <div className="action-form">
        <div>
          <span>
            Do you really want to delete the
            {type === 'mt' ? ' MT' : ' Glossary'}: (<b>{row.name}</b>)
          </span>
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
  type: PropTypes.oneOf(['mt', 'glossary']),
}
