import React, {useContext} from 'react'
import Check from '../../../../../img/icons/Check'
import {Select} from '../common/Select'
import {CreateProjectContext} from './CreateProjectContext'

export const TmGlossarySelect = () => {
  const {SELECT_HEIGHT, tmKeys, setTmKeys, setOpenSettings} =
    useContext(CreateProjectContext)

  const tmKeyActive = Array.isArray(tmKeys)
    ? tmKeys.filter(({isActive}) => isActive)
    : []

  return (
    <Select
      label={
        <div className="label-tmx-select">
          <span>TM & Glossary</span>
          <span
            aria-label="By updating MyMemory, you are contributing to making MateCat better
        and helping fellow MateCat users improve their translations.
        For confidential projects, we suggest adding a private TM and selecting the Update option in the Settings panel."
            tooltip-position="bottom"
          >
            <span className="icon-info icon" />
          </span>
        </div>
      }
      id="tmx-select"
      name={'tmx-select'}
      maxHeightDroplist={SELECT_HEIGHT}
      showSearchBar={true}
      isDisabled={!tmKeys}
      options={tmKeys}
      multipleSelect={'dropdown'}
      activeOptions={tmKeyActive}
      placeholder={'MyMemory Collaborative TM'}
      checkSpaceToReverse={false}
      onToggleOption={(option) => {
        if (tmKeyActive?.some((item) => item.id === option.id)) {
          setTmKeys((prevState) =>
            prevState.map((tm) =>
              tm.id === option.id
                ? {...tm, isActive: false, r: false, w: false}
                : tm,
            ),
          )
        } else {
          setTmKeys((prevState) =>
            prevState.map((tm) =>
              tm.id === option.id
                ? {...tm, isActive: true, r: true, w: true}
                : tm,
            ),
          )
        }
      }}
    >
      {({index, onClose, name, key, showActiveOptionIcon}) => ({
        ...(index === 0 && {
          beforeRow: (
            <button
              className="button-top-of-list"
              onClick={() => {
                setOpenSettings({isOpen: true})
                onClose()
              }}
            >
              CREATE RESOURCE
              <span className="icon-plus3 icon"></span>
            </button>
          ),
        }),
        row: (
          <div className="tmx-dropdown-row">
            <div>
              <span>{name}</span>
              <span>{key}</span>
            </div>
            {showActiveOptionIcon && <Check size={16} />}
          </div>
        ),
      })}
    </Select>
  )
}
