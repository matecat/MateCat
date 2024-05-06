import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import Upload from '../../../../../../../../img/icons/Upload'
import Checkmark from '../../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../../img/icons/Close'
import {MTGlossaryStatus, MT_GLOSSARY_CREATE_ROW_ID} from './MTGlossary'
import {MachineTranslationTabContext} from '..'
import {createMemoryAndImportGlossary} from '../../../../../api/createMemoryAndImportGlossary/createMemoryAndImportGlossary'
import LabelWithTooltip from '../../../../common/LabelWithTooltip'

export const MTGlossaryCreateRow = ({engineId, row, setRows}) => {
  const {setNotification} = useContext(MachineTranslationTabContext)

  const [isActive, setIsActive] = useState(row.isActive)
  const [name, setName] = useState(row.name ?? '')
  const [file, setFile] = useState()
  const [isWaitingResult, setIsWaitingResult] = useState(false)

  const ref = useRef()
  const statusEntry = useRef()

  useEffect(() => {
    statusEntry.current = new MTGlossaryStatus()
    ref.current.scrollIntoView({behavior: 'smooth', block: 'nearest'})

    return () => statusEntry.current.cancel()
  }, [])

  const onChangeIsActive = (e) => {
    setIsActive(e.currentTarget.checked)
    resetErrors()
  }

  const onChangeName = (e) => {
    const {value} = e.currentTarget ?? {}
    setName(value)
    resetErrors()
  }

  const onChangeFile = (e) => {
    if (e.target.files) setFile(Array.from(e.target.files)[0])
    resetErrors()
  }

  const createNewGlossary = () => {
    createMemoryAndImportGlossary({engineId, glossary: file, name})
      .then((data) => {
        const addNewEntry = (prevState) =>
          prevState.map((row) =>
            row.id === MT_GLOSSARY_CREATE_ROW_ID
              ? {
                  id: data.memory,
                  isActive,
                  name,
                }
              : row,
          )

        if (data.progress === 1) {
          dispatchSuccessfullNotification()
          setRows(addNewEntry)
        } else {
          //   start polling to get status
          statusEntry.current
            .get({engineId, uuid: data.id})
            .then(() => {
              dispatchSuccessfullNotification()
              setRows(addNewEntry)
            })
            .catch(() => dispatchErrorNotification())
        }
      })
      .catch(({errors}) => dispatchErrorNotification(errors))
  }

  const onSubmit = (e) => {
    e.preventDefault()
    const isValid = validateForm()
    if (!isValid) return

    setIsWaitingResult(true)
    createNewGlossary()
  }

  const validateForm = () => {
    if (!name || !file) {
      setNotification({
        type: 'error',
        message: !name ? 'Name mandatory' : 'File mandatory',
      })
      return false
    }

    return true
  }

  const onReset = () => {
    setRows((prevState) =>
      prevState.filter(({id}) => id !== MT_GLOSSARY_CREATE_ROW_ID),
    )
    setNotification()
  }

  const resetErrors = () => setNotification()

  const dispatchSuccessfullNotification = () => {
    setNotification({
      type: 'success',
      message: 'Glossary created succesfully',
    })
    setIsWaitingResult(false)
  }
  const dispatchErrorNotification = (message) => {
    setNotification({
      type: 'error',
      message: message ?? 'Glossary create error',
    })
    setIsWaitingResult(false)
  }

  const inputNameClasses =
    'glossary-row-name-input glossary-row-name-create-input'
  const fileNameClasses = 'grey-button'

  const isFormFilled = file && name

  return (
    <form
      ref={ref}
      className="settings-panel-row-content row-content-create"
      onSubmit={onSubmit}
    >
      <div
        className={`align-center${
          isWaitingResult ? ' row-content-create-glossary-waiting' : ''
        }`}
      >
        <input
          checked={isActive}
          onChange={onChangeIsActive}
          type="checkbox"
          title=""
          disabled
        />
      </div>
      <div
        className={`glossary-row-name ${
          isWaitingResult ? ' row-content-create-glossary-waiting' : ''
        }`}
      >
        <input
          className={inputNameClasses}
          placeholder="Please insert a name for the glossary"
          value={name}
          onChange={onChangeName}
          disabled={isWaitingResult}
        />
        <div className="glossary-row-import-button">
          <input
            type="file"
            id="file-import"
            onChange={onChangeFile}
            name="import_file"
            accept=".xls, .xlsx"
            disabled={isWaitingResult}
          />
          {!file ? (
            <label htmlFor="file-import" className={fileNameClasses}>
              <Upload size={14} />
              Choose file
            </label>
          ) : (
            <LabelWithTooltip>
              <div className="filename">
                <label>{file.name}</label>
              </div>
            </LabelWithTooltip>
          )}
        </div>
      </div>
      <div
        className={`glossary-row-confirm-button${
          isWaitingResult ? ' row-content-create-glossary-waiting' : ''
        }`}
      >
        <button
          className="ui primary button settings-panel-button-icon confirm-button"
          type="submit"
          disabled={isWaitingResult || !isFormFilled}
        >
          <Checkmark size={12} />
          Confirm
        </button>
      </div>
      <div className="glossary-row-delete">
        <button
          className="ui button orange close-button"
          onClick={onReset}
          type="reset"
          disabled={isWaitingResult}
        >
          <Close size={18} />
        </button>
      </div>
      {isWaitingResult && <div className="spinner"></div>}
    </form>
  )
}

MTGlossaryCreateRow.propTypes = {
  engineId: PropTypes.number,
  row: PropTypes.object,
  setRows: PropTypes.func,
}
