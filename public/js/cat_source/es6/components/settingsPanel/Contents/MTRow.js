import React, {useContext} from 'react'
import {SettingsPanelContext} from '../SettingsPanelContext'

export const MTRow = ({row, deleteMT}) => {
  const {activeMTEngine, setActiveMTEngine} = useContext(SettingsPanelContext)

  const activateMT = () => {
    setActiveMTEngine(row)
  }

  return (
    <>
      <div>{row.name}</div>
      <div>{row.description}</div>
      {!config.is_cattool && (
        <div>
          <input
            type="checkbox"
            checked={row.id === activeMTEngine.id ? true : false}
            onChange={activateMT}
          ></input>
        </div>
      )}
      {!row.default && !config.is_cattool && (
        <div>
          <button className="settings-panel-button" onClick={deleteMT}>
            Delete
          </button>
        </div>
      )}
    </>
  )
}
