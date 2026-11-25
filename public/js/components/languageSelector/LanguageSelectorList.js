import React, {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react'
import TEXT_UTILS from '../../utils/textUtils'

const LanguageSelectorList = forwardRef((props, ref) => {
  const {
    querySearch,
    selectedLanguages,
    languagesList,
    onToggleLanguage,
    onResetResults,
  } = props
  const [position, setPosition] = useState(0)

  const currentSelectedElementRef = useRef(null)
  const wrapperScrollRef = useRef(null)

  const onClickElement = (language) => () => {
    onToggleLanguage(language)
  }

  const getFilteredLanguages = () => {
    // const querySplitted = TEXT_UTILS.escapeRegExp(querySearch)
    //   .split(' ')
    //   .join('|')
    // const regex = new RegExp(
    //   querySplitted.substr(querySplitted.lastIndexOf('|') + 1) === ''
    //     ? querySplitted.substring(0, querySplitted.lastIndexOf('|'))
    //     : querySplitted,
    //   'i',
    // )
    const wordsFromQuery = TEXT_UTILS.escapeRegExp(querySearch)
      .split(' ')
      .filter((word) => word)

    const langs =
      languagesList && languagesList.length
        ? languagesList.filter(({name, nameWithoutDiacriticalMarks, id}) =>
            wordsFromQuery.every((word) => {
              const regex = new RegExp(word, 'i')
              return (
                regex.test(name) ||
                regex.test(id) ||
                regex.test(nameWithoutDiacriticalMarks)
              )
            }),
          )
        : []
    const sortInputFirst = (input, data) => {
      let first = []
      let others = []
      for (let i = 0; i < data.length; i++) {
        if (
          data[i].name.toLowerCase().indexOf(input.toLowerCase()) === 0 ||
          data[i].nameWithoutDiacriticalMarks
            ?.toLowerCase()
            .indexOf(input.toLowerCase()) === 0
        ) {
          first.push(data[i])
        } else {
          others.push(data[i])
        }
      }
      first.sort()
      others.sort()
      return first.concat(others)
    }
    //Sort languages
    return sortInputFirst(querySearch, langs)
  }

  const getLanguagesInColumns = () => {
    const languagesPerColumn = Math.ceil(languagesList.length / 4)
    const filteredLanguagesInColumns = chunk(
      getFilteredLanguages(),
      languagesPerColumn,
    )

    if (filteredLanguagesInColumns.length >= 4) {
      return filteredLanguagesInColumns
    } else {
      return filteredLanguagesInColumns.concat(
        buildRangeArray(4 - filteredLanguagesInColumns.length).map(function () {
          return []
        }),
      )
    }
  }

  const scrollIfTagNavigationIsOverflow = () => {
    if (currentSelectedElementRef.current) {
      const relativePositionOfTag =
        currentSelectedElementRef.current.offsetTop -
        wrapperScrollRef.current.offsetTop +
        currentSelectedElementRef.current.clientHeight
      const bottomPositionOfWrapper =
        wrapperScrollRef.current.clientHeight +
        wrapperScrollRef.current.scrollTop
      if (relativePositionOfTag > bottomPositionOfWrapper) {
        //check if element is overflowBottom of parent
        wrapperScrollRef.current.scrollTop =
          relativePositionOfTag + 10 - wrapperScrollRef.current.clientHeight
      } else if (
        wrapperScrollRef.current.scrollTop >
        relativePositionOfTag - currentSelectedElementRef.current.clientHeight
      ) {
        //check if element is overflowTop of parent
        wrapperScrollRef.current.scrollTop =
          relativePositionOfTag -
          currentSelectedElementRef.current.clientHeight -
          10
      }
    }
  }

  const isFirstRender = useRef(true)
  const prevQuerySearchRef = useRef(querySearch)

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      prevQuerySearchRef.current = querySearch
      return
    }
    scrollIfTagNavigationIsOverflow()
    if (prevQuerySearchRef.current !== querySearch) {
      setPosition(0)
    }
    prevQuerySearchRef.current = querySearch
  })

  const latestRef = useRef(null)
  latestRef.current = {
    position,
    querySearch,
    onToggleLanguage,
    onResetResults,
    getFilteredLanguages,
  }

  const navigateLanguagesList = useCallback((event) => {
    const {
      position,
      querySearch,
      onToggleLanguage,
      onResetResults,
      getFilteredLanguages,
    } = latestRef.current
    const keyCode = event.keyCode
    if (keyCode === 38 || keyCode === 40) {
      event.preventDefault()
    }

    if (querySearch) {
      const filteredLanguages = getFilteredLanguages()
      if (keyCode === 38) {
        // up key
        if (position !== 0) {
          setPosition(position - 1)
        }
      } else if (keyCode === 40) {
        // down key
        if (position + 1 < filteredLanguages.length) {
          setPosition(position + 1)
        }
      } else if (keyCode === 13 && filteredLanguages.length) {
        //enter with 1 language filtered
        onToggleLanguage(filteredLanguages[position])
        onResetResults()
        event.stopPropagation()
      }
    }
  }, [])

  useImperativeHandle(ref, () => ({navigateLanguagesList}), [
    navigateLanguagesList,
  ])

  let counterItem = -1
  const languages = getLanguagesInColumns()
  currentSelectedElementRef.current = null

  return (
    <div className="languages-columns" ref={wrapperScrollRef}>
      {languages.map((languagesColumn, key) => {
        return (
          <ul key={key} className={'dropdown__list'}>
            {languagesColumn.map((e) => {
              counterItem++
              let elementClass = ''
              const isHover = querySearch && counterItem === position
              if (
                selectedLanguages &&
                selectedLanguages.map((e) => e.code).indexOf(e.code) > -1
              ) {
                elementClass = `selected ${isHover ? 'hover' : ''}`
              } else if (isHover) {
                elementClass = 'hover'
              }
              return (
                <li
                  key={`${counterItem}`}
                  ref={(el) => {
                    if (isHover) {
                      currentSelectedElementRef.current = el
                    }
                  }}
                  className={`lang-item ${elementClass}`}
                  onClick={onClickElement(e)}
                >
                  <div className="language-dropdown-item-container">
                    <div className="code-badge">
                      <span className={`code-badge-${elementClass}`}>
                        {e.code}
                      </span>
                    </div>
                    <span>{e.name}</span>
                  </div>
                  <span className={'check'}>
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="12"
                      viewBox="0 0 16 12"
                    >
                      <path
                        fill="#FFF"
                        fillRule="evenodd"
                        stroke="none"
                        strokeWidth="1"
                        d="M15.735.265a.798.798 0 00-1.13 0L5.04 9.831 1.363 6.154a.798.798 0 00-1.13 1.13l4.242 4.24a.799.799 0 001.13 0l10.13-10.13a.798.798 0 000-1.129z"
                        transform="translate(-266 -10) translate(266 8) translate(0 2)"
                      >
                        {' '}
                      </path>
                    </svg>
                  </span>
                </li>
              )
            })}
          </ul>
        )
      })}
    </div>
  )
})

LanguageSelectorList.displayName = 'LanguageSelectorList'

LanguageSelectorList.defaultProps = {
  selectedLanguages: false,
  languagesList: true,
  onToggleLanguage: true,
  querySearch: true,
  onResetResults: () => {},
}

export default LanguageSelectorList

export const chunk = (array, size) => {
  const firstChunk = array.slice(0, size)

  if (!firstChunk.length) return array
  else return [firstChunk].concat(chunk(array.slice(size, array.length), size))
}

export const buildRangeArray = (items) =>
  Array.apply(null, {length: items}).map(Number.call, Number)
