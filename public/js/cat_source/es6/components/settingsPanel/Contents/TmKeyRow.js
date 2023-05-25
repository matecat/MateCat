import React, {Fragment, useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../SettingsPanelContext'

export const TmKeyRow = ({row}) => {
  const {tmKeys, setTmKeys} = useContext(SettingsPanelContext)

  const [isLookup, setIsLookup] = useState(row.r ?? false)
  const [isUpdating, setIsUpdating] = useState(row.w ?? false)
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
              r: isLookup,
              w: !tm.isActive ? isLookup : isUpdating,
            }
          : tm,
      ),
    )
  }

  const onChangeName = (e) => {
    const {value: name} = e.currentTarget ?? {}
    if (name) {
      setName(name)
      setTmKeys((prevState) =>
        prevState.map((tm) => (tm.id === row.id ? {...tm, name} : tm)),
      )
    }
  }

  const isMMSharedUpdateChecked = !tmKeys.some(({w}) => w)

  const isOwner = true
  const iconClasses = isMMSharedKey
    ? 'icon-earth icon-owner-public'
    : !row.is_shared
    ? 'icon-lock icon-owner-private'
    : ''

  return (
    <Fragment>
      <div className="tm-key-lookup align-center">
        <input checked={isLookup} onChange={onChangeIsLookup} type="checkbox" />
      </div>
      <div className="tm-key-update align-center">
        {row.isActive && (
          <input
            checked={isMMSharedKey ? isMMSharedUpdateChecked : isUpdating}
            onChange={onChangeIsUpdating}
            type="checkbox"
            disabled={isMMSharedKey}
          />
        )}
      </div>
      <input
        className={`tm-key-row-name${
          isMMSharedKey ? ' tm-key-row-name-disabled' : ''
        }`}
        value={name}
        onChange={onChangeName}
        disabled={isMMSharedKey}
      ></input>
      <div
        className={`align-center${isOwner ? ' icon-owner' : ''} ${iconClasses}`}
      ></div>
      {!isMMSharedKey && (
        <div className="align-center">
          <button className="settings-panel-button">Import TMX</button>
        </div>
      )}
    </Fragment>
  )
}

TmKeyRow.propTypes = {
  row: PropTypes.object.isRequired,
}
