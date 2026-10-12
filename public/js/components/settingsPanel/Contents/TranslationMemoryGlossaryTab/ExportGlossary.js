import React, {useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import useExport, {EXPORT_TYPE} from './hooks/useExport'

import Checkmark from '../../../../../img/icons/Checkmark'
import Close from '../../../../../img/icons/Close'
import CatToolActions from '../../../../actions/CatToolActions'
import {
  Button,
  BUTTON_HTML_TYPE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../common/Button/Button'

export const ExportGlossary = ({row, onClose}) => {
  const {email, status, onSubmit, onReset} = useExport({
    type: EXPORT_TYPE.glossary,
    row,
    onClose,
  })

  const formRef = useRef()

  const isFormDisabled = false
  const isErrorExport = status && status.errors

  useEffect(() => {
    if (status && status.successfull) {
      const notification = {
        title: 'Request submitted',
        text: `You will receive the link at ${email}`,
        type: 'success',
        position: 'br',
        allowHtml: true,
        timer: 5000,
      }
      CatToolActions.addNotification(notification)
    }
  }, [status])

  return (
    <div className="translation-memory-glossary-tab-export">
      <form
        ref={formRef}
        className={`action-form${isErrorExport ? ' action-form-error' : ''}`}
        onSubmit={onSubmit}
        onReset={onReset}
      >
        <div>
          <span>
            We will send a link to download the exported termbase to your email.
          </span>
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.SMALL}
            htmlType={BUTTON_HTML_TYPE.SUBMIT}
            disabled={isFormDisabled || isErrorExport}
          >
            <Checkmark size={12} />
            Confirm
          </Button>

          <Button
            type={BUTTON_TYPE.WARNING}
            size={BUTTON_SIZE.ICON_SMALL}
            htmlType={BUTTON_HTML_TYPE.RESET}
            disabled={isFormDisabled}
          >
            <Close size={18} />
          </Button>
        </div>
      </form>
    </div>
  )
}

ExportGlossary.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
