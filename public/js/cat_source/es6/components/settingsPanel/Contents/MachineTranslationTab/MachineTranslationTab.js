import React, {createContext, useContext, useEffect, useState} from 'react'
import {Select} from '../../../common/Select'
import {ModernMt} from './MtEngines/ModernMt'
import {AltLang} from './MtEngines/AltLang'
import {Apertium} from './MtEngines/Apertium'
import {GoogleTranslate} from './MtEngines/GoogleTranslate'
import {Intento} from './MtEngines/Intento'
import {MicrosoftHub} from './MtEngines/MicrosoftHub'
import {SmartMate} from './MtEngines/SmartMate'
import {Yandex} from './MtEngines/Yandex'
import {addMTEngine} from '../../../../api/addMTEngine'
import {SettingsPanelTable} from '../../SettingsPanelTable'
import {MTRow} from './MTRow'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {deleteMTEngine} from '../../../../api/deleteMTEngine'
import {MessageNotification} from '../MessageNotification'
import {DEFAULT_ENGINE_MEMORY} from '../../SettingsPanel'
import {MTGlossary} from './MTGlossary'

import Close from '../../../../../../../img/icons/Close'
import AddWide from '../../../../../../../img/icons/AddWide'

export const MachineTranslationTabContext = createContext({})

export const MachineTranslationTab = () => {
  const {
    mtEngines,
    setMtEngines,
    activeMTEngine,
    openLoginModal,
    setActiveMTEngine,
  } = useContext(SettingsPanelContext)

  const [addMTVisible, setAddMTVisible] = useState(false)
  const [activeAddEngine, setActiveAddEngine] = useState()
  const [isAddMTEngineRequestInProgress, setIsAddMTEngineRequestInProgress] =
    useState(false)
  const [error, setError] = useState()
  const [MTRows, setMTRows] = useState([])
  const [deleteMTRequest, setDeleteMTRequest] = useState()
  const [errorDeletingMT, setErrorDeletingMT] = useState(false)
  const [notification, setNotification] = useState({})

  const enginesList = [
    {name: 'ModernMT', id: 'mmt', component: ModernMt},
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

  const COLUMNS_TABLE = config.is_cattool
    ? [{name: 'Engine Name'}, {name: 'Description'}]
    : [
        {name: 'Engine Name'},
        {name: 'Description'},
        {name: 'Use in this project'},
        {name: 'Action'},
      ]

  const addMTEngineRequest = (data) => {
    setIsAddMTEngineRequestInProgress(true)
    addMTEngine({
      name: data.name,
      provider: activeAddEngine.id,
      dataMt: data,
    })
      .then((response) => {
        const {data} = response
        setAddMTVisible(false)
        setError()

        if (data.id) {
          const newMT = {
            name: data.name,
            id: parseInt(data.id),
            type: data.type,
            description: data.description
              ? data.description
              : activeAddEngine.name,
          }
          setMtEngines((prevStateMT) => {
            return [newMT, ...prevStateMT]
          })
        }
      })
      .catch((error) => {
        if (error && error.length) setError(error[0])
      })
      .finally(() => setIsAddMTEngineRequestInProgress(false))
  }

  const deleteMTConfirm = (mt) => {
    setErrorDeletingMT(false)
    setDeleteMTRequest(mt)
  }
  const deleteMT = () => {
    deleteMTEngine({id: deleteMTRequest.id})
      .then(() => {
        setMtEngines((prevStateMT) => {
          return prevStateMT.filter((MT) => MT.id !== deleteMTRequest.id)
        })
        setDeleteMTRequest()
        if (activeMTEngine.id === deleteMTRequest.id) {
          setActiveMTEngine(DEFAULT_ENGINE_MEMORY)
        }
      })
      .catch(() => {
        setErrorDeletingMT(true)
      })
  }

  const activateMT = (row) => {
    setActiveMTEngine(row)
  }

  const disableMT = () => {
    setActiveMTEngine()
  }

  useEffect(() => {
    setMTRows(
      mtEngines
        .filter((row) => !activeMTEngine?.id || row.id !== activeMTEngine?.id)
        .map((row, index) => {
          return {
            node: (
              <MTRow
                key={index}
                {...{row}}
                deleteMT={() => deleteMTConfirm(row)}
                onCheckboxClick={activateMT}
              />
            ),
            isDraggable: false,
          }
        }),
    )
    setDeleteMTRequest()
  }, [activeMTEngine?.id, mtEngines])

  const resetNotification = () => setNotification({})

  const notificationsNode = (
    <>
      {deleteMTRequest && (
        <MessageNotification
          type={'warning'}
          message={`Do you really want to delete the MT: <b>${deleteMTRequest.name}</b>?`}
          confirmCallback={deleteMT}
          closeCallback={() => setDeleteMTRequest()}
        />
      )}
      {errorDeletingMT && (
        <MessageNotification
          type={'error'}
          message={'There was an error saving your data. Please retry!'}
          closeCallback={() => setErrorDeletingMT(false)}
        />
      )}
      {notification?.message && (
        <MessageNotification
          {...{
            type: notification.type,
            message: notification.message,
            closeCallback: resetNotification,
          }}
        />
      )}
    </>
  )

  return (
    <MachineTranslationTabContext.Provider value={{setNotification}}>
      <div className="machine-translation-tab">
        {notificationsNode}

        {!config.is_cattool && config.isLoggedIn ? (
          !addMTVisible ? (
            <div className="add-mt-button">
              <button
                className="ui primary button settings-panel-button-icon"
                onClick={() => setAddMTVisible(true)}
              >
                <AddWide size={18} /> Add MT engine
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
                  <Close />
                </button>
              </div>
              {activeAddEngine ? (
                <activeAddEngine.component
                  addMTEngine={addMTEngineRequest}
                  error={error}
                  isRequestInProgress={isAddMTEngineRequestInProgress}
                />
              ) : null}
            </div>
          )
        ) : null}
        <div>
          <h2>Active MT</h2>
          <SettingsPanelTable
            columns={COLUMNS_TABLE}
            rows={
              activeMTEngine
                ? [
                    {
                      node: (
                        <MTRow
                          key={'active'}
                          row={{...activeMTEngine}}
                          deleteMT={() => deleteMTConfirm(activeMTEngine)}
                          onCheckboxClick={disableMT}
                        />
                      ),
                      isDraggable: false,
                      isActive: true,
                      ...(activeMTEngine.name === 'ModernMT' && {
                        isExpanded: true,
                        extraNode: (
                          <MTGlossary
                            {...{
                              ...activeMTEngine,
                              isCattoolPage: config.is_cattool,
                            }}
                          />
                        ),
                      }),
                    },
                  ]
                : []
            }
          />
        </div>
        {config.isLoggedIn ? (
          <div className="inactive-mt">
            <h2>Inactive MT</h2>
            <SettingsPanelTable columns={COLUMNS_TABLE} rows={MTRows} />
          </div>
        ) : (
          <div className="not-logged-user">
            <button className="ui primary button" onClick={openLoginModal}>
              Login to see your custom MT engines
            </button>
          </div>
        )}
      </div>
    </MachineTranslationTabContext.Provider>
  )
}
MachineTranslationTab.propTypes = {}
