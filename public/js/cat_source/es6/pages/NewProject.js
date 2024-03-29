import React, {useEffect, useRef, useState, useCallback} from 'react'
import PropTypes from 'prop-types'
import usePortal from '../hooks/usePortal'
import Header from '../components/header/Header'
import TeamsStore from '../stores/TeamsStore'
import TeamConstants from '../constants/TeamConstants'
import {Select} from '../components/common/Select'
import ModalsActions from '../actions/ModalsActions'
import AlertModal from '../components/modals/AlertModal'
import {getTmKeysUser} from '../api/getTmKeysUser'
import More from '../../../../img/icons/More'
import UploadFile from '../components/createProject/UploadFile'
import SupportedFilesModal from '../components/modals/SupportedFilesModal'
import Footer from '../components/footer/Footer'
import {createProject as createProjectApi} from '../api/createProject'
import CreateProjectActions from '../actions/CreateProjectActions'
import LanguageSelector from '../components/languageSelector/LanguageSelector'
import CreateProjectStore from '../stores/CreateProjectStore'
import NewProjectConstants from '../constants/NewProjectConstants'
import {CreateProjectContext} from '../components/createProject/CreateProjectContext'
import {TargetLanguagesSelect} from '../components/createProject/TargetLanguagesSelect'
import {TmGlossarySelect} from '../components/createProject/TmGlossarySelect'
import {SourceLanguageSelect} from '../components/createProject/SourceLanguageSelect'
import CommonUtils from '../utils/commonUtils'
import {
  DEFAULT_ENGINE_MEMORY,
  MMT_NAME,
  SettingsPanel,
} from '../components/settingsPanel'
import {getMTEngines as getMtEnginesApi} from '../api/getMTEngines'
import SegmentUtils from '../utils/segmentUtils'
import {tmCreateRandUser} from '../api/tmCreateRandUser'
import {getTmDataStructureToSendServer} from '../components/settingsPanel/Contents/TranslationMemoryGlossaryTab'
import {getSupportedFiles} from '../api/getSupportedFiles'
import {getSupportedLanguages} from '../api/getSupportedLanguages'
import ApplicationActions from '../actions/ApplicationActions'
import useDeviceCompatibility from '../hooks/useDeviceCompatibility'
import {useGoogleLoginNotification} from '../hooks/useGoogleLoginNotification'

const SELECT_HEIGHT = 324

const historySourceTargets = {
  // source: 'es-ES',
  // targets: 'it-IT,es-ES,es-MX||',
}

const urlParams = new URLSearchParams(window.location.search)
const initialStateIsOpenSettings = Boolean(urlParams.get('openTab'))
const tmKeyFromQueryString = urlParams.get('private_tm_key')

const NewProject = ({
  isLoggedIn = false,
  sourceLanguageSelected,
  targetLanguagesSelected,
  subjectsArray,
  conversionEnabled,
  formatsNumber,
  googleDriveEnabled,
  restartConversions,
}) => {
  const [user, setUser] = useState()
  const [tmKeys, setTmKeys] = useState()
  const [keysOrdered, setKeysOrdered] = useState()
  const [mtEngines, setMtEngines] = useState([DEFAULT_ENGINE_MEMORY])
  const [activeMTEngine, setActiveMTEngine] = useState(DEFAULT_ENGINE_MEMORY)
  const [selectedTeam, setSelectedTeam] = useState()
  const [sourceLang, setSourceLang] = useState({})
  const [targetLangs, setTargetLangs] = useState([])
  const [subject, setSubject] = useState(subjectsArray[0])
  const [projectSent, setProjectSent] = useState(false)
  const [errors, setErrors] = useState()
  const [warnings, setWarnings] = useState()
  const [isOpenMultiselectLanguages, setIsOpenMultiselectLanguages] =
    useState(false)
  const [openSettings, setOpenSettings] = useState({
    isOpen: initialStateIsOpenSettings,
  })
  const [speechToTextActive, setSpeechToTextActive] = useState(
    config.defaults.speech2text,
  )
  const [guessTagActive, setGuessTagActive] = useState(
    !!config.defaults.tag_projection,
  )
  const [lexiqaActive, setLexiqaActive] = useState(!!config.defaults.lexiqa)
  const [multiMatchLangs, setMultiMatchLangs] = useState(
    SegmentUtils.checkCrossLanguageSettings(),
  )
  const [segmentationRule, setSegmentationRule] = useState({
    name: 'General',
    id: 'standard',
  })
  const [isPretranslate100Active, setIsPretranslate100Active] = useState(false)
  const [getPublicMatches, setGetPublicMatches] = useState(
    Boolean(config.get_public_matches),
  )
  const [isImportTMXInProgress, setIsImportTMXInProgress] = useState(false)
  const [isFormReadyToSubmit, setIsFormReadyToSubmit] = useState(false)
  const [supportedFiles, setSupportedFiles] = useState()
  const [supportedLanguages, setSupportedLanguages] = useState()

  const isDeviceCompatible = useDeviceCompatibility()

  // TODO: Remove temp notification warning login google (search in files this todo)
  useGoogleLoginNotification()

  const projectNameRef = useRef()
  const prevSourceLang = useRef(sourceLang)
  const createProject = useRef()

  const closeSettings = useCallback(() => setOpenSettings({isOpen: false}), [])

  const headerMountPoint = document.querySelector('header.upload-page-header')
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
      setSourceLang(targetLangs[0])
      setTargetLangs([sourceLang])
    }
  }

  const openTmPanel = () => setOpenSettings({isOpen: true})

  const changeSourceLanguage = (option) => {
    prevSourceLang.current = sourceLang
    setSourceLang(option)
  }

  const getTmKeys = () => {
    // Create key from query string
    const keyFromQueryString = {
      r: true,
      w: true,
      owner: true,
      name: 'No Description',
      key: tmKeyFromQueryString,
      is_shared: false,
      id: tmKeyFromQueryString,
      isActive: true,
    }

    if (config.isLoggedIn) {
      getTmKeysUser().then(({tm_keys}) => {
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
    } else {
      setTmKeys([...(tmKeyFromQueryString ? [keyFromQueryString] : [])])
    }
  }

  const getMTEngines = () => {
    if (config.isLoggedIn) {
      getMtEnginesApi().then((mtEngines) => {
        mtEngines.push(DEFAULT_ENGINE_MEMORY)
        setMtEngines(mtEngines)
        if (config.isAnInternalUser) {
          const mmt = mtEngines.find((mt) => mt.name === MMT_NAME)
          if (mmt) {
            setActiveMTEngine(mmt)
          }
        }
      })
    }
  }

  createProject.current = () => {
    const {mtGlossaryProps, deeplGlossaryProps} = activeMTEngine ?? {}

    const getParams = () => ({
      action: 'createProject',
      file_name: APP.getFilenameFromUploadedFiles(),
      project_name: projectNameRef.current.value,
      source_lang: sourceLang.id,
      target_lang: targetLangs.map((lang) => lang.id).join(),
      job_subject: subject.id,
      mt_engine: activeMTEngine ? activeMTEngine.id : undefined,
      private_keys_list: getTmDataStructureToSendServer({tmKeys, keysOrdered}),
      lang_detect_files: '',
      pretranslate_100: isPretranslate100Active ? 1 : 0,
      lexiqa: lexiqaActive,
      speech2text: speechToTextActive,
      tag_projection: guessTagActive,
      segmentation_rule: segmentationRule.id === '1' ? '' : segmentationRule.id,
      id_team: selectedTeam ? selectedTeam.id : undefined,
      get_public_matches: getPublicMatches,
      ...(mtGlossaryProps?.glossaries.length && {
        mmt_glossaries: JSON.stringify({
          glossaries: mtGlossaryProps.glossaries,
          ignore_glossary_case: !mtGlossaryProps.isGlossaryCaseSensitive,
        }),
      }),
      ...(typeof deeplGlossaryProps === 'object' && {
        ...Object.entries(deeplGlossaryProps)
          .filter(([, value]) => value)
          .reduce((acc, [key, value]) => ({...acc, [key]: value}), {}),
      }),
    })

    if (!projectSent) {
      setErrors()
      setWarnings()
      setProjectSent(true)
      createProjectApi(getParams())
        .then(({data}) => {
          APP.handleCreationStatus(data.id_project, data.password)
        })
        .catch((errors) => {
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
        setSupportedLanguages(languages)
        setSourceLang(
          sourceLanguageSelected
            ? languages.find((lang) => lang.id === sourceLanguageSelected)
            : languages[0],
        )
        setTargetLangs(
          targetLanguagesSelected
            ? languages.filter(
                (lang) => targetLanguagesSelected.indexOf(lang.id) > -1,
              )
            : [languages[0]],
        )
        ApplicationActions.setLanguages(data)
      })
      .catch((error) =>
        console.log('Error retrieving supported languages', error),
      )
  }

  //TODO: Move it
  useEffect(() => {
    if (selectedTeam) {
      APP.setTeamInStorage(selectedTeam.id)
    }
  }, [selectedTeam])

  useEffect(() => {
    retrieveSupportedLanguages()
    getSupportedFiles()
      .then((data) => {
        setSupportedFiles(data)
      })
      .catch((error) => console.log('Error retrieving supported files', error))

    UI.addEvents()
    setGuessTagActive(
      SegmentUtils.checkGuessTagCanActivate(sourceLang, targetLangs),
    )
    const updateUser = (user) => {
      setUser(user)
      setSelectedTeam(
        APP.getLastTeamSelected(
          user.teams.map((team) => ({...team, id: team.id.toString()})),
        ),
      )
    }
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
    TeamsStore.addListener(TeamConstants.UPDATE_USER, updateUser)
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
    APP.checkGDriveEvents()
    return () => {
      TeamsStore.removeListener(TeamConstants.UPDATE_USER, updateUser)
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
  }, [])
  useEffect(() => {
    const createKeyFromTMXFile = ({extension, filename}) => {
      const haveNoActiveKeys = tmKeys.every(({isActive}) => !isActive)

      if (haveNoActiveKeys) {
        tmCreateRandUser().then((response) => {
          const {key} = response.data
          setTmKeys((prevState) => [
            ...(prevState ?? []),
            {
              r: true,
              w: true,
              tm: true,
              glos: true,
              owner: true,
              name: filename,
              key,
              is_shared: false,
              id: key,
              isActive: true,
            },
          ])
        })
      }

      const glossaryMessage = (
        <span>
          A new resource has been generated for the glossary you uploaded. You
          can manage your resources in the{' '}
          <a href="#" onClick={() => setOpenSettings({isOpen: true})}>
            Settings panel
          </a>
          .
        </span>
      )

      const tmMessage = haveNoActiveKeys ? (
        <span>
          A new resource has been generated for the TMX you uploaded. You can
          manage your resources in the{' '}
          <a href="#" onClick={() => setOpenSettings({isOpen: true})}>
            {' '}
            Settings panel
          </a>
          .
        </span>
      ) : (
        <span>
          The TMX file(s) you have uploaded have been imported into the active
          private key(s)
        </span>
      )

      const message = extension === 'g' ? glossaryMessage : tmMessage
      setWarnings(message)
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
  }, [tmKeys])

  useEffect(() => {
    if (sourceLang) {
      const lang = sourceLang.id
      if (lang && localStorage.getItem('currentSourceLang') !== lang) {
        localStorage.setItem('currentSourceLang', lang)
      }
    }
    if (targetLangs) {
      const lang = targetLangs.map((lang) => lang.id).join()
      if (lang && localStorage.getItem('currentTargetLang') !== lang) {
        localStorage.setItem('currentTargetLang', lang)
      }
    }
    if (sourceLang && targetLangs) {
      setGuessTagActive(
        SegmentUtils.checkGuessTagCanActivate(sourceLang, targetLangs),
      )
      CreateProjectActions.updateProjectParams({
        sourceLang,
        targetLangs,
        selectedTeam,
      })
      if (prevSourceLang.current.id !== sourceLang.id) {
        prevSourceLang.current = sourceLang
        restartConversions()
      }
    }
  }, [sourceLang, targetLangs, selectedTeam])

  useEffect(() => {
    //TODO: used in main.js, remove
    UI.segmentationRule = segmentationRule.id
    restartConversions()
  }, [segmentationRule])

  useEffect(() => {
    if (!isDeviceCompatible) {
      const body = document.querySelector('body')
      if (body) body.classList.add('no-min-width')
    }
  }, [isDeviceCompatible])

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
        isPretranslate100Active,
        setIsPretranslate100Active,
      }}
    >
      <HeaderPortal>
        <Header
          showModals={false}
          showLinks={true}
          loggedUser={isLoggedIn}
          user={user}
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
          <div className="translation-options">
            {/*Project Name*/}
            <div className="translate-box project-name">
              <h2>Project name</h2>
              <input
                name="project-name"
                type="text"
                className="upload-input"
                id="project-name"
                autoFocus="autofocus"
                ref={projectNameRef}
              />
            </div>
            {/* Team Select*/}
            {isLoggedIn && (
              <div className="translate-box project-team">
                <Select
                  label="Team"
                  id="project-team"
                  name={'project-team'}
                  maxHeightDroplist={SELECT_HEIGHT}
                  showSearchBar={true}
                  options={
                    user?.teams
                      ? user.teams.map((team) => ({
                          ...team,
                          id: team.id.toString(),
                        }))
                      : []
                  }
                  activeOption={selectedTeam}
                  checkSpaceToReverse={false}
                  isDisabled={!user || user.teams.length === 1}
                  onSelect={(option) => setSelectedTeam(option)}
                />
              </div>
            )}
            {/*Source Language*/}
            <div className="translate-box source">
              <SourceLanguageSelect
                history={
                  historySourceTargets?.source
                    ? historySourceTargets.source.split(',')
                    : []
                }
              />
            </div>
            <a id="swaplang" title="Swap languages" onClick={swapLanguages}>
              <span>Swap languages</span>
            </a>
            {/*Target Language*/}
            <div className="translate-box target">
              <TargetLanguagesSelect
                history={
                  historySourceTargets?.targets
                    ? historySourceTargets.targets
                        .split('||')
                        .flatMap((item) =>
                          item.length ? [item.split(',')] : [],
                        )
                    : []
                }
              />
            </div>
            {/*Project Subject*/}
            <div className="translate-box project-subject">
              <Select
                label="Select subject"
                id="project-subject"
                name={'project-subject'}
                maxHeightDroplist={SELECT_HEIGHT}
                showSearchBar={true}
                options={subjectsArray}
                activeOption={subject}
                checkSpaceToReverse={false}
                onSelect={(option) => setSubject(option)}
              />
            </div>
            {/*TM and glossary*/}
            <div className="translate-box tmx-select">
              <TmGlossarySelect />
            </div>
            <div className="translate-box settings" onClick={openTmPanel}>
              <More size={24} />
              <span className="text">More settings</span>
            </div>
          </div>
        </div>

        {warnings && (
          <div className="warning-message">
            <i className="icon-warning2 icon"> </i>
            <p
              dangerouslySetInnerHTML={{
                __html: warnings,
              }}
            />
          </div>
        )}

        {errors && (
          <div className="error-message">
            <i className="icon-error_outline icon"> </i>
            <p
              dangerouslySetInnerHTML={{
                __html: errors,
              }}
            />
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
            {googleDriveEnabled && (
              <span className="gdrive-addlink-container">
                and{' '}
                <a className="load-gdrive load-gdrive-disabled" href="#">
                  Google Drive files
                </a>
                <span className="gdrive-icon"></span>
              </span>
            )}
          </p>
        )}
        <div className="uploadbtn-box">
          {!projectSent ? (
            <input
              disabled={!isFormReadyToSubmit || isImportTMXInProgress}
              name=""
              type="button"
              className={`uploadbtn${
                !isFormReadyToSubmit || isImportTMXInProgress ? ' disabled' : ''
              }`}
              value="Analyze"
              onClick={createProject.current}
            />
          ) : (
            <>
              <span className="uploadloader" />
              <input
                name=""
                type="button"
                className="uploadbtn disabled"
                value="Analyzing..."
                disabled="disabled"
              />
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
      <SettingsPanel
        {...{
          onClose: closeSettings,
          isOpened: openSettings.isOpen,
          tabOpen: openSettings.tab,
          tmKeys,
          setTmKeys,
          mtEngines,
          setMtEngines,
          activeMTEngine,
          setActiveMTEngine,
          speechToTextActive,
          setSpeechToTextActive,
          guessTagActive,
          setGuessTagActive,
          sourceLang,
          targetLangs,
          lexiqaActive,
          setLexiqaActive,
          multiMatchLangs,
          setMultiMatchLangs,
          segmentationRule,
          setSegmentationRule,
          setGetPublicMatches,
          setKeysOrdered,
        }}
      />
      <Footer />
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
NewProject.propTypes = {
  isLoggedIn: PropTypes.bool,
  sourceLanguageSelected: PropTypes.string,
  targetLanguagesSelected: PropTypes.string,
  subjectsArray: PropTypes.array,
  conversionEnabled: PropTypes.bool,
  formatsNumber: PropTypes.number,
  googleDriveEnabled: PropTypes.bool,
  restartConversions: PropTypes.func,
}
export default NewProject
