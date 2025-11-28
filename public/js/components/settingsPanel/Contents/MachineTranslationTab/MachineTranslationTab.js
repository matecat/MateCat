import React, {useContext, useEffect, useRef, useState} from 'react'
import {Select} from '../../../common/Select'
import {ModernMt} from './MtEngines/ModernMt'
import {AltLang} from './MtEngines/AltLang'
import {Apertium} from './MtEngines/Apertium'
import {GoogleTranslate} from './MtEngines/GoogleTranslate'
import {Intento} from './MtEngines/Intento'
import {SmartMate} from './MtEngines/SmartMate'
import {Yandex} from './MtEngines/Yandex'
import {addMTEngine} from '../../../../api/addMTEngine'
import {SettingsPanelTable} from '../../SettingsPanelTable'
import {MTRow} from './MTRow'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {deleteMTEngine} from '../../../../api/deleteMTEngine'
import {DEFAULT_ENGINE_MEMORY} from '../../SettingsPanel'

import {DeepL} from './MtEngines/DeepL'
import CreateProjectActions from '../../../../actions/CreateProjectActions'
import CatToolActions from '../../../../actions/CatToolActions'
import {DeleteResource} from './DeleteResource'
import ModalsActions from '../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../modals/ConfirmDeleteResourceProjectTemplates'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import {Lara} from './MtEngines/Lara'
import {OptionsContainer} from './OptionsContainer'
import {ApplicationThreshold} from './ApplicationThreshold'
import defaultMTOptions from '../../Contents/defaultTemplates/mtOptions.json'
import {normalizeTemplatesWithNullProps} from '../../../../hooks/useTemplates'
import {Button, BUTTON_TYPE} from '../../../common/Button/Button'
import IconClose from '../../../icons/IconClose'
import IconAdd from '../../../icons/IconAdd'

let engineIdFromQueryString = new URLSearchParams(window.location.search).get(
  'engineId',
)

export const MachineTranslationTab = () => {
  const {
    mtEngines,
    setMtEngines,
    modifyingCurrentTemplate,
    currentProjectTemplate,
    projectTemplates,
  } = useContext(SettingsPanelContext)

  const enginesList = [
    {
      name: 'ModernMT',
      id: 'mmt',
      component: ModernMt,
    },
    {
      name: 'Lara',
      id: 'Lara',
      component: Lara,
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
    {name: 'SmartMATE', id: 'smartmate', component: SmartMate},
    {name: 'Yandex.Translate', id: 'yandextranslate', component: Yandex},
  ]

  const activeMTEngine = currentProjectTemplate.mt?.id
  const setActiveMTEngine = useRef()
  setActiveMTEngine.current = ({id, engine_type} = {}) => {
    const defaultExtra = defaultMTOptions[engine_type]

    const originalProjectTemplate = projectTemplates.find(
      ({isSelected, isTemporary}) => isSelected && !isTemporary,
    )

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      mt:
        typeof id === 'number'
          ? {
              id,
              ...(defaultExtra && {
                extra: {
                  ...normalizeTemplatesWithNullProps(
                    [
                      id === originalProjectTemplate.mt.id &&
                      typeof originalProjectTemplate.mt.extra !== 'undefined'
                        ? originalProjectTemplate.mt.extra
                        : {},
                    ],
                    defaultExtra,
                  )[0],
                },
              }),
            }
          : {},
    }))
  }

  const [addMTVisible, setAddMTVisible] = useState(
    typeof engineIdFromQueryString === 'string',
  )
  const [activeAddEngine, setActiveAddEngine] = useState(() => {
    const initialState =
      typeof engineIdFromQueryString === 'string' &&
      enginesList.find((mt) => mt.id === engineIdFromQueryString)

    engineIdFromQueryString = false
    return initialState
  })
  const [isAddMTEngineRequestInProgress, setIsAddMTEngineRequestInProgress] =
    useState(false)
  const [error, setError] = useState()
  const [MTRows, setMTRows] = useState([])
  const [deleteMTRequest, setDeleteMTRequest] = useState()

  const COLUMNS_TABLE = config.is_cattool
    ? [{name: 'Active'}, {name: 'Engine Name'}, {name: 'Description'}]
    : [
        {name: 'Active'},
        {name: 'Engine Name'},
        {name: 'Description'},
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
            engine_type: data.engine_type,
          }
          setMtEngines((prevStateMT) => {
            return [newMT, ...prevStateMT]
          })
        }
      })
      .catch(({errors}) => {
        if (errors && errors.message) setError(errors)
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
          setActiveMTEngine.current(DEFAULT_ENGINE_MEMORY)
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
                  onCheckboxClick={(row) => setActiveMTEngine.current(row)}
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

  const disableMT = () => setActiveMTEngine.current()

  const activeMTEngineData =
    !config.is_cattool || (config.is_cattool && config.ownerIsMe)
      ? mtEngines.find(({id}) => id === activeMTEngine)
      : config.active_engine

  const ActiveMTRow = MTRow

  const getExtraNodeActiveRow = () => {
    const shouldShowDeleteConfirmation =
      deleteMTRequest && activeMTEngineData.id === deleteMTRequest

    const shouldShowOptions = true

    return {
      ...((shouldShowDeleteConfirmation || shouldShowOptions) && {
        isExpanded: activeMTEngineData.engine_type !== 'MMTLite',
        extraNode: (
          <>
            {deleteMTRequest && activeMTEngineData.id === deleteMTRequest && (
              <DeleteResource
                row={activeMTEngineData}
                onClose={() => setDeleteMTRequest()}
                onConfirm={deleteMT.current}
              />
            )}
            <OptionsContainer
              {...{
                activeMTEngineData,
                isCattoolPage: config.is_cattool,
              }}
            />
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
              setAddMTVisible={setAddMTVisible}
              error={error}
              isRequestInProgress={isAddMTEngineRequestInProgress}
            />
          ) : null}
        </div>
      )}
      <div data-testid="active-mt">
        <div className="machine-translation-tab-table-title">
          <div className="machine-translation-tab-title-container">
            <h2>Active MT</h2>
          </div>
          {!config.is_cattool && !addMTVisible && (
            <button
              className="ui primary button settings-panel-button-icon"
              onClick={() => setAddMTVisible(true)}
              title="Add MT engine"
            >
              <IconAdd size={16} /> Add MT engine
            </button>
          )}
        </div>

        <ApplicationThreshold />

        <SettingsPanelTable
          columns={COLUMNS_TABLE}
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
      {!config.is_cattool && (
        <div className="inactive-mt" data-testid="inactive-mt">
          <h2>Inactive MT</h2>
          <SettingsPanelTable columns={COLUMNS_TABLE} rows={MTRows} />
        </div>
      )}
    </div>
  )
}
MachineTranslationTab.propTypes = {}
