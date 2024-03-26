import React, {useEffect, useRef, useState, useCallback} from 'react'
import PropTypes from 'prop-types'
import {EMAIL_PATTERN} from '../../../constants/Constants'
import {TAG_STATUS, Tag} from './Tag'

const EMAIL_SEPARATORS = [',', ';', ' ']

/**
 * Splits emails from string by separators ex. ',' or ';'
 *
 * @param {string} value
 * @returns {Array}
 */
const splitEmailsBySeparators = (value) => {
  const cleanValue = value.replace(/[\n\r]+/g, ' ')
  return EMAIL_SEPARATORS.reduce(
    (acc, cur) =>
      acc
        .map((item) => item.split(cur))
        .flat()
        .filter((item) => item),
    [cleanValue],
  )
}
const stringIncludesSeparator = (text) => {
  const lastChar = text.slice(-1)
  return EMAIL_SEPARATORS.some((separator) => lastChar === separator)
}

export const EmailsBadge = ({
  name,
  onChange,
  value = [],
  placeholder,
  error,
}) => {
  const areaRef = useRef()
  const inputRef = useRef()
  const highlightedEmailIndexRef = useRef(-1)

  const [inputValue, setInputValue] = useState('')
  const [emails, setEmails] = useState(() => [...value])
  const [highlightedEmailIndex, setHighlightedEmailIndex] = useState(-1)

  // FUNCTIONS
  const updateHighlightedEmail = (newHighlightedEmailIndex) => {
    highlightedEmailIndexRef.current = newHighlightedEmailIndex
    setHighlightedEmailIndex(newHighlightedEmailIndex)
  }
  const removeDuplicates = (emails) => {
    return emails.reduce((result, email) => {
      return result.includes(email) ? result : [...result, email]
    }, [])
  }
  const removeEmail = (index) => {
    setEmails((prevState) =>
      removeDuplicates(prevState.filter((item, i) => i !== index)),
    )
    updateHighlightedEmail(-1)
  }
  const updateEmails = useCallback((newValue) => {
    const hasSeparator = stringIncludesSeparator(newValue)
    const emails = splitEmailsBySeparators(newValue)
    const lastEmail = emails.pop()
    setEmails((prevState) => {
      const updatedState = [
        ...prevState,
        ...emails,
        ...(hasSeparator && lastEmail ? [lastEmail] : []),
      ]
      return hasSeparator ? removeDuplicates(updatedState) : updatedState
    })
    setInputValue(hasSeparator ? '' : lastEmail)
  }, [])

  const handleInputChange = (e) => {
    const newValue = e.target.value
    if (newValue !== '') {
      updateEmails(newValue)
    } else {
      setInputValue(newValue)
    }
  }
  const handlePaste = (e) => {
    e.preventDefault()
    const pastedText = (e.clipboardData || window.clipboardData).getData('text')
    updateEmails(`${pastedText} `)
  }
  const handleClickOnChip = (e, index) => {
    e.stopPropagation()

    updateHighlightedEmail(index)
    inputRef?.current.blur()
  }
  const handleInputKeyDown = (e) => {
    e.stopPropagation()
    if (
      (e.code === 'Backspace' && !inputValue && areaRef?.current) ||
      (e.code === 'ArrowLeft' &&
        inputRef?.current.selectionStart === 0 &&
        emails.length > 0)
    ) {
      // select last email (if any)
      updateHighlightedEmail(emails.length - 1)
      inputRef?.current.blur()
      areaRef?.current.focus()
    }
    if (e.code === 'Enter') {
      e.preventDefault()
    }
  }
  const handleAreaKeyDown = (e) => {
    switch (e.code) {
      case 'Backspace':
        if (highlightedEmailIndexRef.current >= 0) {
          removeEmail(highlightedEmailIndexRef.current)
          inputRef?.current.focus()
        }
        break
      case 'ArrowLeft':
        if (highlightedEmailIndexRef.current >= 1) {
          updateHighlightedEmail(highlightedEmailIndexRef.current - 1)
        }
        break
      case 'ArrowRight':
        if (highlightedEmailIndexRef.current === emails.length - 1) {
          inputRef?.current.focus()
          updateHighlightedEmail(-1)
        } else if (highlightedEmailIndexRef.current >= 0) {
          updateHighlightedEmail(highlightedEmailIndexRef.current + 1)
        }
        break
    }
  }

  const setFocus = () => {
    inputRef?.current.focus()
    updateHighlightedEmail(-1)
  }

  // EFFECTS
  useEffect(() => {
    if (!value.length) setEmails([])
  }, [value.length])

  // click outside set value
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (
        areaRef.current &&
        !areaRef.current.contains(e.target) &&
        inputRef.current.value
      ) {
        updateEmails(`${inputRef.current.value}${EMAIL_SEPARATORS[0]}`)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)

    inputRef?.current.focus()

    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [updateEmails])

  useEffect(() => {
    const isFirstEntryWritingValid =
      !emails.length && EMAIL_PATTERN.test(inputRef.current.value)
    onChange(isFirstEntryWritingValid ? [inputRef.current.value] : emails)
  }, [emails, onChange])

  // RENDER
  const renderChip = (email, index) => {
    const isValid = EMAIL_PATTERN.test(email)
    const isSelected = index === highlightedEmailIndex
    return (
      <div
        key={index}
        className="email-badge-item"
        onClick={(e) => handleClickOnChip(e, index)}
      >
        <Tag
          status={
            isSelected
              ? TAG_STATUS.SELECTED
              : !isValid
                ? TAG_STATUS.INVALID
                : undefined
          }
          onRemove={() => removeEmail(index)}
        >
          {email}
        </Tag>
      </div>
    )
  }
  return (
    <div className="email-badge">
      <div
        ref={areaRef}
        className="email-badge-fakeInput"
        onClick={setFocus}
        onKeyDown={handleAreaKeyDown}
        tabIndex="0"
        data-testid="email-area"
      >
        {emails.length === 0 && inputValue === '' ? (
          <span className="email-badge-placeholder">
            {placeholder
              ? placeholder
              : 'john@email.com, federico@email.com, sara@email.com'}
          </span>
        ) : (
          emails.map(renderChip)
        )}
        <span className="email-badge-wrapper">
          <input
            ref={inputRef}
            name={name}
            data-testid="email-input"
            value={inputValue}
            autoComplete="off"
            onPaste={handlePaste}
            onChange={handleInputChange}
            onKeyDown={handleInputKeyDown}
          />
          <span>{inputValue}</span>
        </span>
      </div>
      {error && error.message && (
        <span className="email-badge-error">{error.message}</span>
      )}
    </div>
  )
}

EmailsBadge.propTypes = {
  name: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
  value: PropTypes.arrayOf(PropTypes.string),
  placeholder: PropTypes.string,
  error: PropTypes.object,
}
