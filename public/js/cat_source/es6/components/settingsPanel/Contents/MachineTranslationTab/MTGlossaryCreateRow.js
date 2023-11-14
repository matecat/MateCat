import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import Upload from '../../../../../../../img/icons/Upload'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'
import {MTGlossaryStatus, MT_GLOSSARY_CREATE_ROW_ID} from './MTGlossary'
import {MachineTranslationTabContext} from './'
import {createMemoryAndImportGlossary} from '../../../../api/createMemoryAndImportGlossary/createMemoryAndImportGlossary'
import {MTGlossaryRow} from './MTGlossaryRow'

export const MTGlossaryCreateRow = ({engineId, row, setRows}) => {
  const {setNotification} = useContext(MachineTranslationTabContext)

  const [isActive, setIsActive] = useState(row.isActive)
  const [name, setName] = useState(row.name ?? '')
  const [file, setFile] = useState()
  const [submitCheckErrors, setSubmitCheckErrors] = useState()
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
    if (value)
      setRows((prevState) =>
        prevState.map((glossary) =>
          glossary.id === row.id ? {...glossary, name: value} : glossary,
        ),
      )
    resetErrors()
  }

  const onChangeFile = (e) => {
    if (e.target.files) setFile(Array.from(e.target.files)[0])
    resetErrors()
  }

  const createNewGlossary = () => {
    console.log(name, file)
    createMemoryAndImportGlossary({engineId, glossary: file, name})
      .then((data) => {
        const addNewEntry = (prevState) =>
          prevState.map((row) =>
            row.id === MT_GLOSSARY_CREATE_ROW_ID
              ? {
                  id: data.memory,
                  isActive,
                  name: row.name,
                  node: (
                    <MTGlossaryRow
                      key={data.memory}
                      {...{
                        row: {id: data.memory, isActive, name: row.name},
                        setRows,
                      }}
                    />
                  ),
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
      .catch(() => dispatchErrorNotification())
  }

  const onSubmit = (e) => {
    e.preventDefault()
    const isValid = validateForm()
    if (!isValid) return

    setIsWaitingResult(true)
    createNewGlossary()
  }

  const validateForm = () => {
    setSubmitCheckErrors(Symbol())
    if (!name || !file) return false

    return true
  }

  const onReset = () => {
    setRows((prevState) =>
      prevState.filter(({id}) => id !== MT_GLOSSARY_CREATE_ROW_ID),
    )
    setNotification()
  }

  const resetErrors = () => {
    setSubmitCheckErrors(undefined)
    setNotification()
  }

  const dispatchSuccessfullNotification = () => {
    setNotification({
      type: 'success',
      message: 'Glossary create successfull',
    })
    setIsWaitingResult(false)
  }
  const dispatchErrorNotification = () => {
    setNotification({
      type: 'error',
      message: 'Glossary create error',
    })
    setIsWaitingResult(false)
  }

  const inputNameClasses = `glossary-row-name-input ${
    typeof submitCheckErrors === 'symbol' && !name ? ' error' : ''
  }`

  const fileNameClasses = `grey-button ${
    typeof submitCheckErrors === 'symbol' && !file ? ' error' : ''
  }`

  return (
    <form ref={ref} className="settings-panel-row-content" onSubmit={onSubmit}>
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
          disabled={isWaitingResult}
        />
      </div>
      <div
        className={`${
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
      </div>
      <div
        className={`glossary-row-import-button${
          isWaitingResult ? ' row-content-create-glossary-waiting' : ''
        }`}
      >
        <input
          type="file"
          id="file-import"
          onChange={onChangeFile}
          name="import_file"
          accept=".xls, .xlsx"
          disabled={isWaitingResult}
        />
        <label htmlFor="file-import" className={fileNameClasses}>
          <Upload size={14} />
          Import .xls
        </label>

        <button
          className="ui primary button settings-panel-button-icon small-row-button"
          type="submit"
          disabled={isWaitingResult}
        >
          <Checkmark size={16} />
          Confirm
        </button>
      </div>
      <div className="glossary-row-delete">
        <button
          className="ui button orange small-row-button"
          onClick={onReset}
          type="reset"
          disabled={isWaitingResult}
        >
          <Close />
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
