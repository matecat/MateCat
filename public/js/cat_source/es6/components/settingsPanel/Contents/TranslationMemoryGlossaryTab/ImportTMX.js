import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {uploadTm} from '../../../../api/uploadTm'
import {loadTMX} from '../../../../api/loadTMX'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'
import {useRef} from 'react'

const DELAY_GET_STATUS = 1000

export const ImportTMX = ({row, onClose}) => {
  const [files, setFiles] = useState([])
  const [uuids, setUuids] = useState(undefined)
  const [status, setStatus] = useState([])

  const onCloseRef = useRef()
  onCloseRef.current = onClose

  const {key} = row

  useEffect(() => {
    if (!uuids?.length) return

    let tmOut

    const getStatus = () => {
      uuids.forEach(({uuid, name}) => {
        loadTMX({uuid, key, name})
          .then(({data}) => {
            const dataFiles = Array.isArray(data) ? data : [data]
            const statusFiles = dataFiles.map(
              ({uuid, status, completed, totals}) => ({
                uuid,
                isCompleted: status === 1,
                percentage: totals ? (completed / totals) * 100 : 0,
              }),
            )
            setStatus(statusFiles)

            const isCompletedAll = statusFiles.every(
              ({isCompleted}) => isCompleted,
            )
            if (!isCompletedAll) {
              tmOut = setTimeout(getStatus, DELAY_GET_STATUS)
            } else {
              tmOut = setTimeout(onCloseRef.current, 2000)
            }
          })
          .catch((error) => console.log(error))
      })
    }

    getStatus()

    return () => clearTimeout(tmOut)
  }, [uuids, key])

  const onChangeFiles = (e) => {
    if (e.target.files) setFiles(Array.from(e.target.files))
  }

  const onSubmit = (event) => {
    uploadTm({filesToUpload: files, tmKey: row.key, keyName: row.name})
      .then(({data, errors}) =>
        errors?.length ? onErrorUpload(errors) : setUuids(data.uuids),
      )
      .catch((errors) => onErrorUpload(errors))

    setUuids([])
    event.preventDefault()
  }

  const onReset = () => {
    setFiles([])
    setUuids(undefined)

    if (!files.length) onCloseRef.current()
  }

  const onErrorUpload = (errors) => {
    console.log('upload errors', errors)

    onReset()
  }

  const isFormDisabled = files.length && Array.isArray(uuids)

  const getFileRow = ({uuid, name}) => {
    const {isCompleted, percentage = 0} =
      status.find((item) => item.uuid === uuid) ?? {}

    return (
      <li key={uuid}>
        <span>{name}</span>
        {!isCompleted ? (
          <div className="loading-bar">
            <div style={{width: `${percentage}%`}}></div>
          </div>
        ) : (
          <span className="import-completed">Import completed</span>
        )}
      </li>
    )
  }

  return (
    <div className="translation-memory-glossary-tab-import">
      <form className="import-form" onSubmit={onSubmit} onReset={onReset}>
        <div>
          <span>Select a tmx file to import</span>
          <input
            type="file"
            onChange={onChangeFiles}
            name="uploaded_file[]"
            accept=".tmx"
            multiple="multiple"
            disabled={isFormDisabled}
          />
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          {files.length > 0 && (
            <button
              type="submit"
              className="ui primary button settings-panel-button-icon tm-key-create-resource-row-button"
              disabled={isFormDisabled}
            >
              <Checkmark size={16} />
              Confirm
            </button>
          )}

          <button
            type="reset"
            className="ui button orange tm-key-create-resource-row-button"
            disabled={isFormDisabled}
          >
            <Close />
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
