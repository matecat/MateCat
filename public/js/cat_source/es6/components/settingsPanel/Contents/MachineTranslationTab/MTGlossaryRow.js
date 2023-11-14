import React, {Fragment, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import Upload from '../../../../../../../img/icons/Upload'
import Trash from '../../../../../../../img/icons/Trash'

export const MTGlossaryRow = ({row, setRows}) => {
  const [isActive, setIsActive] = useState(row.isActive)
  const [name, setName] = useState(row.name)
  const [file, setFile] = useState()

  // user import new one glossary
  useEffect(() => {
    if (!file) return
    console.log('call import api')
  }, [file])

  const onChangeIsActive = (e) => setIsActive(e.currentTarget.checked)

  const onChangeName = (e) => {
    const {value} = e.currentTarget ?? {}
    setName(value)
    if (value)
      setRows((prevState) =>
        prevState.map((glossary) =>
          glossary.id === row.id ? {...glossary, name: value} : glossary,
        ),
      )
  }

  const updateKeyName = () => {
    // call api to update name
  }

  const onChangeFile = (e) => {
    if (e.target.files) setFile(Array.from(e.target.files)[0])
  }

  return (
    <Fragment>
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
          className="glossary-row-name-input"
          value={name}
          onChange={onChangeName}
          onBlur={updateKeyName}
        />
      </div>
      <div className="glossary-row-import-button">
        <input
          type="file"
          id={`file-import${row.id}`}
          onChange={onChangeFile}
          name="import_file"
          accept=".xls, .xlsx"
        />
        <label htmlFor={`file-import${row.id}`} className="grey-button">
          <Upload size={14} />
          Import from glossary
        </label>
      </div>
      <div className="glossary-row-delete">
        <button className="grey-button">
          <Trash size={14} />
        </button>
      </div>
    </Fragment>
  )
}

MTGlossaryRow.propTypes = {
  row: PropTypes.object,
  setRows: PropTypes.func,
}
