import React, {useRef} from 'react'
import PropTypes from 'prop-types'
import useExport, {EXPORT_TYPE} from './hooks/useExport'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'

export const ExportGlossary = ({row, onClose}) => {
  const {email, status, onSubmit, onReset, onChange} = useExport({
    type: EXPORT_TYPE.glossary,
    row,
    onClose,
  })

  const formRef = useRef()

  const isFormDisabled = false
  const isErrorExport = status && status.errors
  const isSuccessfullExport = status && status.successfull

  return (
    <div className="translation-memory-glossary-tab-export">
      {!isSuccessfullExport ? (
        <form
          ref={formRef}
          className={`action-form${isErrorExport ? ' action-form-error' : ''}`}
          onSubmit={onSubmit}
          onReset={onReset}
        >
          <div>
            <span>
              We will send a link to download the exported Glossary to this
              email:
            </span>
            <input
              type="email"
              className="translation-memory-glossary-tab-input-text"
              required
              value={email}
              onChange={onChange}
              disabled={isFormDisabled}
            />
          </div>
          <div className="translation-memory-glossary-tab-buttons-group align-center">
            <button
              type="submit"
              className="ui primary button settings-panel-button-icon tm-key-small-row-button"
              disabled={isFormDisabled || isErrorExport}
            >
              <Checkmark size={16} />
              Confirm
            </button>

            <button
              type="reset"
              className="ui button orange tm-key-small-row-button"
              disabled={isFormDisabled}
            >
              <Close />
            </button>
          </div>
        </form>
      ) : (
        <div className="export-successfull">
          <span>You should receive the link at {email}</span>
          <span>Request submitted</span>
        </div>
      )}
    </div>
  )
}

ExportGlossary.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
