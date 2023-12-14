import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import Trash from '../../../../../../../img/icons/Trash'

export const MTRow = ({row, deleteMT, onCheckboxClick}) => {
  const {activeMTEngine} = useContext(SettingsPanelContext)

  return (
    <>
      <div>
        {row.name}
        {row.name === 'MyMemory' && (
          <>
            {' '}
            (
            <a href="https://guides.matecat.com/my" target="_blank">
              Details
            </a>
            )
          </>
        )}
        {row.name === 'ModernMT' && (
          <>
            {' '}
            (
            <a
              href="https://guides.matecat.com/modernmt-mmt-plug-in"
              target="_blank"
            >
              Details
            </a>
            )
          </>
        )}
      </div>
      <div>{row.description}</div>
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

MTRow.propTypes = {
  row: PropTypes.object.isRequired,
  deleteMT: PropTypes.func,
  onCheckboxClick: PropTypes.func,
}
