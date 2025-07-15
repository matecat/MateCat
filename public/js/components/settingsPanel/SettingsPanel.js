import React, {useState, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from './SettingsPanelContext'
import {ContentWrapper} from './ContentWrapper'
import {MachineTranslationTab} from './Contents/MachineTranslationTab'
import {OtherTab} from './Contents/OtherTab'
import {TranslationMemoryGlossaryTab} from './Contents/TranslationMemoryGlossaryTab'
import {ProjectTemplate} from './ProjectTemplate/ProjectTemplate'
import {SCHEMA_KEYS, isStandardTemplate} from '../../hooks/useProjectTemplates'
import {AnalysisTab} from './Contents/AnalysisTab'
import {QualityFrameworkTab} from './Contents/QualityFrameworkTab'
import {updateProjectTemplate} from '../../api/updateProjectTemplate'
import {flushSync} from 'react-dom'
import CreateProjectStore from '../../stores/CreateProjectStore'
import NewProjectConstants from '../../constants/NewProjectConstants'
import {FileImportTab} from './Contents/FileImportTab/FileImportTab'
import {EditorSettingsTab} from './Contents/EditorSettingsTab'
import ModalsActions from '../../actions/ModalsActions'
import {getFiltersParamsTemplates} from '../../api/getFiltersParamsTemplates'
import defaultFiltersParams from './Contents/defaultTemplates/filterParams.json'
import {isEqual} from 'lodash'
import useSyncTemplateWithConvertFile from './useSyncTemplateWithConvertFile'
import {EditorOtherTab} from './Contents/EditorOtherTab'

let tabOpenFromQueryString = new URLSearchParams(window.location.search).get(
  'openTab',
)

export const SETTINGS_PANEL_TABS = {
  translationMemoryGlossary: 'tm',
  machineTranslation: 'mt',
  other: 'other',
  analysis: 'analysis',
  qualityFramework: 'qf',
  fileImport: 'fileImport',
  editorSettings: 'editorSettings',
  editorOther: 'editorOther',
}

export const TEMPLATE_PROPS_BY_TAB = {
  [SETTINGS_PANEL_TABS.translationMemoryGlossary]: [
    SCHEMA_KEYS.tm,
    SCHEMA_KEYS.getPublicMatches,
    SCHEMA_KEYS.pretranslate100,
    SCHEMA_KEYS.tmPrioritization,
  ],
  [SETTINGS_PANEL_TABS.machineTranslation]: [SCHEMA_KEYS.mt],
  [SETTINGS_PANEL_TABS.qualityFramework]: [SCHEMA_KEYS.qaModelTemplateId],
  [SETTINGS_PANEL_TABS.fileImport]: [
    SCHEMA_KEYS.segmentationRule,
    SCHEMA_KEYS.filtersTemplateId,
    SCHEMA_KEYS.XliffConfigTemplateId,
  ],
  [SETTINGS_PANEL_TABS.analysis]: [SCHEMA_KEYS.payableRateTemplateId],
  [SETTINGS_PANEL_TABS.other]: [
    SCHEMA_KEYS.speech2text,
    SCHEMA_KEYS.tagProjection,
    SCHEMA_KEYS.lexica,
    SCHEMA_KEYS.crossLanguageMatches,
    SCHEMA_KEYS.idTeam,
  ],
  [SETTINGS_PANEL_TABS.editorSettings]: [],
  [SETTINGS_PANEL_TABS.editorOther]: [],
}

const DEFAULT_CONTENTS = (isCattool = config.is_cattool) => {
  return [
    {
      id: SETTINGS_PANEL_TABS.translationMemoryGlossary,
      label: 'Translation Memory and Glossary',
      description:
        'Manage your language resources and select which should be used on your new project. <a href="https://guides.matecat.com/activ" target="_blank">More details</a>',
      component: <TranslationMemoryGlossaryTab />,
    },
    {
      id: SETTINGS_PANEL_TABS.machineTranslation,
      label: 'Machine Translation',
      description:
        'Manage your machine translation engines and select which should be used on your new project. <a href="https://guides.matecat.com/machine-translation-engines" target="_blank">More details</a>',
      component: <MachineTranslationTab />,
    },
    ...(!isCattool
      ? [
          {
            id: SETTINGS_PANEL_TABS.qualityFramework,
            label: 'Quality framework',
            description:
              'Manage your quality frameworks and select which should be used on your new project. <a href="https://guides.matecat.com/quality-framework" target="_blank">More details</a>',
            component: <QualityFrameworkTab />,
          },
          {
            id: SETTINGS_PANEL_TABS.fileImport,
            label: 'File import',
            description:
              'Set up your file import preferences for new projects.  <a href="https://guides.matecat.com/file-import" target="_blank">More details</a>',
            component: <FileImportTab />,
          },
          {
            id: SETTINGS_PANEL_TABS.analysis,
            label: 'Analysis',
            description:
              'Manage your billing models and select which should be used on your new project. <a href="https://guides.matecat.com/billing-model" target="_blank">More details</a>',
            component: <AnalysisTab />,
          },
          {
            id: SETTINGS_PANEL_TABS.other,
            label: 'Other',
            description: 'Adjust other project creation settings.',
            component: <OtherTab />,
          },
        ]
      : []),
    ...(isCattool
      ? [
          {
            id: SETTINGS_PANEL_TABS.editorSettings,
            label: 'Editor settings',
            description:
              'Customize the settings for Matecat\'s editor page to better suit your personal workflow and preferences. <a href="https://guides.matecat.com/editor-settings" target="_blank">Learn more</a>',
            component: <EditorSettingsTab />,
          },
          ...(config.ownerIsMe === 1
            ? [
                {
                  id: SETTINGS_PANEL_TABS.editorOther,
                  label: 'Other',
                  description: 'Adjust other project creation settings.',
                  component: <EditorOtherTab />,
                },
              ]
            : []),
        ]
      : []),
  ]
}

export const DEFAULT_ENGINE_MEMORY = {
  id: 1,
  name: 'ModernMT Lite',
  description: (
    <div
      dangerouslySetInnerHTML={{
        __html:
          'Smart machine translation that learns from your corrections for enhanced quality and productivity thanks to ModernMT’s basic features. To unlock all features, <a target="_blank" href="https://www.modernmt.com/pricing#translators">click here</a>.',
      }}
    />
  ),
  default: true,
  engine_type: 'MMTLite',
}

export const SettingsPanel = ({
  onClose,
  isOpened,
  tabOpen = SETTINGS_PANEL_TABS.translationMemoryGlossary,
  user,
  tmKeys,
  setTmKeys,
  mtEngines,
  setMtEngines,
  sourceLang,
  targetLangs,
  projectTemplates,
  currentProjectTemplate,
  setProjectTemplates,
  modifyingCurrentTemplate,
  checkSpecificTemplatePropsAreModified,
  qualityFrameworkTemplates = {},
  analysisTemplates = {},
  fileImportFiltersParamsTemplates = {},
  fileImportXliffSettingsTemplates = {},
}) => {
  const [isVisible, setIsVisible] = useState(false)
  const [tabs, setTabs] = useState(() => {
    const initialState = DEFAULT_CONTENTS().map((tab) => ({
      ...tab,
      isOpened: Object.values(SETTINGS_PANEL_TABS).some(
        (value) => value === tabOpenFromQueryString,
      )
        ? tabOpenFromQueryString === tab.id
        : tabOpen === tab.id,
    }))

    tabOpenFromQueryString = false
    return initialState
  })

  // Sync filters template with conversion file
  useSyncTemplateWithConvertFile({
    ...fileImportFiltersParamsTemplates,
    defaultTemplate: defaultFiltersParams,
    idTemplate: currentProjectTemplate?.filtersTemplateId,
    getTemplates: getFiltersParamsTemplates,
    checkIfUpdate: (filtersTemplate) => {
      if (!isEqual(filtersTemplate, CreateProjectStore.getFiltersTemplate())) {
        CreateProjectStore.updateProject({
          filtersTemplate,
        })
      }
    },
  })

  const wrapperRef = useRef()

  useEffect(() => {
    setIsVisible(typeof isOpened !== 'undefined' ? isOpened : true)

    const keyboardHandler = (e) => e.key === 'Escape' && setIsVisible(false)
    document.addEventListener('keydown', keyboardHandler)

    return () => document.removeEventListener('keydown', keyboardHandler)
  }, [isOpened])

  useEffect(() => {
    const onTransitionEnd = () => !isVisible && onClose()

    const {current} = wrapperRef
    current.addEventListener('transitionend', onTransitionEnd)

    return () => current.removeEventListener('transitionend', onTransitionEnd)
  }, [isVisible, onClose])

  // Notify to server when user deleted a tmKey, MT or MT glossary from templates and sync project templates state
  useEffect(() => {
    const updateProjectTemplatesAction = ({
      templates,
      modifiedPropsCurrentProjectTemplate,
    }) => {
      const promiseTemplates = templates
        .filter(({isTemporary, id}) => !isTemporary && !isStandardTemplate(id))
        .map(
          (template) =>
            new Promise((resolve, reject) => {
              /* eslint-disable no-unused-vars */
              const {
                created_at,
                id,
                uid,
                modified_at,
                isTemporary,
                isSelected,
                ...modifiedTemplate
              } = template
              /* eslint-enable no-unused-vars */

              updateProjectTemplate({
                id: template.id,
                template: modifiedTemplate,
              })
                .then((template) => resolve(template))
                .catch(() => reject())
            }),
        )

      Promise.all(promiseTemplates).then((values) => {
        flushSync(() =>
          setProjectTemplates((prevState) =>
            prevState.map((template) => {
              const update = values.find(
                ({id} = {}) => id === template.id && !template.isTemporary,
              )
              return {...template, ...(update && {...update})}
            }),
          ),
        )

        const currentOriginalTemplate = values.find(
          ({id, isTemporary}) =>
            id === currentProjectTemplate.id && !isTemporary,
        )

        modifyingCurrentTemplate((prevTemplate) => ({
          ...prevTemplate,
          ...(templates.find(
            ({isTemporary, id}) => isTemporary && !isStandardTemplate(id),
          )
            ? {
                ...modifiedPropsCurrentProjectTemplate,
                ...(currentOriginalTemplate && {
                  modifiedAt: currentOriginalTemplate.modified_at,
                }),
              }
            : currentOriginalTemplate),
        }))
      })
    }

    CreateProjectStore.addListener(
      NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
      updateProjectTemplatesAction,
    )

    return () =>
      CreateProjectStore.removeListener(
        NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
        updateProjectTemplatesAction,
      )
  }, [
    currentProjectTemplate?.id,
    setProjectTemplates,
    modifyingCurrentTemplate,
  ])

  const close = () => setIsVisible(false)

  const openLoginModal = () => {
    setIsVisible(false)
    ModalsActions.openLoginModal()
  }

  const isEnabledProjectTemplateComponent = !config.is_cattool

  return (
    <SettingsPanelContext.Provider
      value={{
        tabs,
        setTabs,
        user,
        tmKeys,
        setTmKeys,
        mtEngines,
        setMtEngines,
        openLoginModal,
        wrapperRef,
        sourceLang,
        targetLangs,
        projectTemplates,
        currentProjectTemplate,
        setProjectTemplates,
        modifyingCurrentTemplate,
        checkSpecificTemplatePropsAreModified,
        isEnabledProjectTemplateComponent,
        qualityFrameworkTemplates,
        analysisTemplates,
        fileImportFiltersParamsTemplates,
        fileImportXliffSettingsTemplates,
      }}
    >
      <div
        className={`settings-panel${
          isOpened || typeof isOpened === 'undefined' ? ' visible' : ''
        }`}
      >
        <div
          className={`settings-panel-overlay${
            isVisible
              ? ' settings-panel-overlay-visible'
              : ' settings-panel-overlay-hide'
          }`}
          onClick={close}
        ></div>
        <div
          ref={wrapperRef}
          className={`settings-panel-wrapper${
            isVisible
              ? ' settings-panel-wrapper-visible'
              : ' settings-panel-wrapper-hide'
          }`}
        >
          {isOpened && (
            <>
              <div className="settings-panel-header">
                <div className="settings-panel-header-logo" />
                <span>Settings</span>
                <div onClick={close} className="close-matecat-modal x-popup" />
              </div>
              {isEnabledProjectTemplateComponent && <ProjectTemplate />}
              {currentProjectTemplate && <ContentWrapper />}
            </>
          )}
        </div>
      </div>
    </SettingsPanelContext.Provider>
  )
}

SettingsPanel.propTypes = {
  onClose: PropTypes.func.isRequired,
  isOpened: PropTypes.bool,
  tabOpen: PropTypes.oneOf(Object.values(SETTINGS_PANEL_TABS)),
  user: PropTypes.object,
  tmKeys: PropTypes.array,
  setTmKeys: PropTypes.func,
  mtEngines: PropTypes.array,
  setMtEngines: PropTypes.func,
  sourceLang: PropTypes.object,
  targetLangs: PropTypes.array,
  projectTemplates: PropTypes.array,
  currentProjectTemplate: PropTypes.any,
  setProjectTemplates: PropTypes.func,
  modifyingCurrentTemplate: PropTypes.func,
  checkSpecificTemplatePropsAreModified: PropTypes.func,
  qualityFrameworkTemplates: PropTypes.object,
  analysisTemplates: PropTypes.object,
  fileImportFiltersParamsTemplates: PropTypes.object,
  fileImportXliffSettingsTemplates: PropTypes.object,
}
