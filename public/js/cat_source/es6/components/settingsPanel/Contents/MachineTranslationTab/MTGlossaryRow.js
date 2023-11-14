import React, {Fragment, useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import Upload from '../../../../../../../img/icons/Upload'
import Trash from '../../../../../../../img/icons/Trash'
import {deleteMemoryGlossary} from '../../../../api/deleteMemoryGlossary/deleteMemoryGlossary'
import {MachineTranslationTabContext} from './MachineTranslationTab'
import {MTGlossaryStatus} from './MTGlossary'
import {importMemoryGlossary} from '../../../../api/importMemoryGlossary/importMemoryGlossary'
import {updateMemoryGlossary} from '../../../../api/updateMemoryGlossary/updateMemoryGlossary'

export const MTGlossaryRow = ({engineId, row, setRows}) => {
  const {setNotification} = useContext(MachineTranslationTabContext)

  const [isActive, setIsActive] = useState(row.isActive)
  const [name, setName] = useState(row.name)
  const [file, setFile] = useState()
  const [isWaitingResult, setIsWaitingResult] = useState(false)

  const statusImport = useRef()

  // user import new one glossary
  useEffect(() => {
    if (!file) return

    const dispatchSuccessfullImportNotification = () => {
      setNotification({
        type: 'success',
        message: 'Glossary import successfull',
      })
      setIsWaitingResult(false)
    }
    const dispatchErrorImportNotification = () => {
      setNotification({
        type: 'error',
        message: 'Glossary import error',
      })
      setIsWaitingResult(false)
    }

    statusImport.current = new MTGlossaryStatus()

    setIsWaitingResult(true)
    importMemoryGlossary({engineId, glossary: file, memoryId: row.id})
      .then((data) => {
        if (data.progress === 1) {
          dispatchSuccessfullImportNotification()
        } else {
          //   start polling to get status
          statusImport.current
            .get({engineId, uuid: data.id})
            .then(() => {
              dispatchSuccessfullImportNotification()
            })
            .catch(() => dispatchErrorImportNotification())
        }
      })
      .catch(() => dispatchErrorImportNotification())

    return () => statusImport.current.cancel()
  }, [file, row.id, engineId, setNotification])

  const onChangeIsActive = (e) => {
    setNotification()
    setIsActive(e.currentTarget.checked)
  }

  const onChangeName = (e) => {
    setNotification()

    const {value} = e.currentTarget ?? {}
    setName(value)
    if (value)
      setRows((prevState) =>
        prevState.map((glossary) =>
          glossary.id === row.id ? {...glossary, name: value} : glossary,
        ),
      )
  }

  const updateKeyName = () => false
  // updateMemoryGlossary({engineId, memoryId: row.id, name})

  const onChangeFile = (e) => {
    setNotification()
    if (e.target.files) setFile(Array.from(e.target.files)[0])
  }

  const deleteGlossary = () => {
    setNotification()

    setIsWaitingResult(true)
    deleteMemoryGlossary({engineId, memoryId: row.id})
      .then((data) => {
        if (data.id === row.id)
          setRows((prevState) => prevState.filter(({id}) => id !== row.id))
      })
      .catch(() => {
        setNotification({
          type: 'error',
          message: 'Glossary delete error',
        })
      })
      .finally(() => setIsWaitingResult(false))
  }

  return (
    <Fragment>
      <div className="align-center">
        <input
          checked={isActive}
          onChange={onChangeIsActive}
          type="checkbox"
          disabled={isWaitingResult}
        />
      </div>
      <div>
        <input
          className="glossary-row-name-input"
          value={name}
          onChange={onChangeName}
          onBlur={updateKeyName}
          disabled={isWaitingResult}
        />
      </div>
      <div className="glossary-row-import-button">
        <input
          type="file"
          id={`file-import${row.id}`}
          onChange={onChangeFile}
          name="import_file"
          accept=".xls, .xlsx"
          disabled={isWaitingResult}
        />
        <label htmlFor={`file-import${row.id}`} className="grey-button">
          <Upload size={14} />
          Import from glossary
        </label>
      </div>
      <div className="glossary-row-delete">
        <button
          className="grey-button"
          disabled={isWaitingResult}
          onClick={deleteGlossary}
        >
          <Trash size={14} />
        </button>
      </div>
      {isWaitingResult && <div className="spinner"></div>}
    </Fragment>
  )
}

MTGlossaryRow.propTypes = {
  engineId: PropTypes.number,
  row: PropTypes.object,
  setRows: PropTypes.func,
}
