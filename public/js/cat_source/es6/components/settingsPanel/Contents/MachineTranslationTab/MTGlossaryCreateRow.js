import React, {useState} from 'react'
import PropTypes from 'prop-types'
import Upload from '../../../../../../../img/icons/Upload'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'
import {MT_GLOSSARY_CREATE_ROW_ID} from './MTGlossary'

export const MTGlossaryCreateRow = ({row, setRows}) => {
  const [isActive, setIsActive] = useState(row.isActive)
  const [name, setName] = useState(row.name ?? '')
  const [file, setFile] = useState()
  const [submitCheckErrors, setSubmitCheckErrors] = useState()

  const onChangeIsActive = (e) => {
    setIsActive(e.currentTarget.checked)
    setSubmitCheckErrors(undefined)
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
    setSubmitCheckErrors(undefined)
  }

  const onChangeFile = (e) => {
    if (e.target.files) setFile(Array.from(e.target.files)[0])
    setSubmitCheckErrors(undefined)
  }

  const createNewGlossary = () => {
    console.log(name, file)
  }

  const onSubmit = (e) => {
    e.preventDefault()
    const isValid = validateForm()
    if (!isValid) return

    createNewGlossary()
  }

  const validateForm = () => {
    setSubmitCheckErrors(Symbol())
    if (!name || !file) return false

    return true
  }

  const onReset = () =>
    setRows((prevState) =>
      prevState.filter(({id}) => id !== MT_GLOSSARY_CREATE_ROW_ID),
    )

  const inputNameClasses = `glossary-row-name-input ${
    typeof submitCheckErrors === 'symbol' && !name ? ' error' : ''
  }`

  const fileNameClasses = `grey-button ${
    typeof submitCheckErrors === 'symbol' && !file ? ' error' : ''
  }`

  return (
    <form className="settings-panel-row-content" onSubmit={onSubmit}>
      <div className="align-center">
        <input
          checked={isActive}
          onChange={onChangeIsActive}
          type="checkbox"
          title=""
        />
      </div>
      <div>
        <input
          className={inputNameClasses}
          placeholder="Please insert a name for the glossary"
          value={name}
          onChange={onChangeName}
        />
      </div>
      <div className="glossary-row-import-button">
        <input
          type="file"
          id="file-import"
          onChange={onChangeFile}
          name="import_file"
          accept=".xls, .xlsx"
        />
        <label htmlFor="file-import" className={fileNameClasses}>
          <Upload size={14} />
          Import .xls
        </label>

        <button
          className="ui primary button settings-panel-button-icon small-row-button"
          type="submit"
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
        >
          <Close />
        </button>
      </div>
    </form>
  )
}

MTGlossaryCreateRow.propTypes = {
  row: PropTypes.object,
  setRows: PropTypes.func,
}
