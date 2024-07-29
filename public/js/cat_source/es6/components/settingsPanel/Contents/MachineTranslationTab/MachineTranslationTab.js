import React, {useContext, useEffect, useRef, useState} from 'react'
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
import {DEFAULT_ENGINE_MEMORY} from '../../SettingsPanel'
import {MTGlossary} from './MTGlossary'

import AddWide from '../../../../../../../img/icons/AddWide'
import {DeepL} from './MtEngines/DeepL'
import {DeepLGlossary} from './DeepLGlossary'
import {MTDeepLRow} from './MTDeepLRow'
import CreateProjectActions from '../../../../actions/CreateProjectActions'
import CatToolActions from '../../../../actions/CatToolActions'
import {DeleteResource} from './DeleteResource'
import ModalsActions from '../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../modals/ConfirmDeleteResourceProjectTemplates'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import IconClose from '../../../icons/IconClose'
import {BUTTON_TYPE, Button} from '../../../common/Button/Button'

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

  const enginesList = [
    {
      name: 'ModernMT',
      id: 'mmt',
      component: ModernMt,
    },
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
            engine_type: data.engine_type,
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

  const deleteMT = useRef()
  deleteMT.current = (id) => {
    const deleteId = typeof id === 'number' ? id : deleteMTRequest

    deleteMTEngine({id: deleteId})
      .then(() => {
        const mtToDelete = mtEngines.find((mt) => mt.id === deleteId)
        setMtEngines((prevStateMT) => {
          return prevStateMT.filter((MT) => MT.id !== deleteId)
        })
        setDeleteMTRequest()
        if (activeMTEngine === deleteId) {
          setActiveMTEngine(DEFAULT_ENGINE_MEMORY)
        }

        const templatesInvolved = projectTemplates
          .filter((template) => template.mt.id === deleteId)
          .map((template) => ({
            ...template,
            [SCHEMA_KEYS.mt]: {},
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

  const showConfirmDelete = useRef()
  showConfirmDelete.current = (id) => {
    const templatesInvolved = projectTemplates
      .filter(({isTemporary}) => !isTemporary)
      .filter((template) => template.mt.id === id)

    if (templatesInvolved.length) {
      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: () => deleteMT.current(id),
          content: `The MT engine you are about to delete is used in the following project creation template(s):`,
        },
        'Confirm deletion',
      )
    } else {
      setDeleteMTRequest(id)
    }
  }

  useEffect(() => {
    if (!config.is_cattool) {
      setMTRows(
        mtEngines
          .filter((row) => !activeMTEngine || row.id !== activeMTEngine)
          .map((row, index) => {
            return {
              node: (
                <MTRow
                  key={index}
                  {...{row}}
                  deleteMT={() => showConfirmDelete.current(row.id)}
                  onCheckboxClick={(row) => setActiveMTEngine(row)}
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
                      onConfirm={deleteMT.current}
                    />
                  ),
                }),
            }
          }),
      )
    }
  }, [activeMTEngine, mtEngines, deleteMTRequest])

  const disableMT = () => setActiveMTEngine()

  const activeMTEngineData =
    !config.is_cattool || (config.is_cattool && config.ownerIsMe)
      ? mtEngines.find(({id}) => id === activeMTEngine)
      : config.active_engine

  const activeColumns = CUSTOM_ACTIVE_COLUMNS_TABLE_BY_ENGINE[
    activeMTEngineData?.name
  ]
    ? CUSTOM_ACTIVE_COLUMNS_TABLE_BY_ENGINE[activeMTEngineData.name]
    : COLUMNS_TABLE

  const ActiveMTRow =
    activeMTEngineData?.engine_type === 'DeepL' ? MTDeepLRow : MTRow

  const getExtraNodeActiveRow = () => {
    const shouldShowDeleteConfirmation =
      deleteMTRequest && activeMTEngineData.id === deleteMTRequest
    const GlossaryComponent =
      activeMTEngineData.engine_type === 'MMT'
        ? MTGlossary
        : activeMTEngineData.engine_type === 'DeepL'
          ? DeepLGlossary
          : undefined

    return {
      ...((shouldShowDeleteConfirmation || GlossaryComponent) && {
        isExpanded: true,
        extraNode: (
          <>
            {deleteMTRequest && activeMTEngineData.id === deleteMTRequest && (
              <DeleteResource
                row={activeMTEngineData}
                onClose={() => setDeleteMTRequest()}
                onConfirm={deleteMT.current}
              />
            )}
            {GlossaryComponent && (
              <GlossaryComponent
                {...{
                  ...activeMTEngineData,
                  isCattoolPage: config.is_cattool,
                }}
              />
            )}
          </>
        ),
      }),
    }
  }

  return (
    <div className="machine-translation-tab settings-panel-contentwrapper-tab-background">
      {!config.is_cattool && config.isLoggedIn && addMTVisible && (
        <div className="add-mt-container">
          <h2>Add MT Engine</h2>
          <div className="add-mt-provider" data-testid="add-mt-provider">
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
            <Button
              type={BUTTON_TYPE.WARNING}
              onClick={() => setAddMTVisible(false)}
            >
              <IconClose size={11} />
            </Button>
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
      <div data-testid="active-mt">
        <div className="machine-translation-tab-table-title">
          <h2>Active MT</h2>
          {!config.is_cattool && config.isLoggedIn && !addMTVisible && (
            <button
              className="ui primary button settings-panel-button-icon"
              onClick={() => setAddMTVisible(true)}
              title="Add MT engine"
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
                        deleteMT={() =>
                          showConfirmDelete.current(activeMTEngine)
                        }
                        onCheckboxClick={disableMT}
                      />
                    ),
                    isDraggable: false,
                    isActive: true,
                    ...getExtraNodeActiveRow(),
                  },
                ]
              : []
          }
        />
      </div>
      {config.isLoggedIn ? (
        !config.is_cattool && (
          <div className="inactive-mt" data-testid="inactive-mt">
            <h2>Inactive MT</h2>
            <SettingsPanelTable columns={COLUMNS_TABLE} rows={MTRows} />
          </div>
        )
      ) : (
        <div className="not-logged-user">
          <button
            className="ui primary button"
            onClick={openLoginModal}
            title="Login to see your custom MT engines"
            data-testid="login-button"
          >
            Login to see your custom MT engines
          </button>
        </div>
      )}
    </div>
  )
}
MachineTranslationTab.propTypes = {}
