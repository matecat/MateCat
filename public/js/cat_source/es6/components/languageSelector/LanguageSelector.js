import React from 'react'

import LanguageSelectorList from './LanguageSelectorList'
import LanguageSelectorSearch from './LanguageSelectorSearch'

class LanguageSelector extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      selectedLanguages: null,
      initialLanguages: null,
      fromLanguage: null,
      querySearch: '',
      filteredLanguages: [],
    }

    this.listRef = React.createRef()
  }

  componentDidMount() {
    const {selectedLanguagesFromDropdown, languagesList, fromLanguage} =
      this.props
    this.container.addEventListener(
      'keydown',
      this.listRef.current.navigateLanguagesList,
    )
    document.addEventListener('keydown', this.keyHandler)

    this.setState({
      fromLanguage: languagesList.filter((i) => i.code === fromLanguage)[0],
      selectedLanguages: selectedLanguagesFromDropdown.map(
        (e) => languagesList.filter((i) => i.code === e)[0],
      ),
      initialLanguages: selectedLanguagesFromDropdown.map(
        (e) => languagesList.filter((i) => i.code === e)[0],
      ),
    })
  }

  componentWillUnmount() {
    this.container.removeEventListener(
      'keydown',
      this.listRef.current.navigateLanguagesList,
    )
    document.removeEventListener('keydown', this.keyHandler)
  }

  componentDidUpdate(prevProps, prevState) {
    if (prevState.querySearch !== this.state.querySearch) {
      const filteredLanguages = this.state.querySearch
        ? this.props.languagesList.filter(
            (e) =>
              e.name
                .toLowerCase()
                .indexOf(this.state.querySearch.toLowerCase()) === 0,
          )
        : []
      this.setState({filteredLanguages})
    }
  }

  render() {
    const {
      onQueryChange,
      onToggleLanguage,
      onConfirm,
      preventDismiss,
      onRestore,
      onReset,
      onResetResults,
    } = this
    const {languagesList, onClose} = this.props
    const {selectedLanguages, querySearch, fromLanguage, filteredLanguages} =
      this.state

    return (
      <div
        id="matecat-modal-languages"
        className="matecat-modal"
        ref={(el) => {
          this.container = el
        }}
        onClick={onClose}
      >
        <div className="matecat-modal-content" onClick={preventDismiss}>
          <div className="matecat-modal-header">
            <span className={'modal-title'}>Multiple Languages</span>
            <span className="close-matecat-modal x-popup" onClick={onClose} />
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
              {(filteredLanguages.length > 0 ||
                (querySearch && !filteredLanguages.length)) && (
                <div className="button-all-languages">
                  <button
                    className={'modal-btn secondary gray'}
                    onClick={onResetResults}
                  >
                    <span className="icon-undo2"></span>
                    All languages
                  </button>
                </div>
              )}
            </div>

            <LanguageSelectorList
              ref={this.listRef}
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
              <span className={'label'}>language selected</span>
            </div>
            <div className="">
              <button
                className={'modal-btn secondary gray'}
                onClick={onRestore}
              >
                Restore all
              </button>
              <button className={'modal-btn primary blue'} onClick={onConfirm}>
                Confirm
              </button>
            </div>
          </div>
        </div>
      </div>
    )
  }

  preventDismiss = (event) => {
    event.stopPropagation()
  }
  onConfirm = () => {
    //confirm must have 1 language selected
    const {selectedLanguages} = this.state
    this.props.onConfirm(selectedLanguages)
  }

  onQueryChange = (querySearch) => {
    this.setState({querySearch})
  }

  onToggleLanguage = (language) => {
    const {selectedLanguages} = this.state
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
      this.state.filteredLanguages.filter(({code}) =>
        newSelectedLanguages.find((selected) => selected.code === code),
      ).length === this.state.filteredLanguages.length

    const shouldResetQuery =
      this.state.filteredLanguages.length < 2 || areAllResultsSelected

    this.setState({
      selectedLanguages: newSelectedLanguages,
      ...(shouldResetQuery && {querySearch: ''}),
    })
    //when add a language, restore query search.
  }

  onRestore = () => {
    const {initialLanguages} = this.state
    this.setState({
      selectedLanguages: initialLanguages,
      querySearch: '',
    })
  }
  onReset = () => {
    this.setState({
      selectedLanguages: [],
      querySearch: '',
    })
  }
  onResetResults = () => {
    this.setState({querySearch: ''})
  }

  keyHandler = (event) => {
    const {onClose} = this.props
    const keyCode = event.keyCode

    if (keyCode === 27) {
      onClose()
    }

    if (event.key === 'Enter' && !this.state.querySearch) {
      this.onConfirm()
    }

    //27
  }
}

LanguageSelector.defaultProps = {
  selectedLanguagesFromDropdown: false,
  fromLanguage: true,
  languagesList: true,
  onClose: true,
  onConfirm: true,
}

export default LanguageSelector
