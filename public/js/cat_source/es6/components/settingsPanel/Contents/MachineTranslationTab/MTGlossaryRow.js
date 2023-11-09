import React, {Fragment, useState} from 'react'
import PropTypes from 'prop-types'
import Upload from '../../../../../../../img/icons/Upload'
import Trash from '../../../../../../../img/icons/Trash'

export const MTGlossaryRow = ({row, setRows}) => {
  const [isActive, setIsActive] = useState(row.isActive)
  const [name, setName] = useState(row.name)

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
        <button className="grey-button">
          <Upload size={14} />
          Import from glossary
        </button>
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
