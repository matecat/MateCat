import React, {useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import Trash from '../../../../../../../img/icons/Trash'
import {Select} from '../../../common/Select'

export const MTDeepLRow = ({row, deleteMT, onCheckboxClick}) => {
  const {activeMTEngine} = useContext(SettingsPanelContext)
  const [formalityOptions, setFormalityOptions] = useState([
    {name: 'Default', id: 'default', isActive: true},
    {name: 'prefer_less', id: 'prefer_less'},
    {name: 'prefer_more', id: 'prefer_more'},
  ])

  const formalitySelect = (
    <Select
      name="formality"
      options={formalityOptions}
      activeOption={formalityOptions.find(({isActive}) => isActive)}
      onSelect={(option) =>
        setFormalityOptions((prevState) =>
          prevState.map((optionItem) => ({
            ...optionItem,
            isActive: optionItem.id === option.id,
          })),
        )
      }
    />
  )

  return (
    <>
      <div>
        {row.name} (
        <a href="https://guides.matecat.com/my" target="_blank">
          Details
        </a>
        )
      </div>
      <div>{row.description}</div>
      {!config.is_cattool && (
        <div className="settings-panel-cell-center">{formalitySelect}</div>
      )}
      {!config.is_cattool && (
        <div className="settings-panel-cell-center">
          <input
            type="checkbox"
            checked={
              activeMTEngine && row.id === activeMTEngine.id ? true : false
            }
            onChange={() => onCheckboxClick(row)}
          ></input>
        </div>
      )}
      {!row.default && !config.is_cattool && (
        <div className="settings-panel-cell-center">
          <button className="grey-button" onClick={deleteMT}>
            <Trash size={12} />
            Delete
          </button>
        </div>
      )}
      {config.is_cattool && activeMTEngine && row.id === activeMTEngine.id && (
        <>
          <div></div>
          <div className="settings-panel-cell-center">Enabled</div>
        </>
      )}
    </>
  )
}

MTDeepLRow.propTypes = {
  row: PropTypes.object.isRequired,
  deleteMT: PropTypes.func,
  onCheckboxClick: PropTypes.func,
}
