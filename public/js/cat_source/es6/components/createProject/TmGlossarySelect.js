import React, {useContext} from 'react'
import Check from '../../../../../img/icons/Check'
import {Select} from '../common/Select'
import {CreateProjectContext} from './CreateProjectContext'
import {orderTmKeys} from '../settingsPanel/Contents/TranslationMemoryGlossaryTab'

export const TmGlossarySelect = () => {
  const {SELECT_HEIGHT, tmKeys, setOpenSettings, modifyingCurrentTemplate} =
    useContext(CreateProjectContext)

  const tmKeyActive = Array.isArray(tmKeys)
    ? tmKeys.filter(({isActive}) => isActive)
    : []

  const hasNoPrivateKeys = Array.isArray(tmKeys) && !tmKeys.length

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
      className={`${hasNoPrivateKeys ? 'select-without-private-keys' : ''}`}
      maxHeightDroplist={SELECT_HEIGHT}
      showSearchBar={true}
      isDisabled={!tmKeys}
      options={tmKeys}
      multipleSelect={'dropdown'}
      activeOptions={tmKeyActive}
      placeholder={'MyMemory Collaborative TM'}
      checkSpaceToReverse={false}
      onToggleOption={(option) => {
        const isKeyAlreadyActive = tmKeyActive?.some(
          (item) => item.id === option.id,
        )
        const updatedKeys = tmKeys.map((tm) =>
          tm.id === option.id
            ? {
                ...tm,
                isActive: !isKeyAlreadyActive,
                r: !isKeyAlreadyActive,
                w: !isKeyAlreadyActive,
              }
            : tm,
        )
        modifyingCurrentTemplate((prevTemplate) => ({
          ...prevTemplate,
          tm: orderTmKeys(
            updatedKeys.filter(({isActive}) => isActive),
            prevTemplate.tm.map(({key}) => key),
          ).map(({id, isActive, ...rest}) => rest), //eslint-disable-line
        }))
      }}
    >
      {({index, onClose, name, key, showActiveOptionIcon}) => ({
        ...(index === 0 && {
          beforeRow: (
            <>
              {hasNoPrivateKeys && (
                <span className="no-private-keys-message">
                  You have no private resources
                </span>
              )}
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
            </>
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
