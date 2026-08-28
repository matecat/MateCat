import React, {useEffect, useRef, useState} from 'react'
import TagsInput from 'react-tagsinput'

const LanguageSelectorSearch = ({
  onQueryChange,
  querySearch,
  selectedLanguages,
  onDeleteLanguage,
}) => {
  const [highlightDelete, setHighlightDelete] = useState(false)
  const tagsInputRef = useRef(null)

  useEffect(() => {
    const handleMouseDown = () => {
      setHighlightDelete(false)
    }

    document.addEventListener('mousedown', handleMouseDown)

    tagsInputRef.current.focus()

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

  const handleChange = () => {
    if (highlightDelete) {
      onDeleteLanguage(selectedLanguages[selectedLanguages.length - 1])
      setHighlightDelete(false)
    } else {
      setHighlightDelete(true)
    }
  }

  const removeLanguageWithIconTag = (tagIndex) => {
    onDeleteLanguage(selectedLanguages[tagIndex])
  }

  const defaultRenderTag = (props) => {
    let {
      tag,
      key,
      disabled,
      classNameRemove,
      getTagDisplayValue,
      onRemove,
      ...other
    } = props
    const highlight =
      highlightDelete && key + 1 === selectedLanguages.length
        ? 'highlightDelete'
        : ''
    return (
      <span key={key} {...other} className={`tag ${highlight}`}>
        {getTagDisplayValue(tag)}
        {!disabled && (
          <a
            className={classNameRemove}
            onClick={() => removeLanguageWithIconTag(key)}
          >
            {' '}
            &times;
          </a>
        )}
      </span>
    )
  }

  return (
    <TagsInput
      inputValue={querySearch}
      addKeys={[]}
      inputProps={{placeholder: 'Search...'}}
      onChangeInput={onQueryChange}
      renderTag={defaultRenderTag}
      value={selectedLanguages ? selectedLanguages.map((e) => e.name) : []}
      onChange={handleChange}
      autofocus={true}
      ref={tagsInputRef}
    />
  )
}

LanguageSelectorSearch.defaultProps = {
  selectedLanguages: false,
  languagesList: true,
  querySearch: true,
  onDeleteLanguage: true,
  onQueryChange: true,
}

export default LanguageSelectorSearch
