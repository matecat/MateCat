import React, {useEffect, useRef, useState} from 'react'
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

const SELECT_HEIGHT = 260

const NewProject = ({
  isLoggedIn = false,
  languages,
  sourceLanguageSelected,
  targetLanguagesSelected,
  subjectsArray,
  conversionEnabled,
  formatsNumber,
  googleDriveEnabled,
  supportedFiles,
}) => {
  const projectNameRef = useRef()
  const [user, setUser] = useState()
  const [tmKeys, setTmKeys] = useState()
  const [tmKeySelected, setTmKeySelected] = useState([])
  const [selectedTeam, setSelectedTeam] = useState()
  const [sourceLang, setSourceLang] = useState(
    sourceLanguageSelected
      ? languages.find((lang) => lang.id === sourceLanguageSelected)
      : languages[0],
  )
  const [targetLangs, setTargetLangs] = useState(
    targetLanguagesSelected
      ? languages.filter(
          (lang) => targetLanguagesSelected.indexOf(lang.id) > -1,
        )
      : [languages[0]],
  )
  const [subject, setSubject] = useState(subjectsArray[0])
  const [projectSent, setProjectSent] = useState(false)
  const [errors, setErrors] = useState()
  const [warnings, setWarnings] = useState()
  const [isOpenMultiselectLanguages, setIsOpenMultiselectLanguages] =
    useState(false)

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
      setSourceLang(targetLangs[0])
      setTargetLangs([sourceLang])
      UI.UPLOAD_PAGE.restartConversions()
    }
  }

  const openTmPanel = () => {
    APP.openOptionsPanel('tm')
  }

  const changeSourceLanguage = (option) => {
    setSourceLang(option)
    APP.sourceLangChangedCallback()
    APP.checkForTagProjectionLangs(sourceLang)
  }

  const getTmKeys = () => {
    getTmKeysUser().then(({tm_keys}) =>
      setTmKeys(
        tm_keys.map((key) => {
          return {...key, id: key.key}
        }),
      ),
    )
  }

  const createProject = () => {
    if (!projectSent) {
      if (!UI.allTMUploadsCompleted()) {
        return false
      }
      setErrors()
      setWarnings()
      setProjectSent(true)
      createProjectApi(
        APP.getCreateProjectParams({
          projectName: projectNameRef.current.value,
          sourceLang: sourceLang.id,
          targetLang: targetLangs.map((lang) => lang.id).join(),
          jobSubject: subject.id,
          selectedTeam: selectedTeam.id,
        }),
      )
        .then(({data}) => {
          APP.handleCreationStatus(data.id_project, data.password)
        })
        .catch((errors) => {
          let errorMsg
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
          setErrors(errorMsg)
          setProjectSent(false)
        })
    }
  }

  //TODO: Move it
  useEffect(() => {
    if (selectedTeam) {
      APP.setTeamInStorage(selectedTeam.id)
    }
  }, [selectedTeam])

  useEffect(() => {
    if (sourceLang) {
      APP.changeSourceLang(sourceLang.id)
    }
    if (targetLangs) {
      APP.changeTargetLang(targetLangs.map((lang) => lang.id).join())
    }
    APP.checkForLexiQALangs(sourceLang)
    APP.checkForTagProjectionLangs(sourceLang)
  }, [sourceLang, targetLangs])

  useEffect(() => {
    APP.checkForSpeechToText()
    APP.checkForDqf()
    APP.checkGDriveEvents()
    UI.addEvents()

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
    const showError = (message) => setErrors(message)

    const showWarning = (message) => {
      console.log('setWarnings-------------->', message)
      setWarnings(message)
    }

    getTmKeys()
    TeamsStore.addListener(TeamConstants.UPDATE_USER, updateUser)
    CreateProjectStore.addListener(
      NewProjectConstants.HIDE_ERROR_WARNING,
      hideAllErrors,
    )
    CreateProjectStore.addListener(NewProjectConstants.SHOW_ERROR, showError)
    CreateProjectStore.addListener(
      NewProjectConstants.SHOW_WARNING,
      showWarning,
    )
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
        NewProjectConstants.SHOW_WARNING,
        showWarning,
      )
    }
  }, [])
  useEffect(() => {
    const activateKey = (event, desc, key) => {
      let tmSelected = tmKeys.find((item) => item.id === key)
      if (!tmSelected) {
        tmSelected = {id: key, name: desc}
        setTmKeys(tmKeys.concat(tmSelected))
      }
      if (!tmKeySelected.find((item) => item.id === key)) {
        setTmKeySelected(tmKeySelected.concat([tmSelected]))
      }
    }
    const deactivateKey = (event, key) => {
      setTmKeySelected(tmKeySelected.filter((item) => item.id !== key))
    }
    const removeKey = () => {
      getTmKeys()
    }
    $('#activetm').on('update', activateKey)
    $('#activetm').on('removeTm', deactivateKey)
    $('#activetm').on('deleteTm', removeKey)
    return () => {
      $('#activetm').off('update')
      $('#activetm').off('removeTm')
      $('#activetm').off('deleteTm')
    }
  }, [tmKeys, tmKeySelected])

  useEffect(
    () =>
      CreateProjectActions.updateProjectParams({
        sourceLang,
        targetLangs,
        selectedTeam,
      }),
    [sourceLang, targetLangs, selectedTeam],
  )

  return (
    <>
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
        <div id="matecat-cat" />
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
              <Select
                label="From"
                name={'source-lang'}
                maxHeightDroplist={SELECT_HEIGHT}
                showSearchBar={true}
                options={languages}
                activeOption={sourceLang}
                checkSpaceToReverse={false}
                onSelect={(option) => changeSourceLanguage(option)}
              />
            </div>
            <a
              id="swaplang"
              title="Swap languages"
              onClick={() => swapLanguages()}
            >
              <span>Swap languages</span>
            </a>
            {/*Target Language*/}
            <div className="translate-box target">
              <Select
                label="To"
                name={'target-lang'}
                maxHeightDroplist={SELECT_HEIGHT}
                showSearchBar={true}
                options={languages}
                multipleSelect={'dropdown'}
                activeOptions={targetLangs}
                checkSpaceToReverse={false}
                onToggleOption={(option, onClose) => {
                  if (
                    targetLangs.length > 1 &&
                    targetLangs.find(({id}) => id === option.id)
                  ) {
                    setTargetLangs((prevState) =>
                      prevState.filter(({id}) => id !== option.id),
                    )
                  } else {
                    setTargetLangs([option])
                    onClose()
                  }
                }}
              >
                {({index, onClose}) => ({
                  ...(index === 0 && {
                    beforeRow: (
                      <button
                        className="button-multiple-languages"
                        onClick={() => {
                          setIsOpenMultiselectLanguages(true)
                          onClose()
                        }}
                      >
                        MULTIPLE LANGUAGES
                        <span className="icon-plus3 icon"></span>
                      </button>
                    ),
                  }),
                })}
              </Select>
            </div>
            {/*Project Subject*/}
            <div className="translate-box project-subject">
              <Select
                label="Select subject"
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
              <Select
                label={
                  <div className="label-tmx-select">
                    <span>TM & Glossary</span>
                    <span
                      aria-label="By updating MyMemory, you are contributing to making MateCat better 
                      and helping fellow MateCat users improve their translations.
                      For confidential projects, we suggest adding a private TM and selecting the Update option in the Settings panel."
                      tooltip-position="bottom"
                    >
                      <span className="icon-info icon" />
                    </span>
                  </div>
                }
                name={'tmx-select'}
                maxHeightDroplist={SELECT_HEIGHT}
                showSearchBar={true}
                isDisabled={!tmKeys}
                options={tmKeys}
                multipleSelect={'dropdown'}
                activeOptions={tmKeySelected}
                placeholder={'MyMemory Collaborative TM'}
                checkSpaceToReverse={false}
                onToggleOption={(option) => {
                  if (tmKeySelected?.some((item) => item.id === option.id)) {
                    setTmKeySelected(
                      tmKeySelected.filter((item) => item.id !== option.id),
                    )
                    UI.disableTm(option.id)
                  } else {
                    setTmKeySelected(tmKeySelected.concat([option]))
                    UI.selectTm(option.id)
                  }
                }}
              >
                {({index, onClose}) => ({
                  ...(index === 0 && {
                    beforeRow: (
                      <button
                        className="button-multiple-languages"
                        onClick={() => {
                          UI.openLanguageResourcesPanel('tm')
                          onClose()
                        }}
                      >
                        CREATE RESOURCE
                        <span className="icon-plus3 icon"></span>
                      </button>
                    ),
                  }),
                })}
              </Select>
            </div>
            <div
              className="translate-box settings"
              onClick={() => openTmPanel()}
            >
              <More size={24} />
              <span className="text">More settings</span>
            </div>
          </div>
        </div>
        {/* TODO: ERROR MESSAGES*/}
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
                  {supportedFiles},
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
              name=""
              type="button"
              className="uploadbtn disabled"
              value="Analyze"
              onClick={() => createProject()}
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
          <p className="enter">Press Enter</p>
        </div>
      </div>
      {isOpenMultiselectLanguages && (
        <LanguageSelector
          selectedLanguagesFromDropdown={CreateProjectStore.getTargetLangs().split(
            ',',
          )}
          languagesList={config.languages_array}
          fromLanguage={CreateProjectStore.getSourceLang()}
          onClose={() => setIsOpenMultiselectLanguages(false)}
          onConfirm={(data) => {
            setTargetLangs(data.map((item) => ({...item, id: item.code})))
            setIsOpenMultiselectLanguages(false)
          }}
        />
      )}
      <Footer />
    </>
  )
}

export default NewProject
