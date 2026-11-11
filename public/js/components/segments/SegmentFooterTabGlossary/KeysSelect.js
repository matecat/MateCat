import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'
import SegmentUtils from '../../../utils/segmentUtils'
import CatToolActions from '../../../actions/CatToolActions'
import IconAdd from '../../../../img/icons/IconAdd'

export const KeysSelect = ({
  className = '',
  onToggleOption = () => false,
  disabled = false,
}) => {
  const {keys, selectsActive, setSelectsActive, modifyElement} =
    useContext(TabGlossaryContext)

  const isEmptyKeys = !keys.length

  return (
    <Select
      className={`glossary-select ${className}`}
      dropdownClassName="glossary-domain-subdomain-dropdown"
      isPortalDropdown={true}
      name="glossary-term-tm"
      label="Termbase*"
      placeholder="Select a termbase"
      multipleSelect="dropdown"
      showSearchBar
      searchPlaceholder="Find a termbase"
      options={keys}
      activeOptions={selectsActive.keys}
      checkSpaceToReverse={false}
      isDisabled={!!modifyElement || disabled}
      onToggleOption={(option) => {
        if (option) {
          const {keys: activeKeys} = selectsActive
          const updatedKeys = activeKeys.some((item) => item.id === option.id)
            ? activeKeys.filter((item) => item.id !== option.id)
            : activeKeys.concat([option])

          setSelectsActive((prevState) => ({
            ...prevState,
            keys: updatedKeys,
          }))

          // save to local storage keys selected for actual job
          SegmentUtils.setSelectedKeysGlossary(updatedKeys)
        }
        onToggleOption(option)
      }}
    >
      {({index, onClose}) => ({
        ...(index === 0 &&
          isEmptyKeys && {
            beforeRow: (
              <li
                className="dropdown__option button-create-option"
                onClick={() => {
                  CatToolActions.openSettingsPanel()
                  onToggleOption()
                  onClose()
                }}
              >
                <div className="button-create-option-content">
                  <IconAdd size={18} />
                  <span>Create termbase key</span>
                </div>
              </li>
            ),
          }),
      })}
    </Select>
  )
}

KeysSelect.propTypes = {
  className: PropTypes.string,
  onToggleOption: PropTypes.func,
}
