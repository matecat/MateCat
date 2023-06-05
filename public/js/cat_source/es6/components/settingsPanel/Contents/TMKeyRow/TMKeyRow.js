import React, {Fragment, useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {SPECIAL_ROWS_ID} from '../TranslationMemoryGlossaryTab'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'

import Earth from '../../../../../../../img/icons/Earth'
import Lock from '../../../../../../../img/icons/Lock'
import Users from '../../../../../../../img/icons/Users'
import Upload from '../../../../../../../img/icons/Upload'
import Download from '../../../../../../../img/icons/Download'
import Share from '../../../../../../../img/icons/Share'
import Trash from '../../../../../../../img/icons/Trash'

export const TMKeyRow = ({row, onExpandRow, setSpecialRows}) => {
  const {tmKeys, setTmKeys, wrapperRef} = useContext(SettingsPanelContext)

  const [isLookup, setIsLookup] = useState(row.r ?? false)
  const [isUpdating, setIsUpdating] = useState(row.w ?? false)
  const [name, setName] = useState(row.name)

  const isMMSharedKey = row.id === SPECIAL_ROWS_ID.defaultTranslationMemory

  const onChangeIsLookup = (e) => {
    const isLookup = e.currentTarget.checked

    updateRow({isLookup, isUpdating})
    setIsLookup(isLookup)
  }

  const onChangeIsUpdating = (e) => {
    const isUpdating = e.currentTarget.checked

    updateRow({isLookup, isUpdating})
    setIsUpdating(isUpdating)
  }

  const updateRow = ({isLookup, isUpdating}) => {
    if (!isMMSharedKey) {
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
    } else {
      setSpecialRows((prevState) =>
        prevState.map((specialRow) =>
          specialRow.id === row.id
            ? {
                ...specialRow,
                r: isLookup,
                w: isUpdating,
              }
            : specialRow,
        ),
      )
    }
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

  const iconDetails = isMMSharedKey
    ? {
        title: 'Public translation memory',
        icon: <Earth size={16} />,
      }
    : !row.is_shared
    ? {
        title: 'Private resource. Share it from the dropdown menu',
        icon: <Lock size={16} />,
      }
    : {
        title:
          'Shared resource. Select Share resource from the dropdown menu to see owners',
        icon: <Users size={16} />,
      }

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
      <div>
        <input
          className={`tm-key-row-name${
            isMMSharedKey ? ' tm-key-row-name-disabled' : ''
          }`}
          value={name}
          onChange={onChangeName}
          disabled={isMMSharedKey}
        ></input>
      </div>
      <div>{row.key}</div>
      <div title={iconDetails.title} className="align-center tm-key-row-icons">
        {iconDetails.icon}
      </div>
      {!isMMSharedKey && (
        <div className="align-center">
          <MenuButton
            label="Import TMX"
            onClick={() => onExpandRow({row, shouldExpand: true})}
            className="tm-key-row-menu-button"
            itemsTarget={wrapperRef.current}
          >
            <MenuButtonItem>
              <div className="tm-key-row-button-item">
                <Upload size={20} /> Import Glossary
              </div>
            </MenuButtonItem>
            <MenuButtonItem>
              <div className="tm-key-row-button-item">
                <Download size={20} /> Export TMX
              </div>
            </MenuButtonItem>
            <MenuButtonItem>
              <div className="tm-key-row-button-item">
                <Download size={20} /> Export Glossary
              </div>
            </MenuButtonItem>
            <MenuButtonItem>
              <div className="tm-key-row-button-item">
                <Share size={20} /> Share resource
              </div>
            </MenuButtonItem>
            <MenuButtonItem>
              <div className="tm-key-row-button-item">
                <Trash size={20} /> Delete resource
              </div>
            </MenuButtonItem>
          </MenuButton>
        </div>
      )}
    </Fragment>
  )
}

TMKeyRow.propTypes = {
  row: PropTypes.object.isRequired,
  onExpandRow: PropTypes.func.isRequired,
  setSpecialRows: PropTypes.func.isRequired,
}
