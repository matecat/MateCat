import React, {Fragment, useState} from 'react'
import PropTypes from 'prop-types'
import {SPECIAL_ROWS_ID} from './TranslationMemoryGlossaryTab'

import Close from '../../../../../../../img/icons/Close'
import Checkmark from '../../../../../../../img/icons/Checkmark'

export const TMCreateResourceRow = ({row, setSpecialRows}) => {
  const [isLookup, setIsLookup] = useState(row.r ?? false)
  const [isUpdating, setIsUpdating] = useState(row.w ?? false)
  const [name, setName] = useState(row.name ?? '')
  const [keyCode, setKeyCode] = useState('')

  const onChangeIsLookup = (e) => {
    const isLookup = e.currentTarget.checked
    setIsLookup(isLookup)
    updateRow({isUpdating, isLookup, name})
  }

  const onChangeIsUpdating = (e) => {
    const isUpdating = e.currentTarget.checked
    setIsUpdating(isUpdating)
    updateRow({isUpdating, isLookup, name})
  }

  const onChangeName = (e) => {
    const {value: name} = e.currentTarget ?? {}
    if (name) {
      setName(name)
      updateRow({isUpdating, isLookup, name})
    }
  }

  const onChangeKeyCode = (e) => setKeyCode(e.currentTarget.value)

  const updateRow = ({isLookup, isUpdating, name, keyCode}) => {
    setSpecialRows((prevState) =>
      prevState.map((specialRow) =>
        specialRow.id === row.id
          ? {
              ...specialRow,
              name,
              key: keyCode,
              r: isLookup,
              w: isUpdating,
            }
          : specialRow,
      ),
    )
  }

  const onClose = () =>
    setSpecialRows((prevState) =>
      prevState.filter(
        ({id}) =>
          id !== SPECIAL_ROWS_ID.addSharedResource &&
          id !== SPECIAL_ROWS_ID.newResource,
      ),
    )

  return (
    <Fragment>
      <div className="tm-key-lookup align-center">
        <input checked={isLookup} onChange={onChangeIsLookup} type="checkbox" />
      </div>
      <div className="tm-key-update align-center">
        <input
          checked={isUpdating}
          onChange={onChangeIsUpdating}
          type="checkbox"
        />
      </div>
      <div>
        <input
          placeholder="Please insert a name for the resource"
          className="tm-key-create-resource-row-input"
          value={name}
          onChange={onChangeName}
        ></input>
      </div>
      <div>
        {row.id === SPECIAL_ROWS_ID.addSharedResource && (
          <input
            placeholder="Add the shared key here"
            className="tm-key-create-resource-row-input"
            value={keyCode}
            onChange={onChangeKeyCode}
          ></input>
        )}
      </div>
      <div />
      <div className="translation-memory-glossary-tab-buttons-group align-center">
        <button className="ui primary button settings-panel-button-icon tm-key-create-resource-row-button">
          <Checkmark size={16} />
          Confirm
        </button>
        <button
          className="ui button orange tm-key-create-resource-row-button"
          onClick={onClose}
        >
          <Close />
        </button>
      </div>
    </Fragment>
  )
}

TMCreateResourceRow.propTypes = {
  row: PropTypes.object.isRequired,
  setSpecialRows: PropTypes.func.isRequired,
}
