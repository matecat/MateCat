import React, {useEffect, useRef, useState} from 'react'

const LanguageSelectorSearch = ({
  onQueryChange,
  querySearch,
  selectedLanguages,
  onDeleteLanguage,
}) => {
  const [highlightDelete, setHighlightDelete] = useState(false)
  const inputRef = useRef(null)

  useEffect(() => {
    const handleMouseDown = () => {
      setHighlightDelete(false)
    }

    document.addEventListener('mousedown', handleMouseDown)

    inputRef.current.focus()

    return () => {
      document.removeEventListener('mousedown', handleMouseDown)
    }
  }, [])

  const isFirstRender = useRef(true)
  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
    setHighlightDelete(false)
  }, [querySearch])

  const languages = selectedLanguages ?? []

  // Only Backspace on an empty search is handled here; every other key
  // (Enter, Tab, arrows) must keep bubbling, because the modal confirms on
  // Enter and moves through the language list on the arrow keys.
  const handleKeyDown = (event) => {
    if (
      event.key !== 'Backspace' ||
      event.target.value !== '' ||
      !languages.length
    ) {
      return
    }
    event.preventDefault()
    if (highlightDelete) {
      onDeleteLanguage(languages[languages.length - 1])
      setHighlightDelete(false)
    } else {
      setHighlightDelete(true)
    }
  }

  // Enter on the remove button must only remove the language: the modal's
  // own Enter listeners (confirm, pick the highlighted result) sit higher up
  // and would otherwise run too. Stopping it while capturing keeps the
  // button's native click, which is what removes the language.
  const stopEnterPropagation = (event) => {
    if (event.key === 'Enter') event.stopPropagation()
  }

  return (
    <div
      className="language-search-tags"
      onClick={() => inputRef.current.focus()}
    >
      <span>
        {languages.map((language, index) => (
          <span
            key={language.code}
            className={`language-search-tag${
              highlightDelete && index === languages.length - 1
                ? ' highlightDelete'
                : ''
            }`}
          >
            {language.name}{' '}
            <button
              type="button"
              className="language-search-tag-remove"
              aria-label={`Remove ${language.name}`}
              onKeyDownCapture={stopEnterPropagation}
              onClick={() => onDeleteLanguage(language)}
            >
              &times;
            </button>
          </span>
        ))}
        <input
          ref={inputRef}
          type="text"
          placeholder="Search..."
          value={querySearch}
          onChange={(event) => onQueryChange(event.target.value)}
          onKeyDown={handleKeyDown}
        />
      </span>
    </div>
  )
}

export default LanguageSelectorSearch
