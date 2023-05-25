import React, {useContext, useEffect, useState} from 'react'
import IconAdd from '../../icons/IconAdd'
import {Select} from '../../common/Select'
import {ModernMt} from './MtEngines/ModernMt'
import {AltLang} from './MtEngines/AltLang'
import {Apertium} from './MtEngines/Apertium'
import {GoogleTranslate} from './MtEngines/GoogleTranslate'
import {Intento} from './MtEngines/Intento'
import {MicrosoftHub} from './MtEngines/MicrosoftHub'
import {SmartMate} from './MtEngines/SmartMate'
import {Yandex} from './MtEngines/Yandex'
import {addMTEngine} from '../../../api/addMTEngine'
import {SettingsPanelTable} from '../SettingsPanelTable'
import {MTRow} from './MTRow'
import {SettingsPanelContext} from '../SettingsPanelContext'

export const MachineTranslationTab = () => {
  const {mtEngines, setMtEngines, activeMTEngine, setActiveMTEngine} =
    useContext(SettingsPanelContext)

  const [addMTVisible, setAddMTVisible] = useState(false)
  const [activeAddEngine, setActiveAddEngine] = useState()
  const [error, setError] = useState()
  const [MTRows, setMTRows] = useState([])

  const enginesList = [
    {name: 'ModernMt', id: 'mmt', component: ModernMt},
    {name: 'AltLang', id: 'altlang', component: AltLang},
    {name: 'Apertium', id: 'apertium', component: Apertium},
    {
      name: 'Google Translate',
      id: 'googletranslate',
      component: GoogleTranslate,
    },
    {name: 'Intento', id: 'intento', component: Intento},
    {
      name: 'Microsoft Translator Hub',
      id: 'microsofthub',
      component: MicrosoftHub,
    },
    {name: 'SmartMATE', id: 'smartmate', component: SmartMate},
    {name: 'Yandex.Translate', id: 'yandextranslate', component: Yandex},
  ]

  const COLUMNS_TABLE = [
    {name: 'Engine Name'},
    {name: 'Description'},
    {name: 'Use in this project'},
    {name: 'Action'},
  ]

  const addMTEngineRequest = (data) => {
    addMTEngine({
      name: data.name,
      provider: activeAddEngine.id,
      dataMt: data,
    })
      .then((response) => {
        setAddMTVisible(false)
        setError()
        //TODO: ADD TM to the list and activate
      })
      .catch((error) => {
        if (error && error.length) setError(error[0])
      })
  }

  useEffect(() => {
    setMTRows(
      mtEngines
        .filter((row) => row.id !== activeMTEngine.id)
        .map((row, index) => {
          return {
            node: <MTRow key={index} {...{row}} />,
            isDraggable: false,
          }
        }),
    )
  }, [activeMTEngine, mtEngines])

  return (
    <div className="machine-translation-tab">
      {!addMTVisible ? (
        <div className="add-mt-button">
          <button
            className="ui primary button"
            onClick={() => setAddMTVisible(true)}
          >
            <IconAdd /> Add MT engine
          </button>
        </div>
      ) : (
        <div className="add-mt-container">
          <h2>Add MT Engine</h2>
          <div className="add-mt-provider">
            <Select
              placeholder="Choose provider"
              id="mt-engine"
              maxHeightDroplist={100}
              options={enginesList}
              activeOption={activeAddEngine}
              onSelect={(option) => {
                setActiveAddEngine(option)
                setError()
              }}
            />
            <button
              className="ui button orange"
              onClick={() => setAddMTVisible(false)}
            >
              Close
            </button>
          </div>
          {activeAddEngine ? (
            <activeAddEngine.component
              addMTEngine={addMTEngineRequest}
              error={error}
            />
          ) : null}
        </div>
      )}
      <div className="active-mt">
        <h2>Active MT</h2>
        <SettingsPanelTable
          columns={COLUMNS_TABLE}
          rows={[
            {
              node: <MTRow key={'active'} row={activeMTEngine} />,
              isDraggable: false,
            },
          ]}
        />
      </div>
      <div className="inactive-mt">
        <h2>Inactive MT</h2>
        <SettingsPanelTable columns={COLUMNS_TABLE} rows={MTRows} />
      </div>
    </div>
  )
}
