import React, {useState} from 'react'
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
const enginesList = [
  {name: 'ModernMt', id: 'mmt', component: <ModernMt />},
  {name: 'AltLang', id: 'altlang', component: <AltLang />},
  {name: 'Apertium', id: 'apertium', component: <Apertium />},
  {
    name: 'googletranslate',
    id: 'Google Translate',
    component: <GoogleTranslate />,
  },
  {name: 'Intento', id: 'intento', component: <Intento />},
  {
    name: 'Microsoft Translator Hub',
    id: 'microsofthub',
    component: <MicrosoftHub />,
  },
  {name: 'SmartMATE', id: 'smartmate', component: <SmartMate />},
  {name: 'Yandex.Translate', id: 'yandextranslate', component: <Yandex />},
]
export const MachineTranslationTab = () => {
  const [addMTVisible, setAddMTVisible] = useState(false)
  const [activeEngine, setActiveEngine] = useState()
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
              showSearchBar={true}
              maxHeightDroplist={100}
              options={enginesList}
              activeOption={activeEngine}
              onSelect={(option) => setActiveEngine(option)}
            />
            <button
              className="ui button orange"
              onClick={() => setAddMTVisible(false)}
            >
              Close
            </button>
          </div>
          {activeEngine ? activeEngine.component : null}
        </div>
      )}
    </div>
  )
}
