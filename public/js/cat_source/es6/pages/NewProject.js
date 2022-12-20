import React, {useEffect, useState} from 'react'
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
      UI.restartConversions()
    }
  }

  const openTmPanel = () => {
    APP.openOptionsPanel('tm')
  }

  const changeSourceLanguage = (option) => {
    setSourceLang(option)
    UI.UPLOAD_PAGE.sourceLangChangedCallback()
    APP.checkForTagProjectionLangs()
  }

  const changeTargetLanguage = (option) => {
    if (targetLangs.some((lang) => lang.id === option.id)) {
      setTargetLangs(targetLangs.filter((lang) => lang.id !== option.id))
    } else {
      setTargetLangs(targetLangs.concat([option]))
    }
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

    const updateUser = (user) => {
      setUser(user)
      setSelectedTeam(APP.getLastTeamSelected(user.teams))
    }
    getTmKeys()
    TeamsStore.addListener(TeamConstants.UPDATE_USER, updateUser)

    return () => {
      TeamsStore.removeListener(TeamConstants.UPDATE_USER, updateUser)
    }
  }, [])
  useEffect(() => {
    const activateKey = (event, desc, key) => {
      const tmSelected = tmKeys.find((item) => item.id === key)
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
              />
            </div>
            {/* Team Select*/}
            {isLoggedIn && (
              <div className="translate-box project-team">
                <Select
                  label="Team"
                  name={'project-team'}
                  showSearchBar={true}
                  options={user?.teams ? user.teams : []}
                  activeOption={selectedTeam}
                  checkSpaceToReverse={false}
                  isDisabled={!user || user.teams.length == 1}
                  onSelect={(option) => setSelectedTeam(option)}
                />
              </div>
            )}
            {/*Source Language*/}
            <div className="translate-box source">
              <Select
                label="From"
                name={'source-lang'}
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
                showSearchBar={true}
                options={languages}
                multipleSelect={'dropdown'}
                activeOptions={targetLangs}
                checkSpaceToReverse={false}
                onToggleOption={(option) => {
                  changeTargetLanguage(option)
                }}
              />
            </div>
            {/*Project Subject*/}
            <div className="translate-box project-subject">
              <Select
                label="Select subject"
                name={'project-subject'}
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
                label="TM & Glossary"
                name={'tmx-select'}
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
                    UI.UPLOAD_PAGE.disableTm(option.id)
                  } else {
                    setTmKeySelected(tmKeySelected.concat([option]))
                    UI.UPLOAD_PAGE.selectTm(option.id)
                  }
                }}
              />
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
          <span className="uploadloader"></span>
          <input
            name=""
            type="button"
            className="uploadbtn disabled"
            value="Analyze"
            disabled="disabled"
          />
          <p className="enter">Press Enter</p>
        </div>
      </div>
      <Footer />
    </>
  )
}

export default NewProject
