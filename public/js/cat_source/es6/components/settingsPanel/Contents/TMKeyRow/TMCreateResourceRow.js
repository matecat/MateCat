import React, {Fragment, useState} from 'react'
import PropTypes from 'prop-types'

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
      <input
        placeholder="Please insert a name for the resource"
        value={name}
        onChange={onChangeName}
      ></input>
      <div>
        <input
          placeholder="Add the shared key here"
          value={keyCode}
          onChange={onChangeKeyCode}
        ></input>
      </div>
      <div />
      <div>
        <button className="settings-panel-button">Confirm</button>
        <button className="settings-panel-button">Close</button>
      </div>
    </Fragment>
  )
}

TMCreateResourceRow.propTypes = {
  row: PropTypes.object.isRequired,
  setSpecialRows: PropTypes.func.isRequired,
}
