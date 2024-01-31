import React, {useRef, useContext, useEffect} from 'react'
import PropTypes from 'prop-types'
import useImport, {IMPORT_TYPE} from './hooks/useImport'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'

export const ImportTMX = ({row, onClose}) => {
  const {setIsImportTMXInProgress} = useContext(CreateProjectContext)

  const {files, uuids, status, onSubmit, onReset, onChangeFiles} = useImport({
    type: IMPORT_TYPE.tmx,
    row,
    onClose,
  })

  const formRef = useRef()

  const isFormDisabled = files.length && Array.isArray(uuids)
  const isErrorUpload = status.length && status.some(({uuid}) => !uuid)

  const isCompletedAll = status
    .filter(({errors}) => !errors)
    .every(({isCompleted}) => isCompleted)

  useEffect(() => {
    if (row.isActive && setIsImportTMXInProgress)
      setIsImportTMXInProgress(!isCompletedAll)
  }, [isCompletedAll, setIsImportTMXInProgress, row.isActive])

  const getFileRow = ({uuid, filename}) => {
    const {
      isCompleted,
      percentage = 0,
      errors,
    } = status.find((item) => item.uuid === uuid) ?? {}

    return (
      <li key={uuid}>
        <span className={`filename${errors ? ' filename-error' : ''}`}>
          {filename}
        </span>
        {!errors ? (
          !isCompleted ? (
            <div className="loading-bar">
              <div style={{width: `${percentage}%`}}></div>
            </div>
          ) : (
            <span className="import-completed">Import completed</span>
          )
        ) : (
          <div className="message-error">
            <span>{errors[0].message}</span>
            <button
              className="ui button red"
              onClick={() => formRef.current.reset()}
            >
              <Close size={16} />
            </button>
          </div>
        )}
      </li>
    )
  }

  return (
    <div className="translation-memory-glossary-tab-import">
      <form
        ref={formRef}
        className={`action-form${isErrorUpload ? ' action-form-error' : ''}`}
        onSubmit={onSubmit}
        onReset={onReset}
      >
        <div>
          <span>Select a tmx file to import</span>
          <input
            type="file"
            onChange={onChangeFiles}
            name="uploaded_file[]"
            accept=".tmx"
            disabled={isFormDisabled}
          />
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          {files.length > 0 && (
            <button
              type="submit"
              className="ui primary button settings-panel-button-icon confirm-button"
              disabled={isFormDisabled || isErrorUpload}
            >
              <Checkmark size={12} />
              Confirm
            </button>
          )}

          <button
            type="reset"
            className="ui button orange close-button"
            disabled={isFormDisabled}
          >
            <Close size={18} />
          </button>
        </div>
      </form>
      {uuids?.length > 0 && (
        <div className="import-files">
          <ul>{uuids.map(getFileRow)}</ul>
        </div>
      )}
    </div>
  )
}

ImportTMX.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
