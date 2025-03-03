import React, {useEffect, useRef, useState, useCallback} from 'react'
import PropTypes from 'prop-types'
import {EMAIL_PATTERN} from '../../../constants/Constants'
import {TAG_STATUS, Tag} from './Tag'
import {isEqual} from 'lodash'

const EMAIL_SEPARATORS = [',', ';', ' ']
export const SPECIALS_SEPARATORS = {
  EnterKey: 'EnterKey',
}

/**
 * Splits emails from string by separators ex. ',' or ';'
 *
 * @param {string} value
 * @returns {Array}
 */
const splitEmailsBySeparators = (value, separators) => {
  const cleanValue = value.replace(/[\n\r]+/g, ' ')
  return separators.reduce(
    (acc, cur) =>
      acc
        .map((item) => item.split(cur))
        .flat()
        .filter((item) => item),
    [cleanValue],
  )
}
const stringIncludesSeparator = (text, separators) => {
  const lastChar = text.slice(-1)
  return separators.some((separator) => lastChar === separator)
}
const filterSeparators = (separators) =>
  separators.filter((separator) =>
    Object.values(SPECIALS_SEPARATORS).every((v) => v !== separator),
  )

export const EmailsBadge = ({
  name,
  onChange,
  value = [],
  validatePattern = EMAIL_PATTERN,
  separators = EMAIL_SEPARATORS,
  placeholder,
  disabled,
  error,
}) => {
  const areaRef = useRef()
  const inputRef = useRef()
  const highlightedEmailIndexRef = useRef(-1)
  const validatePatternRef = useRef()
  validatePatternRef.current = validatePattern

  const [inputValue, setInputValue] = useState('')
  const [emails, setEmails] = useState(() => value)
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
  const updateEmails = useCallback(
    (newValue) => {
      const filteredSeparators = filterSeparators(separators)
      const hasSeparator = stringIncludesSeparator(newValue, filteredSeparators)
      const emails = splitEmailsBySeparators(newValue, filteredSeparators)
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
    },
    [separators],
  )

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
      if (
        separators.some(
          (separator) => separator === SPECIALS_SEPARATORS.EnterKey,
        )
      )
        updateEmails(
          `${inputRef.current.value}${filterSeparators(separators)[0]}`,
        )
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
    setEmails((prevState) => (!isEqual(value, prevState) ? value : prevState))
  }, [value])

  // click outside set value
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (
        areaRef.current &&
        !areaRef.current.contains(e.target) &&
        inputRef.current.value
      ) {
        updateEmails(
          `${inputRef.current.value}${filterSeparators(separators)[0]}`,
        )
      }
    }
    document.addEventListener('mousedown', handleClickOutside)

    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [updateEmails, separators])

  useEffect(() => {
    // const isFirstEntryWritingValid =
    //   !emails.length && validatePatternRef.current.test(inputRef.current.value)
    onChange(emails)
  }, [emails, onChange])

  // RENDER
  const renderChip = (email, index) => {
    const isValid = validatePatternRef.current.test(email)
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
    <div className={`email-badge${disabled ? ' email-badge-disabled' : ''}`}>
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
            {typeof placeholder === 'string'
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
            disabled={disabled}
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
  validatePattern: PropTypes.object,
  placeholder: PropTypes.string,
  disabled: PropTypes.bool,
  error: PropTypes.object,
}
