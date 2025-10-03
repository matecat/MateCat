import React, {
  useEffect,
  useRef,
  useState,
  useCallback,
  useMemo,
  useContext,
} from 'react'
import usePortal from '../hooks/usePortal'
import Header from '../components/header/Header'
import {Select} from '../components/common/Select'
import ModalsActions from '../actions/ModalsActions'
import AlertModal from '../components/modals/AlertModal'
import {getTmKeysUser} from '../api/getTmKeysUser'
import More from '../../img/icons/More'
import SupportedFilesModal from '../components/modals/SupportedFilesModal'
import Footer from '../components/footer/Footer'
import {createProject as createProjectApi} from '../api/createProject'
import CreateProjectActions from '../actions/CreateProjectActions'
import LanguageSelector, {
  setRecentlyUsedLanguages,
} from '../components/languageSelector/LanguageSelector'
import CreateProjectStore from '../stores/CreateProjectStore'
import NewProjectConstants from '../constants/NewProjectConstants'
import {CreateProjectContext} from '../components/createProject/CreateProjectContext'
import {TargetLanguagesSelect} from '../components/createProject/TargetLanguagesSelect'
import {TmGlossarySelect} from '../components/createProject/TmGlossarySelect'
import {SourceLanguageSelect} from '../components/createProject/SourceLanguageSelect'
import CommonUtils from '../utils/commonUtils'
import {DEFAULT_ENGINE_MEMORY, SettingsPanel} from '../components/settingsPanel'
import {getMTEngines as getMtEnginesApi} from '../api/getMTEngines'
import {tmCreateRandUser} from '../api/tmCreateRandUser'
import {getSupportedFiles} from '../api/getSupportedFiles'
import {getSupportedLanguages} from '../api/getSupportedLanguages'
import ApplicationActions from '../actions/ApplicationActions'
import useDeviceCompatibility from '../hooks/useDeviceCompatibility'
import useProjectTemplates, {SCHEMA_KEYS} from '../hooks/useProjectTemplates'
import {TemplateSelect} from '../components/settingsPanel/ProjectTemplate/TemplateSelect'
import {getMMTKeys} from '../api/getMMTKeys/getMMTKeys'
import {AlertDeleteResourceProjectTemplates} from '../components/modals/AlertDeleteResourceProjectTemplates'
import {checkGDriveEvents, handleCreationStatus} from '../utils/newProjectUtils'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import {mountPage} from './mountPage'
import {HomePageSection} from '../components/createProject/HomePageSection'
import UserActions from '../actions/UserActions'
import {getDeepLGlosssaries} from '../api/getDeepLGlosssaries/getDeepLGlosssaries'
import SocketListener from '../sse/SocketListener'
import {
  Button,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../components/common/Button/Button'
import {
  ONBOARDING_PAGE,
  OnboardingTooltips,
} from '../components/header/OnboardingTooltips'
import {UploadFile} from '../components/createProject/UploadFile'
import {flushSync} from 'react-dom'
import DriveIcon from '../../img/icons/DriveIcon'
import useTemplates from '../hooks/useTemplates'
import {QF_SCHEMA_KEYS} from '../components/settingsPanel/Contents/QualityFrameworkTab'
import {ANALYSIS_SCHEMA_KEYS} from '../components/settingsPanel/Contents/AnalysisTab'
import {FILTERS_PARAMS_SCHEMA_KEYS} from '../components/settingsPanel/Contents/FileImportTab/FiltersParams/FiltersParams'
import {XLIFF_SETTINGS_SCHEMA_KEYS} from '../components/settingsPanel/Contents/FileImportTab/XliffSettings/XliffSettings'

const SELECT_HEIGHT = 324

const urlParams = new URLSearchParams(window.location.search)
const initialStateIsOpenSettings = Boolean(urlParams.get('openTab'))
const tmKeyFromQueryString = urlParams.get('private_tm_key')
const idTeamFromQueryString = urlParams.get('idTeam')

let isTmKeyFromQueryStringAddedToTemplate = false

const subjectsArray = config.subject_array.map((item) => {
  return {...item, id: item.key, name: item.display}
})
const conversionEnabled = Boolean(config.conversionEnabled)
const formatsNumber = config.formats_number

const headerMountPoint = document.querySelector('header.upload-page-header')

const NewProject = () => {
  const [tmKeys, setTmKeys] = useState()
  const [mtEngines, setMtEngines] = useState([DEFAULT_ENGINE_MEMORY])
  const [projectSent, setProjectSent] = useState(false)
  const [errors, setErrors] = useState()
  const [warnings, setWarnings] = useState()
  const [isOpenMultiselectLanguages, setIsOpenMultiselectLanguages] =
    useState(false)
  const [openSettings, setOpenSettings] = useState({
    isOpen: initialStateIsOpenSettings,
  })
  const [isImportTMXInProgress, setIsImportTMXInProgress] = useState(false)
  const [isFormReadyToSubmit, setIsFormReadyToSubmit] = useState(false)
  const [supportedFiles, setSupportedFiles] = useState()
  const [supportedLanguages, setSupportedLanguages] = useState()
  const [openGDrive, setOpenGDrive] = useState(false)
  const [isGDriveEnabled, setIsGDriveEnabled] = useState(
    config.googleDriveEnabled,
  )
  const [uploadedFilesNames, setUploadedFilesNames] = useState([])

  const {
    projectTemplates,
    currentProjectTemplate,
    setProjectTemplates,
    modifyingCurrentTemplate,
    checkSpecificTemplatePropsAreModified,
  } = useProjectTemplates(tmKeys)

  // templates quality framework
  const qualityFrameworkTemplates = useTemplates(QF_SCHEMA_KEYS)
  const analysisTemplates = useTemplates(ANALYSIS_SCHEMA_KEYS)
  const fileImportFiltersParamsTemplates = useTemplates(
    FILTERS_PARAMS_SCHEMA_KEYS,
  )
  const fileImportXliffSettingsTemplates = useTemplates(
    XLIFF_SETTINGS_SCHEMA_KEYS,
  )

  const isDeviceCompatible = useDeviceCompatibility()

  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const subject = useMemo(
    () =>
      currentProjectTemplate &&
      subjectsArray.find(
        ({id}) =>
          id === (currentProjectTemplate.subject ?? subjectsArray[0].id),
      ),
    [currentProjectTemplate],
  )
  const setSubject = useCallback(
    ({id}) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        subject: id,
      })),
    [modifyingCurrentTemplate],
  )

  const sourceLang = useMemo(
    () =>
      supportedLanguages?.length && currentProjectTemplate
        ? supportedLanguages.find(
            ({id}) => id === (currentProjectTemplate.sourceLanguage ?? 'en-US'),
          )
        : {},
    [currentProjectTemplate, supportedLanguages],
  )
  const setSourceLang = useCallback(
    ({id}) => {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        sourceLanguage: id,
      }))
    },
    [modifyingCurrentTemplate],
  )

  const targetLangs = useMemo(() => {
    if (supportedLanguages?.length && currentProjectTemplate) {
      const targetLanguage = currentProjectTemplate.targetLanguage.length
        ? currentProjectTemplate.targetLanguage
        : ['fr-FR']
      return supportedLanguages.filter(({id}) =>
        targetLanguage.some((value) => value === id),
      )
    } else {
      return []
    }
  }, [currentProjectTemplate, supportedLanguages])
  const setTargetLangs = useCallback(
    (value) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        targetLanguage: value.map(({id}) => id),
      })),
    [modifyingCurrentTemplate],
  )

  const projectNameRef = useRef()
  const prevSourceLang = useRef(sourceLang)
  const createProject = useRef()
  const idTeamPreset = useRef(parseInt(idTeamFromQueryString))

  const checkMMTGlossariesWasCancelledIntoTemplates = useRef(
    (() => {
      let wasChecked = false

      return ({engineId, projectTemplates}) => {
        if (
          !wasChecked &&
          typeof engineId === 'number' &&
          projectTemplates.length &&
          projectTemplates.find(({isSelected}) => isSelected)?.mt.id ===
            engineId
        ) {
          getMMTKeys({engineId}).then((data) => {
            const projectTemplatesInvolved = projectTemplates.filter(
              ({mt}) =>
                mt.id === engineId &&
                Array.isArray(mt.extra?.glossaries) &&
                mt.extra.glossaries.some(
                  (glossaryId) => !data.find(({id}) => glossaryId === id),
                ),
            )

            if (projectTemplatesInvolved.length) {
              const projectTemplatesUpdated = projectTemplatesInvolved.map(
                (template) => {
                  const filteredGlossaries =
                    template.mt.extra.glossaries.filter((glossaryId) =>
                      data.find(({id}) => id === glossaryId),
                    )
                  const {glossaries, ...prevExtra} = template.mt.extra // eslint-disable-line

                  return {
                    ...template,
                    [SCHEMA_KEYS.mt]: {
                      ...template.mt,
                      extra: {
                        ...prevExtra,
                        ...(filteredGlossaries.length && {
                          glossaries: filteredGlossaries,
                        }),
                      },
                    },
                  }
                },
              )

              // Notify template to server without glossaries delete
              CreateProjectActions.updateProjectTemplates({
                templates: projectTemplatesUpdated,
                modifiedPropsCurrentProjectTemplate: {
                  tm: projectTemplatesUpdated.find(
                    ({isTemporary}) => isTemporary,
                  )?.tm,
                },
              })

              ModalsActions.showModalComponent(
                AlertDeleteResourceProjectTemplates,
                {
                  projectTemplatesInvolved,
                  content:
                    'A different user has deleted one or more of the MT glossaries used in the following project creation template(s):',
                },
                'MT glossary deletion',
              )
            }
          })

          wasChecked = true
        }
      }
    })(),
  )

  const checkDeepLGlossaryWasCancelledIntoTemplates = useRef(
    (() => {
      let wasChecked = false

      return ({engineId, projectTemplates}) => {
        if (
          !wasChecked &&
          typeof engineId === 'number' &&
          projectTemplates.length &&
          projectTemplates.find(({isSelected}) => isSelected)?.mt.id ===
            engineId
        ) {
          getDeepLGlosssaries({engineId}).then(({glossaries}) => {
            const projectTemplatesInvolved = projectTemplates.filter(
              ({mt}) =>
                mt.id === engineId &&
                typeof mt.extra?.deepl_id_glossary !== 'undefined' &&
                !glossaries.some(
                  ({glossary_id}) =>
                    glossary_id === mt.extra?.deepl_id_glossary,
                ),
            )

            if (projectTemplatesInvolved.length) {
              const projectTemplatesUpdated = projectTemplatesInvolved.map(
                (template) => {
                  const {deepl_id_glossary, ...prevExtra} =
                    template.mt.extra ?? {}

                  return {
                    ...template,
                    [SCHEMA_KEYS.mt]: {
                      ...template.mt,
                      extra: {
                        ...prevExtra,
                        ...(glossaries.some(
                          ({glossary_id}) => glossary_id === deepl_id_glossary,
                        ) && {
                          deepl_id_glossary,
                        }),
                      },
                    },
                  }
                },
              )

              // Notify template to server without glossaries delete
              CreateProjectActions.updateProjectTemplates({
                templates: projectTemplatesUpdated,
                modifiedPropsCurrentProjectTemplate: {
                  tm: projectTemplatesUpdated.find(
                    ({isTemporary}) => isTemporary,
                  )?.tm,
                },
              })

              ModalsActions.showModalComponent(
                AlertDeleteResourceProjectTemplates,
                {
                  projectTemplatesInvolved,
                  content:
                    'A different user has deleted one or more of the DeepL glossaries used in the following project creation template(s):',
                },
                'MT glossary deletion',
              )
            }
          })

          wasChecked = true
        }
      }
    })(),
  )

  const closeSettings = useCallback(() => setOpenSettings({isOpen: false}), [])

  const selectedTeam = useMemo(() => {
    const team = userInfo?.teams?.find(
      ({id}) => id === currentProjectTemplate?.idTeam,
    )

    return team && {...team, id: team.id?.toString()}
  }, [userInfo?.teams, currentProjectTemplate?.idTeam])
  const setSelectedTeam = useCallback(
    ({id}) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        idTeam: parseInt(id),
      })),
    [modifyingCurrentTemplate],
  )

  const HeaderPortal = usePortal(headerMountPoint)

  const swapLanguages = () => {
    if (targetLangs.length > 1) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Cannot swap languages when multiple target languages are selected!',
        },
        'Warning',
      )
    } else {
      prevSourceLang.current = sourceLang
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        sourceLanguage: targetLangs[0].id,
        targetLanguage: [sourceLang.id],
      }))
    }
  }

  const openTmPanel = () => setOpenSettings({isOpen: true})

  const changeSourceLanguage = (option) => {
    prevSourceLang.current = sourceLang
    setSourceLang(option)
  }

  const getTmKeys = useCallback(() => {
    // Create key from query string
    const keyFromQueryString = {
      r: true,
      w: true,
      owner: true,
      tm: true,
      glos: true,
      name: 'No Description',
      key: tmKeyFromQueryString,
      is_shared: false,
      id: tmKeyFromQueryString,
      isActive: true,
    }

    getTmKeysUser()
      .then(({tm_keys}) => {
        const isMatchingKeyFromQuery = tm_keys.some(
          ({key}) => tmKeyFromQueryString === key,
        )
        setTmKeys([
          ...tm_keys.map((key) => ({
            ...key,
            id: key.key,
            ...(isMatchingKeyFromQuery &&
              key.key === tmKeyFromQueryString && {
                isActive: true,
                r: true,
                w: true,
              }),
          })),
          ...(tmKeyFromQueryString && !isMatchingKeyFromQuery
            ? [keyFromQueryString]
            : []),
        ])
      })
      .catch(() => setTmKeys([]))
  }, [])

  const getMTEngines = useCallback(() => {
    getMtEnginesApi().then((mtEngines) => {
      mtEngines.push(DEFAULT_ENGINE_MEMORY)
      setMtEngines(mtEngines)
    })
  }, [])

  createProject.current = () => {
    const {
      mt,
      tm,
      pretranslate100,
      pretranslate101,
      segmentationRule,
      idTeam,
      getPublicMatches,
      publicTmPenalty,
      qaModelTemplateId,
      payableRateTemplateId,
      XliffConfigTemplateId,
      tmPrioritization,
      characterCounterCountTags,
      characterCounterMode,
      dialectStrict,
      mtQualityValueInEditor,
    } = currentProjectTemplate

    const getTemplateUnsavedById = (id, templates) => {
      const unsavedTemplate = templates
        .filter((template) => template.id === id && template.isTemporary)
        .map(
          ({
            /* eslint-disable */
            isSelected,
            isTemporary,
            id,
            created_at,
            modified_at,
            createdAt,
            deletedAt,
            modifiedAt,
            /* eslint-enable */
            ...result
          }) => result,
        )[0]

      return unsavedTemplate
    }

    const qaModelTemplate = JSON.stringify(
      getTemplateUnsavedById(
        qaModelTemplateId,
        qualityFrameworkTemplates.templates,
      ),
    )
    const payableRateTemplate = JSON.stringify(
      getTemplateUnsavedById(
        payableRateTemplateId,
        analysisTemplates.templates,
      ),
    )
    const xliffParametersTemplate = JSON.stringify(
      getTemplateUnsavedById(
        XliffConfigTemplateId,
        fileImportXliffSettingsTemplates.templates,
      ),
    )

    // update store recently used target languages
    setRecentlyUsedLanguages(targetLangs)
    const getParams = () => ({
      file_name: uploadedFilesNames.join('@@SEP@@'),
      project_name: projectNameRef.current.value,
      source_lang: sourceLang.id,
      target_lang: targetLangs.map((lang) => lang.id).join(),
      job_subject: subject.id,
      mt_engine: mt.id,
      private_keys_list: JSON.stringify({
        ownergroup: [],
        mine: tm,
        anonymous: [],
      }),
      lang_detect_files: '',
      pretranslate_100: pretranslate100 ? 1 : 0,
      pretranslate_101: pretranslate101 ? 1 : 0,
      segmentation_rule: segmentationRule.id === '1' ? '' : segmentationRule.id,
      id_team: idTeam,
      ...(typeof qaModelTemplate !== 'undefined'
        ? {qa_model_template: qaModelTemplate}
        : {qa_model_template_id: qaModelTemplateId}),
      ...(typeof payableRateTemplate !== 'undefined'
        ? {payable_rate_template: payableRateTemplate}
        : {payable_rate_template_id: payableRateTemplateId}),
      get_public_matches: getPublicMatches,
      public_tm_penalty: publicTmPenalty,
      ...(mt?.extra?.glossaries?.length && {
        mmt_glossaries: JSON.stringify({
          glossaries: mt.extra.glossaries,
          ignore_glossary_case: !mt.extra.ignore_glossary_case,
        }),
      }),
      ...(mt?.extra?.deepl_id_glossary && {
        deepl_id_glossary: mt.extra.deepl_id_glossary,
      }),
      ...(mt?.extra?.deepl_formality && {
        deepl_formality: mt.extra.deepl_formality,
      }),
      ...(typeof xliffParametersTemplate !== 'undefined'
        ? {xliff_parameters: xliffParametersTemplate}
        : {xliff_parameters_template_id: XliffConfigTemplateId}),
      tm_prioritization: tmPrioritization ? 1 : 0,
      character_counter_mode: characterCounterMode,
      character_counter_count_tags: characterCounterCountTags,
      ...(dialectStrict && {
        dialect_strict: JSON.stringify(
          targetLangs.reduce(
            (acc, {id}) => ({
              ...acc,
              [id]: true,
            }),
            {},
          ),
        ),
      }),
      mt_quality_value_in_editor: mtQualityValueInEditor,
      ...(mt?.extra?.lara_glossaries?.length && {
        lara_glossaries: JSON.stringify(mt.extra.lara_glossaries),
      }),
    })

    if (!projectSent) {
      setErrors()
      setWarnings()
      setProjectSent(true)
      createProjectApi(getParams())
        .then(({data}) => {
          handleCreationStatus(data.id_project, data.password)
        })
        .catch(({errors}) => {
          let errorMsg
          if (errors && errors.length) {
            switch (errors[0].code) {
              case -230: {
                errorMsg =
                  'Sorry, file name too long. Try shortening it and try again.'
                break
              }
              case -235: {
                errorMsg =
                  'Sorry, an error occurred while creating the project, please try again after refreshing the page.'
                break
              }
              default:
                errorMsg = errors[0].message
            }
          } else {
            errorMsg =
              'Sorry, an error occurred while creating the project, please try again after refreshing the page.'
          }
          setErrors(errorMsg)
          setProjectSent(false)
        })
    }
  }
  const retrieveSupportedLanguages = () => {
    getSupportedLanguages()
      .then((data) => {
        const languages = data.map((lang) => {
          return {...lang, id: lang.code}
        })

        setSupportedLanguages(
          languages.map((language) => {
            const cleaned = language.name
              .normalize('NFD')
              .replace(/[\u0300-\u036f]/g, '')
            return {
              ...language,
              ...(cleaned !== language.name && {cleanedName: cleaned}),
            }
          }),
        )
        ApplicationActions.setLanguages(data)
      })
      .catch((error) =>
        console.log('Error retrieving supported languages', error),
      )
  }
  const checkQueryStringParameter = () => {
    const param = CommonUtils.getParameterByName('open')
    switch (param) {
      case 'signin':
        if (!config.isLoggedIn) {
          ModalsActions.openLoginModal()
        }
        CommonUtils.removeParam('open')
        break
      case 'signup':
        if (!config.isLoggedIn) {
          ModalsActions.openRegisterModal()
        }
        CommonUtils.removeParam('open')
        break
    }
  }

  //TODO: Move it
  useEffect(() => {
    if (typeof selectedTeam?.id !== 'undefined') {
      UserActions.setTeamInStorage(selectedTeam.id)
    }
  }, [selectedTeam])

  useEffect(() => {
    checkQueryStringParameter()
    getSupportedFiles()
      .then((data) => {
        setSupportedFiles(data)
      })
      .catch((error) => console.log('Error retrieving supported files', error))
    if (!isUserLogged) return

    retrieveSupportedLanguages()
    const hideAllErrors = () => {
      setErrors()
      setWarnings()
    }
    const showError = (message) => {
      setErrors(message)
      setProjectSent(false)
    }
    const enableAnalizeButton = (value) => setIsFormReadyToSubmit(value)

    getTmKeys()
    getMTEngines()
    CreateProjectStore.addListener(
      NewProjectConstants.HIDE_ERROR_WARNING,
      hideAllErrors,
    )
    CreateProjectStore.addListener(NewProjectConstants.SHOW_ERROR, showError)
    CreateProjectStore.addListener(
      NewProjectConstants.ENABLE_ANALYZE_BUTTON,
      enableAnalizeButton,
    )

    // check query string project name
    const projectNameFromQuerystring =
      CommonUtils.getParameterByName('project_name')
    if (projectNameFromQuerystring)
      projectNameRef.current.value = projectNameFromQuerystring
    return () => {
      CreateProjectStore.removeListener(
        NewProjectConstants.HIDE_ERROR_WARNING,
        hideAllErrors,
      )
      CreateProjectStore.removeListener(
        NewProjectConstants.SHOW_ERROR,
        showError,
      )
      CreateProjectStore.removeListener(
        NewProjectConstants.ENABLE_ANALYZE_BUTTON,
        enableAnalizeButton,
      )
    }
  }, [getMTEngines, getTmKeys, isUserLogged])

  useEffect(() => {
    if (!Array.isArray(currentProjectTemplate?.tm)) return

    let isTmCreationInProgress = false

    const createKeyFromTMXFile = ({filename}) => {
      const alreadyImportedTmFromFile = tmKeys
        .filter(({key}) =>
          currentProjectTemplate.tm.some((tmCompare) => tmCompare.key === key),
        )
        .some(({isTmFromFile}) => isTmFromFile)

      if (!alreadyImportedTmFromFile && !isTmCreationInProgress) {
        tmCreateRandUser().then((response) => {
          const {key} = response.data
          const tmItem = {
            r: true,
            w: false,
            tm: true,
            glos: true,
            owner: true,
            name: filename,
            key,
            is_shared: false,
            id: key,
            isActive: true,
            isLocked: true,
            isTmFromFile: true,
          }

          flushSync(() =>
            setTmKeys((prevState) => [...(prevState ?? []), tmItem]),
          )

          //eslint-disable-next-line
          const {id, isActive, isLocked, isTmFromFile, ...tmTemplateItem} =
            tmItem

          modifyingCurrentTemplate((prevTemplate) => ({
            ...prevTemplate,
            tm: [tmTemplateItem, ...prevTemplate.tm],
          }))

          isTmCreationInProgress = false
        })

        const message = (
          <span>
            The TMX file(s) you have uploaded will be imported into the newly
            created key <i>{filename}</i>. If you wish to import them into an
            existing key, please use the 'Import TMX' button in the
            <a href="#" onClick={() => setOpenSettings({isOpen: true})}>
              {' '}
              Settings panel
            </a>
            .
          </span>
        )

        setWarnings(message)

        isTmCreationInProgress = true
      }
    }
    CreateProjectStore.addListener(
      NewProjectConstants.CREATE_KEY_FROM_TMX_FILE,
      createKeyFromTMXFile,
    )
    return () => {
      CreateProjectStore.removeListener(
        NewProjectConstants.CREATE_KEY_FROM_TMX_FILE,
        createKeyFromTMXFile,
      )
    }
  }, [currentProjectTemplate?.tm, tmKeys, modifyingCurrentTemplate])

  useEffect(() => {
    if (sourceLang && targetLangs) {
      CreateProjectActions.updateProjectParams({
        sourceLang,
        targetLangs,
        selectedTeam,
      })
    }
  }, [sourceLang, targetLangs, selectedTeam])

  useEffect(() => {
    if (!isDeviceCompatible) {
      const body = document.querySelector('body')
      if (body) body.classList.add('no-min-width')
    }
  }, [isDeviceCompatible])

  // Sync tmKeys state when current project template changed
  useEffect(() => {
    const tm = currentProjectTemplate?.tm ?? []

    setTmKeys((prevState) =>
      Array.isArray(prevState)
        ? prevState.map((tmItem) => {
            const tmFromTemplate = tm.find(({key}) => key === tmItem.key)
            return {
              ...tmItem,
              r: false,
              w: false,
              isActive: false,
              penalty: 0,
              ...(tmFromTemplate && {
                ...tmFromTemplate,
                isActive: true,
              }),
              name: tmItem.name,
            }
          })
        : prevState,
    )
  }, [currentProjectTemplate?.tm])

  // check key from querystring and adding to current template
  useEffect(() => {
    if (
      tmKeyFromQueryString &&
      !isTmKeyFromQueryStringAddedToTemplate &&
      Array.isArray(currentProjectTemplate?.tm) &&
      tmKeys?.length
    ) {
      modifyingCurrentTemplate((prevTemplate) => {
        const isMatched = prevTemplate.tm.some(
          ({key}) => key === tmKeyFromQueryString,
        )
        // eslint-disable-next-line
        const {id, isActive, ...tmFound} = tmKeys.find(
          ({key}) => key === tmKeyFromQueryString,
        )

        return {
          ...prevTemplate,
          tm: isMatched
            ? prevTemplate.tm.map((item) => ({
                ...item,
                ...(item.key === tmKeyFromQueryString && {
                  isActive: true,
                  r: true,
                  w: true,
                }),
              }))
            : [
                ...prevTemplate.tm,
                ...(tmFound ? [{...tmFound, r: true, w: true}] : []),
              ],
        }
      })
      isTmKeyFromQueryStringAddedToTemplate = true
    }
  }, [currentProjectTemplate?.tm, tmKeys, modifyingCurrentTemplate])

  useEffect(() => {
    if (
      typeof currentProjectTemplate?.idTeam === 'number' &&
      typeof idTeamPreset.current === 'number' &&
      !isNaN(idTeamPreset.current)
    ) {
      setSelectedTeam({id: idTeamPreset.current})
      idTeamPreset.current = undefined
    }
  }, [currentProjectTemplate?.idTeam, setSelectedTeam])

  const isLoadingTemplates = !projectTemplates.length

  checkMMTGlossariesWasCancelledIntoTemplates.current({
    engineId: mtEngines.find(({engine_type}) => engine_type === 'MMT')?.id,
    projectTemplates,
  })
  checkDeepLGlossaryWasCancelledIntoTemplates.current({
    engineId: mtEngines.find(({engine_type}) => engine_type === 'DeepL')?.id,
    projectTemplates,
  })

  return isDeviceCompatible ? (
    <CreateProjectContext.Provider
      value={{
        SELECT_HEIGHT,
        tmKeys,
        setTmKeys,
        languages: supportedLanguages,
        targetLangs,
        setTargetLangs,
        setIsOpenMultiselectLanguages,
        sourceLang,
        changeSourceLanguage,
        setOpenSettings,
        isImportTMXInProgress,
        setIsImportTMXInProgress,
        projectTemplates,
        modifyingCurrentTemplate,
        selectedTeam,
        setSelectedTeam,
        subject,
        setSubject,
        openGDrive,
        setOpenGDrive,
        setIsGDriveEnabled,
        currentProjectTemplate,
        uploadedFilesNames,
        setUploadedFilesNames,
        qualityFrameworkTemplates,
        analysisTemplates,
        fileImportFiltersParamsTemplates,
        fileImportXliffSettingsTemplates,
      }}
    >
      <HeaderPortal>
        <Header
          showModals={false}
          showLinks={true}
          loggedUser={isUserLogged}
          user={isUserLogged ? userInfo.user : undefined}
        />
      </HeaderPortal>
      <div className="wrapper-claim">
        <div className="wrapper-claim-content">
          <h1>The CAT tool that works for you</h1>
        </div>
      </div>

      <div className="wrapper-upload">
        <div id="languageSelector" />
        <div className="translation-row">
          <div
            className={`translation-options ${!isUserLogged ? 'user-not-logged' : ''}`}
          >
            {/*Project Name*/}
            <div className="translate-box project-name ">
              <h2>Project name</h2>
              <input
                name="project-name"
                type="text"
                className="upload-input"
                id="project-name"
                autoFocus={isUserLogged ? 'autofocus' : false}
                ref={projectNameRef}
                readOnly={!isUserLogged}
              />
            </div>
            <div className="translate-box">
              <TemplateSelect
                {...{
                  label: 'Project template',
                  maxHeightDroplist: SELECT_HEIGHT,
                  projectTemplates,
                  setProjectTemplates,
                  currentProjectTemplate,
                }}
              />
            </div>
            {/* Team Select*/}
            <div className="translate-box project-team">
              <Select
                label="Team"
                id="project-team"
                name={'project-team'}
                maxHeightDroplist={SELECT_HEIGHT}
                showSearchBar={true}
                options={
                  userInfo?.teams
                    ? userInfo.teams.map((team) => ({
                        ...team,
                        id: team.id.toString(),
                      }))
                    : []
                }
                activeOption={selectedTeam}
                checkSpaceToReverse={false}
                isDisabled={
                  !isUserLogged ||
                  userInfo?.teams.length === 1 ||
                  isLoadingTemplates
                }
                onSelect={(option) => setSelectedTeam(option)}
              />
            </div>
            {/*Source Language*/}
            <div className="translate-box source">
              <SourceLanguageSelect />
            </div>
            <a
              id="swaplang"
              title="Swap languages"
              {...(isUserLogged &&
                !isLoadingTemplates && {onClick: swapLanguages})}
            >
              <span>Swap languages</span>
            </a>
            {/*Target Language*/}
            <div className="translate-box target">
              <TargetLanguagesSelect />
            </div>
            {/*Project Subject*/}
            <div className="translate-box project-subject">
              <Select
                label="Subject"
                id="project-subject"
                name={'project-subject'}
                maxHeightDroplist={SELECT_HEIGHT}
                showSearchBar={true}
                options={subjectsArray}
                activeOption={subject}
                checkSpaceToReverse={false}
                onSelect={(option) => setSubject(option)}
                isDisabled={!isUserLogged || isLoadingTemplates}
              />
            </div>
            {/*TM and glossary*/}
            <div className="translate-box tmx-select">
              <TmGlossarySelect />
            </div>

            <div
              className={`translate-box settings${isLoadingTemplates ? ' settings-disabled' : ''}`}
              {...(!isLoadingTemplates && {onClick: openTmPanel})}
            >
              <More size={24} />
              <span className="text">More settings</span>
            </div>
          </div>
        </div>

        {warnings && (
          <div className="warning-message">
            <i className="icon-warning2 icon"> </i>
            <p>{warnings}</p>
          </div>
        )}

        {errors && (
          <div className="error-message">
            <i className="icon-error_outline icon"> </i>
            <p>{errors}</p>
          </div>
        )}
        <UploadFile />
      </div>
      <div className="wrapper-bottom">
        {conversionEnabled && (
          <p className="supported-files">
            Matecat supports{' '}
            <a
              className="supported-file-formats"
              onClick={() => {
                ModalsActions.showModalComponent(
                  SupportedFilesModal,
                  {supportedFiles: supportedFiles},
                  'Supported file formats',
                  {minWidth: '80%', height: '80%'},
                )
              }}
            >
              {formatsNumber} file formats{' '}
            </a>
            <span style={{float: 'right'}}>.</span>
            {isGDriveEnabled &&
              currentProjectTemplate &&
              uploadedFilesNames.length === 0 && (
                <span className="gdrive-addlink-container">
                  and{' '}
                  <a
                    className="load-gdrive"
                    onClick={() => setOpenGDrive(true)}
                    href="#"
                  >
                    Google Drive files{'  '}
                    <DriveIcon size={16} />
                  </a>
                </span>
              )}
          </p>
        )}
        <div className="uploadbtn-box">
          {!projectSent ? (
            <Button
              size={BUTTON_SIZE.BIG}
              type={BUTTON_TYPE.PRIMARY}
              disabled={
                !isFormReadyToSubmit ||
                isImportTMXInProgress ||
                projectTemplates.length === 0
              }
              className={`uploadbtn${
                !isFormReadyToSubmit ||
                isImportTMXInProgress ||
                projectTemplates.length === 0
                  ? ' disabled'
                  : ''
              }`}
              onClick={createProject.current}
            >
              {' '}
              Analyze
            </Button>
          ) : (
            <>
              <Button
                size={BUTTON_SIZE.BIG}
                type={BUTTON_TYPE.PRIMARY}
                className={'uploadbtn disabled'}
                disabled={true}
              >
                <span className="uploadloader" />
                Analyzing...
              </Button>
            </>
          )}
        </div>
      </div>
      {isOpenMultiselectLanguages && (
        <LanguageSelector
          selectedLanguagesFromDropdown={
            targetLangs.length > 1 ? targetLangs.map(({code}) => code) : []
          }
          languagesList={supportedLanguages}
          fromLanguage={CreateProjectStore.getSourceLang()}
          onClose={() => setIsOpenMultiselectLanguages(false)}
          onConfirm={(data) => {
            if (data.length)
              setTargetLangs(data.map((item) => ({...item, id: item.code})))
            setIsOpenMultiselectLanguages(false)
          }}
        />
      )}
      {isUserLogged && projectTemplates.length > 0 && (
        <SettingsPanel
          {...{
            onClose: closeSettings,
            isOpened: openSettings.isOpen,
            tabOpen: openSettings.tab,
            user: userInfo,
            tmKeys,
            setTmKeys,
            mtEngines,
            setMtEngines,
            sourceLang,
            targetLangs,
            projectTemplates,
            setProjectTemplates,
            modifyingCurrentTemplate,
            currentProjectTemplate,
            checkSpecificTemplatePropsAreModified,
            qualityFrameworkTemplates,
            analysisTemplates,
            fileImportFiltersParamsTemplates,
            fileImportXliffSettingsTemplates,
          }}
        />
      )}
      <HomePageSection />
      <Footer />
      <SocketListener
        isAuthenticated={isUserLogged}
        userId={isUserLogged ? userInfo.user.uid : null}
      />
      <OnboardingTooltips
        show={isUserLogged && userInfo.user}
        continous={true}
        page={ONBOARDING_PAGE.HOME}
      />
    </CreateProjectContext.Provider>
  ) : (
    <div>
      <HeaderPortal>
        <Header
          showModals={false}
          showLinks={false}
          loggedUser={false}
          showUserMenu={false}
        />
      </HeaderPortal>
      <div className="not-supported-container">
        <h1>Use Matecat from your desktop</h1>
        <p>
          Matecat is not available for mobile devices, you can use it on your
          desktop with the browser of your choice.
        </p>
        <div className="buttons">
          <a href="https://site.matecat.com/" className="ui primary button">
            Find out more about Matecat
          </a>
        </div>
      </div>
    </div>
  )
}
export default NewProject

mountPage({
  Component: NewProject,
  rootElement: document.getElementsByClassName('new_project__page')[0],
})
