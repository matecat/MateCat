import React, {Fragment, useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../SettingsPanelContext'

export const TmKeyRow = ({row}) => {
  const {tmKeys, setTmKeys} = useContext(SettingsPanelContext)

  const [isLookup, setIsLookup] = useState(row.isActive ?? false)
  const [isUpdating, setIsUpdating] = useState(row.isActive ?? false)
  const [name, setName] = useState(row.name)

  const isMMSharedKey = row.id === 'mmSharedKey'

  const onChangeIsLookup = (e) => {
    const isLookup = e.currentTarget.checked

    updateContextRow({isLookup, isUpdating})
    setIsLookup(isLookup)
  }

  const onChangeIsUpdating = (e) => {
    const isUpdating = e.currentTarget.checked

    updateContextRow({isLookup, isUpdating})
    setIsUpdating(isUpdating)
  }

  const updateContextRow = ({isLookup, isUpdating}) => {
    setTmKeys((prevState) =>
      prevState.map((tm) =>
        tm.id === row.id
          ? {
              ...tm,
              isActive: isLookup
                ? isLookup
                : !isLookup && !isUpdating
                ? false
                : true,
            }
          : tm,
      ),
    )
  }

  const isMMSharedUpdateChecked = !tmKeys.some(({isActive}) => isActive)

  return (
    <Fragment>
      <div className="tm-key-lookup">
        <input checked={isLookup} onChange={onChangeIsLookup} type="checkbox" />
      </div>
      <div className="tm-key-update">
        {row.isActive && (
          <input
            checked={
              isMMSharedKey ? isUpdating && isMMSharedUpdateChecked : isUpdating
            }
            onChange={onChangeIsUpdating}
            type="checkbox"
            disabled={isMMSharedKey}
          />
        )}
      </div>
      <input
        className="tm-key-row-name"
        value={name}
        onChange={(e) => setName(e.currentTarget.value)}
      ></input>
      <span>I</span>
      <button>Import TMX</button>
    </Fragment>
  )
}

TmKeyRow.propTypes = {
  row: PropTypes.object.isRequired,
}
