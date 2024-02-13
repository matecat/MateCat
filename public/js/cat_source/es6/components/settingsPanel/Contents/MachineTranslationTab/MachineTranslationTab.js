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
import {DeepL} from './MtEngines/DeepL'
import {DeepLGlossary} from './DeepLGlossary'
import {MTDeepLRow} from './MTDeepLRow'
import CreateProjectActions from '../../../../actions/CreateProjectActions'
import CatToolActions from '../../../../actions/CatToolActions'
import {DeleteResource} from './DeleteResource'

export const MachineTranslationTabContext = createContext({})

export const MachineTranslationTab = () => {
  const {
    mtEngines,
    setMtEngines,
    openLoginModal,
    modifyingCurrentTemplate,
    currentProjectTemplate,
    projectTemplates,
  } = useContext(SettingsPanelContext)

  const activeMTEngine = currentProjectTemplate.mt?.id
  const setActiveMTEngine = ({id} = {}) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      mt:
        typeof id === 'number'
          ? {
              id,
            }
          : {},
    }))

  const [addMTVisible, setAddMTVisible] = useState(false)
  const [activeAddEngine, setActiveAddEngine] = useState()
  const [isAddMTEngineRequestInProgress, setIsAddMTEngineRequestInProgress] =
    useState(false)
  const [error, setError] = useState()
  const [MTRows, setMTRows] = useState([])
  const [deleteMTRequest, setDeleteMTRequest] = useState()
  const [notification, setNotification] = useState({})

  const enginesList = [
    {name: 'ModernMT', id: 'mmt', component: ModernMt},
    {name: 'AltLang', id: 'altlang', component: AltLang},
    {name: 'Apertium', id: 'apertium', component: Apertium},
    {name: 'DeepL', id: 'deepl', component: DeepL},
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

  const CUSTOM_ACTIVE_COLUMNS_TABLE_BY_ENGINE = {
    [enginesList.find(({name}) => name === 'DeepL').name]: config.is_cattool
      ? [{name: 'Engine Name'}, {name: 'Description'}, {name: 'Formality'}]
      : [
          {name: 'Engine Name'},
          {name: 'Description'},
          {name: 'Formality'},
          {name: 'Use in this project'},
          {name: 'Action'},
        ],
  }

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
    setDeleteMTRequest(mt)
  }
  const deleteMT = () => {
    deleteMTEngine({id: deleteMTRequest})
      .then(() => {
        const mtToDelete = mtEngines.find((mt) => mt.id === deleteMTRequest)
        setMtEngines((prevStateMT) => {
          return prevStateMT.filter((MT) => MT.id !== deleteMTRequest)
        })
        setDeleteMTRequest()
        if (activeMTEngine === deleteMTRequest) {
          setActiveMTEngine(DEFAULT_ENGINE_MEMORY)
        }

        const templatesInvolved = projectTemplates
          .filter((template) => template.mt.id === deleteMTRequest)
          .map((template) => ({
            ...template,
            mt: {},
          }))

        CreateProjectActions.updateProjectTemplates({
          templates: templatesInvolved,
          modifiedPropsCurrentProjectTemplate: {
            mt: templatesInvolved.find(({isTemporary}) => isTemporary)?.mt,
          },
        })
        CatToolActions.addNotification({
          title: 'MT deleted',
          type: 'success',
          text: `The MT (<b>${mtToDelete.name}</b>) has been successfully deleted`,
          position: 'br',
          allowHtml: true,
          timer: 5000,
        })
      })
      .catch(() => {
        CatToolActions.addNotification({
          title: 'Error deleting MT',
          type: 'error',
          text: 'There was an error saving your data. Please retry!',
          position: 'br',
          timer: 5000,
        })
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
        .filter((row) => !activeMTEngine || row.id !== activeMTEngine)
        .map((row, index) => {
          return {
            node: (
              <MTRow
                key={index}
                {...{row}}
                deleteMT={() => deleteMTConfirm(row.id)}
                onCheckboxClick={activateMT}
              />
            ),
            isDraggable: false,
            ...(deleteMTRequest &&
              row.id === deleteMTRequest && {
                isExpanded: true,
                extraNode: (
                  <DeleteResource
                    row={row}
                    onClose={() => setDeleteMTRequest()}
                    onConfirm={deleteMT}
                  />
                ),
              }),
          }
        }),
    )
  }, [activeMTEngine, mtEngines, deleteMTRequest])

  const resetNotification = () => setNotification({})

  const activeMTEngineData = mtEngines.find(({id}) => id === activeMTEngine)

  const notificationsNode = (
    <>
      {/* {typeof deleteMTRequest === 'number' && (
        <MessageNotification
          type={'warning'}
          message={`Do you really want to delete the MT: <b>${activeMTEngineData.name}</b>?`}
          confirmCallback={deleteMT}
          closeCallback={() => setDeleteMTRequest()}
        />
      )}*/}
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

  const activeColumns = CUSTOM_ACTIVE_COLUMNS_TABLE_BY_ENGINE[
    activeMTEngineData?.name
  ]
    ? CUSTOM_ACTIVE_COLUMNS_TABLE_BY_ENGINE[activeMTEngineData.name]
    : COLUMNS_TABLE

  const ActiveMTRow = activeMTEngineData?.name === 'DeepL' ? MTDeepLRow : MTRow

  return (
    <MachineTranslationTabContext.Provider value={{setNotification}}>
      <div className="machine-translation-tab settings-panel-contentwrapper-tab-background">
        {notificationsNode}

        {!config.is_cattool && config.isLoggedIn && addMTVisible && (
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
        )}
        <div>
          <div className="machine-translation-tab-table-title">
            <h2>Active MT</h2>
            {!config.is_cattool && config.isLoggedIn && !addMTVisible && (
              <button
                className="ui primary button settings-panel-button-icon"
                onClick={() => setAddMTVisible(true)}
              >
                <AddWide size={18} /> Add MT engine
              </button>
            )}
          </div>

          <SettingsPanelTable
            columns={activeColumns}
            className={`active-table-${activeMTEngineData?.name}`}
            rows={
              typeof activeMTEngine === 'number'
                ? [
                    {
                      node: (
                        <ActiveMTRow
                          key={'active'}
                          row={activeMTEngineData}
                          deleteMT={() => deleteMTConfirm(activeMTEngine)}
                          onCheckboxClick={disableMT}
                        />
                      ),
                      ...(deleteMTRequest &&
                        activeMTEngineData.id === deleteMTRequest && {
                          isExpanded: true,
                          extraNode: (
                            <DeleteResource
                              row={activeMTEngineData}
                              onClose={() => setDeleteMTRequest()}
                              onConfirm={deleteMT}
                            />
                          ),
                        }),
                      isDraggable: false,
                      isActive: true,
                      ...(activeMTEngineData.name === 'ModernMT' && {
                        isExpanded: true,
                        extraNode: (
                          <MTGlossary
                            {...{
                              ...activeMTEngineData,
                              isCattoolPage: config.is_cattool,
                            }}
                          />
                        ),
                      }),
                      ...(activeMTEngineData.name === 'DeepL' && {
                        isExpanded: true,
                        extraNode: (
                          <DeepLGlossary
                            {...{
                              ...activeMTEngineData,
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
