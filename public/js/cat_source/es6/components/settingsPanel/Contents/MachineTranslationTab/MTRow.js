import React, {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'

export const MTRow = ({row, deleteMT}) => {
  const {activeMTEngine, setActiveMTEngine} = useContext(SettingsPanelContext)

  const activateMT = () => {
    setActiveMTEngine(row)
  }

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
            checked={row.id === activeMTEngine.id ? true : false}
            onChange={activateMT}
          ></input>
        </div>
      )}
      {!row.default && !config.is_cattool && (
        <div className="settings-panel-cell-center">
          <button className="settings-panel-button" onClick={deleteMT}>
            Delete
          </button>
        </div>
      )}
      {config.is_cattool && row.id === activeMTEngine.id && (
        <>
          <div></div>
          <div className="settings-panel-cell-center">Enabled</div>
        </>
      )}
    </>
  )
}
