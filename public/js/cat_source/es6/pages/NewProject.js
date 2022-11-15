import React, {useEffect, useState, useRef} from 'react'
import Header from '../components/header/Header'
import TeamsStore from '../stores/TeamsStore'
import TeamConstants from '../constants/TeamConstants'
import {Select} from '../components/common/Select'
import ModalsActions from '../actions/ModalsActions'
import AlertModal from '../components/modals/AlertModal'
import {getTmKeysUser} from '../api/getTmKeysUser'
import More from "../../../../img/icons/More";

const NewProject = ({
  isLoggedIn = false,
  languages,
  sourceLanguageSelected,
  targetLanguagesSelected,
  subjectsArray,
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

  const openTmPanel = ()=> {
    APP.openOptionsPanel('tm')
  }

  //TODO: Move it
  useEffect(() => {
    if (selectedTeam) {
      APP.setTeamInStorage(selectedTeam.id)
    }
  }, [selectedTeam])

  //TODO: Move it
  useEffect(() => {
    if (sourceLang) {
      APP.changeSourceLang(sourceLang.id)
    }
  }, [sourceLang])

  //TODO: Move it
  useEffect(() => {
    if (targetLangs) {
      APP.changeTargetLang(targetLangs.map((lang) => lang.id).join())
    }
  }, [targetLangs])

  useEffect(() => {
    const updateUser = (user) => {
      setUser(user)
      setSelectedTeam(APP.getLastTeamSelected(user.teams))
    }
    getTmKeysUser().then(({tm_keys}) =>
      setTmKeys(
        tm_keys.map((key) => {
          return {...key, id: key.key}
        }),
      ),
    )
    TeamsStore.addListener(TeamConstants.UPDATE_USER, updateUser)
    return () => {
      TeamsStore.removeListener(TeamConstants.UPDATE_USER, updateUser)
    }
  }, [])
  return (
    <>
      <header className="upload-page-header">
        <Header
          showModals={false}
          showLinks={true}
          loggedUser={isLoggedIn}
          user={user}
        />
      </header>
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
                onSelect={(option) => setSourceLang(option)}
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
                  if (targetLangs.some((lang) => lang.id === option.id)) {
                    setTargetLangs(
                      targetLangs.filter((lang) => lang.id !== option.id),
                    )
                  } else {
                    setTargetLangs(targetLangs.concat([option]))
                  }
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
                  } else {
                    setTmKeySelected(tmKeySelected.concat([option]))
                  }
                }}
              />
            </div>
            <div className="translate-box settings" onClick={()=>openTmPanel()}>
              <More size={24}/>
              <span className="text">More settings</span>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default NewProject