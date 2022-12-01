import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'

export const KeysSelect = ({className = '', onToggleOption = () => false}) => {
  const {keys, selectsActive, setSelectsActive, modifyElement} =
    useContext(TabGlossaryContext)

  const isEmptyKeys = !keys.length

  return (
    <Select
      className={`glossary-select ${className}`}
      name="glossary-term-tm"
      label="Glossary*"
      placeholder="Select a glossary"
      multipleSelect="dropdown"
      showSearchBar={!isEmptyKeys}
      searchPlaceholder="Find a glossary"
      options={keys.length ? keys : [{id: '0', name: '+ Create glossary key'}]}
      activeOptions={selectsActive.keys}
      checkSpaceToReverse={false}
      isDisabled={!!modifyElement}
      onToggleOption={(option) => {
        if (option) {
          const {keys: activeKeys} = selectsActive
          setSelectsActive((prevState) => ({
            ...prevState,
            keys: activeKeys.some((item) => item.id === option.id)
              ? activeKeys.filter((item) => item.id !== option.id)
              : activeKeys.concat([option]),
          }))
        }
        onToggleOption(option)
      }}
    >
      {({name}) => ({
        // customize row with button create glossary key
        ...(isEmptyKeys && {
          row: (
            <button
              className="button-create-glossary-key"
              onClick={() => {
                UI.openLanguageResourcesPanel('tm')
                onToggleOption()
              }}
            >
              {name}
            </button>
          ),
          cancelHandleClick: true,
        }),
      })}
    </Select>
  )
}

KeysSelect.propTypes = {
  className: PropTypes.string,
  onToggleOption: PropTypes.func,
}
