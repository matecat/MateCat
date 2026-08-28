import React, {useState, useRef, useEffect} from 'react'

import LanguageSelectorList from './LanguageSelectorList'
import LanguageSelectorSearch from './LanguageSelectorSearch'
import LabelWithTooltip from '../common/LabelWithTooltip'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import FlipBackwardIcon from '../../../img/icons/FlipBackwardIcon'
import Close from '../../../img/icons/Close'

const RECENTLY_USED_LOCAL_STORAGE_KEY = `target_languages_recently_used-${config.userMail}`
const MAX_RECENTLY_USED_STORED = 3

const getRecentyUsedLanguages = () =>
  JSON.parse(localStorage.getItem(RECENTLY_USED_LOCAL_STORAGE_KEY) ?? '[]')
export const setRecentlyUsedLanguages = (languages) => {
  if (!languages.length) return

  const collection = JSON.parse(
    localStorage.getItem(RECENTLY_USED_LOCAL_STORAGE_KEY) ?? '[]',
  )

  const indexAlreadyExistingCombination = collection.findIndex(
    (list) =>
      languages.every(({id}) => list.some((item) => item.id === id)) &&
      languages.length === list.length,
  )

  const collectionWithoutDuplicates = collection.filter(
    (item, index) => index !== indexAlreadyExistingCombination,
  )

  const newCollection =
    collectionWithoutDuplicates.length >= MAX_RECENTLY_USED_STORED
      ? [
          ...collectionWithoutDuplicates.filter((item, index) => index > 0),
          languages,
        ]
      : [...collectionWithoutDuplicates, languages]

  localStorage.setItem(
    RECENTLY_USED_LOCAL_STORAGE_KEY,
    JSON.stringify(newCollection),
  )
}

const LanguageSelector = (props) => {
  const {
    languagesList,
    onClose,
    selectedLanguagesFromDropdown,
    fromLanguage: fromLanguageProp,
  } = props

  const [selectedLanguages, setSelectedLanguages] = useState(null)
  // `initialLanguages` is write-only dead state, preserved verbatim from the
  // class component (set once at mount, never read anywhere).
  const [, setInitialLanguages] = useState(null)
  const [fromLanguage, setFromLanguage] = useState(null)
  const [querySearch, setQuerySearch] = useState('')
  const [filteredLanguages, setFilteredLanguages] = useState([])

  const containerRef = useRef(null)
  const listRef = useRef(null)

  const latestRef = useRef({})
  latestRef.current = {onClose: props.onClose, querySearch, onConfirm}

  useEffect(() => {
    const container = containerRef.current
    const navigateLanguagesList = listRef.current.navigateLanguagesList
    container.addEventListener('keydown', navigateLanguagesList)

    const handleDocumentKeyDown = (event) => {
      const keyCode = event.keyCode
      if (keyCode === 27) {
        latestRef.current.onClose()
      }
      if (event.key === 'Enter' && !latestRef.current.querySearch) {
        latestRef.current.onConfirm()
      }
    }
    document.addEventListener('keydown', handleDocumentKeyDown)

    setFromLanguage(languagesList.filter((i) => i.code === fromLanguageProp)[0])
    setSelectedLanguages(
      selectedLanguagesFromDropdown.map(
        (e) => languagesList.filter((i) => i.code === e)[0],
      ),
    )
    setInitialLanguages(
      selectedLanguagesFromDropdown.map(
        (e) => languagesList.filter((i) => i.code === e)[0],
      ),
    )

    return () => {
      container.removeEventListener('keydown', navigateLanguagesList)
      document.removeEventListener('keydown', handleDocumentKeyDown)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const isFirstRender = useRef(true)
  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    setFilteredLanguages(
      querySearch
        ? languagesList.filter(
            (e) =>
              e.name.toLowerCase().indexOf(querySearch.toLowerCase()) === 0,
          )
        : [],
    )
  }, [querySearch])

  const preventDismiss = (event) => {
    event.stopPropagation()
  }

  function onConfirm() {
    //confirm must have 1 language selected
    props.onConfirm(selectedLanguages)
  }

  const onQueryChange = (querySearch) => {
    setQuerySearch(querySearch)
  }

  const onToggleLanguage = (language) => {
    let newSelectedLanguages = [...selectedLanguages]
    const indexSearch = selectedLanguages
      .map((e) => e.code)
      .indexOf(language.code)
    if (indexSearch > -1) {
      newSelectedLanguages.splice(indexSearch, 1)
    } else {
      newSelectedLanguages.push(language)
    }

    const areAllResultsSelected =
      filteredLanguages.filter(({code}) =>
        newSelectedLanguages.find((selected) => selected.code === code),
      ).length === filteredLanguages.length

    const shouldResetQuery =
      filteredLanguages.length < 2 || areAllResultsSelected

    setSelectedLanguages(newSelectedLanguages)
    if (shouldResetQuery) setQuerySearch('')
    //when add a language, restore query search.
  }

  const onReset = () => {
    setSelectedLanguages([])
    setQuerySearch('')
  }
  const onResetResults = () => {
    setQuerySearch('')
  }

  const setSelectLanguagesFromRecentlyUsed = (list) => {
    setSelectedLanguages(list)
  }

  const recentyUsedLanguages = getRecentyUsedLanguages().reverse()

  return (
    <div
      id="matecat-modal-languages"
      className="matecat-modal"
      ref={containerRef}
      onClick={onClose}
    >
      <div className="matecat-modal-content" onClick={preventDismiss}>
        <div className="matecat-modal-header">
          <span className={'modal-title'}>Target languages</span>
          <Button
            type={BUTTON_TYPE.ICON}
            size={BUTTON_SIZE.ICON_STANDARD}
            mode={BUTTON_MODE.GHOST}
            onClick={onClose}
          >
            <Close size={20} />
          </Button>
        </div>

        <div className="matecat-modal-body">
          <div className="matecat-modal-subheader">
            <div className={'language-from'}>
              <div className={'first-column'}>
                <span className={'label'}>From:</span>
              </div>
              <div>
                <span>{fromLanguage && fromLanguage.name}</span>
              </div>
            </div>
            <div className={'language-to'}>
              <div className={'first-column'}>
                <span className={'label'}>To:</span>
              </div>
              <div className={'language-search'}>
                <LanguageSelectorSearch
                  languagesList={languagesList}
                  selectedLanguages={selectedLanguages}
                  querySearch={querySearch}
                  onDeleteLanguage={onToggleLanguage}
                  onQueryChange={onQueryChange}
                />
              </div>
            </div>
            {recentyUsedLanguages.length > 0 && (
              <div className="recently-used">
                <div className="first-column">
                  <span className="label">Recently used:</span>
                </div>
                <div className="second-column">
                  {recentyUsedLanguages.map((list, index) => (
                    <div
                      className="list-badge"
                      key={index}
                      onClick={() => setSelectLanguagesFromRecentlyUsed(list)}
                    >
                      <LabelWithTooltip>
                        <span className="language-name">
                          {list.map(({name}) => name).join(', ')}
                        </span>
                      </LabelWithTooltip>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {(filteredLanguages.length > 0 ||
              (querySearch && !filteredLanguages.length)) && (
              <div className="button-all-languages">
                <Button
                  type={BUTTON_TYPE.DEFAULT}
                  mode={BUTTON_MODE.OUTLINE}
                  onClick={onResetResults}
                >
                  <FlipBackwardIcon />
                  All languages
                </Button>
              </div>
            )}
          </div>

          <LanguageSelectorList
            ref={listRef}
            languagesList={languagesList}
            selectedLanguages={selectedLanguages}
            querySearch={querySearch}
            onToggleLanguage={onToggleLanguage}
            onResetResults={onResetResults}
          />
        </div>

        <div className="matecat-modal-footer">
          <div className="selected-counter">
            {selectedLanguages && selectedLanguages.length > 0 ? (
              <span className={'uncheck-all'} onClick={onReset}>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="12"
                  height="12"
                  viewBox="0 0 12 12"
                >
                  <g
                    fill="#00AEE4"
                    fillRule="nonzero"
                    stroke="#00AEE4"
                    strokeWidth="1"
                    transform="translate(-5 -5) translate(5 5)"
                  >
                    <rect
                      width="13"
                      height="1"
                      x="-0.5"
                      y="5.5"
                      rx="0.5"
                      transform="rotate(45 6 6)"
                    >
                      {' '}
                    </rect>
                    <rect
                      width="13"
                      height="1"
                      x="-0.5"
                      y="5.5"
                      rx="0.5"
                      transform="rotate(-45 6 6)"
                    >
                      {' '}
                    </rect>
                  </g>
                </svg>
              </span>
            ) : null}
            <span className={'badge'}>
              {selectedLanguages && selectedLanguages.length}
            </span>
            <span className={'label'}>
              {`Language${selectedLanguages?.length === 0 || selectedLanguages?.length > 1 ? 's' : ''}`}{' '}
              selected
            </span>
          </div>
          <div className="">
            <Button type={BUTTON_TYPE.PRIMARY} onClick={onConfirm}>
              Confirm
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}

LanguageSelector.defaultProps = {
  selectedLanguagesFromDropdown: false,
  fromLanguage: true,
  onClose: true,
  onConfirm: true,
}

export default LanguageSelector
