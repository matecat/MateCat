import React, {useEffect, useRef, useState, useCallback} from 'react'
import PropTypes from 'prop-types'
import {EMAIL_PATTERN} from '../../../constants/Constants'
import {TAG_STATUS, Tag} from './Tag'
import {isEqual} from 'lodash'
import styles from './EmailsBadge.module.scss'

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
/**
 * Splits a value into its leading whitespace, its core and its trailing whitespace.
 * Everything is optional, so a value made only of spaces lands entirely in the first group.
 */
const EDGE_WHITESPACE_PATTERN = /^(\s*)([\s\S]*?)(\s*)$/
const WHITESPACE_MARKER = '·'

const isMeaningful = (value) => value.trim() !== ''

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
  validateUserTyping,
  validateChip = EMAIL_PATTERN,
  separators = EMAIL_SEPARATORS,
  trimChips = true,
  revealEdgeWhitespace = false,
  placeholder,
  disabled,
  error,
}) => {
  const areaRef = useRef()
  const inputRef = useRef()
  const highlightedEmailIndexRef = useRef(-1)
  const validateChipRef = useRef()
  validateChipRef.current = validateChip

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
      const normalize = (email) => (trimChips ? email.trim() : email)
      setEmails((prevState) => {
        // an entry left empty once trimmed matches nothing: drop it instead of
        // committing a chip the user cannot see
        const committed = [
          ...emails.map(normalize),
          ...(hasSeparator && lastEmail ? [normalize(lastEmail)] : []),
        ].filter(isMeaningful)
        const updatedState = [...prevState, ...committed]
        return hasSeparator ? removeDuplicates(updatedState) : updatedState
      })
      setInputValue(hasSeparator ? '' : lastEmail)
    },
    [separators, trimChips],
  )

  const handleInputChange = (e) => {
    const newValue = e.target.value

    if (
      (typeof validateUserTyping === 'function' &&
        validateUserTyping(newValue)) ||
      !validateUserTyping
    ) {
      if (newValue !== '') {
        updateEmails(newValue)
      } else {
        setInputValue(newValue)
      }
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
    if (e.key !== 'Enter') e.stopPropagation()

    if (
      (e.key === 'Backspace' && !inputValue && areaRef?.current) ||
      (e.key === 'ArrowLeft' &&
        inputRef?.current.selectionStart === 0 &&
        emails.length > 0)
    ) {
      // select last email (if any)
      updateHighlightedEmail(emails.length - 1)
      inputRef?.current.blur()
      areaRef?.current.focus()
    }
    if (e.key === 'Enter') {
      if (
        separators.some(
          (separator) => separator === SPECIALS_SEPARATORS.EnterKey,
        )
      )
        updateEmails(
          `${inputRef.current.value}${filterSeparators(separators)[0]}`,
        )
      if (inputValue !== '') e.stopPropagation()

      e.preventDefault()
    }
  }
  const handleAreaKeyDown = (e) => {
    switch (e.key) {
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
  /**
   * Leading and trailing spaces collapse when the browser renders them, so a chip whose value
   * keeps them verbatim (JSON and YAML keys) has to spell them out.
   */
  const renderChipLabel = (email) => {
    if (!revealEdgeWhitespace) return email

    const [, leading, core, trailing] = email.match(EDGE_WHITESPACE_PATTERN)

    return (
      <span className={styles['email-badge-tag-label']}>
        {leading && (
          <span className={styles['email-badge-tag-whitespace']}>
            {WHITESPACE_MARKER.repeat(leading.length)}
          </span>
        )}
        {core}
        {trailing && (
          <span className={styles['email-badge-tag-whitespace']}>
            {WHITESPACE_MARKER.repeat(trailing.length)}
          </span>
        )}
      </span>
    )
  }

  const renderChip = (email, index) => {
    const isValid =
      typeof validateChipRef.current === 'object'
        ? validateChipRef.current.test(email)
        : validateChipRef.current(email)
    const isSelected = index === highlightedEmailIndex
    return (
      <div
        key={index}
        className={styles['email-badge-item']}
        onClick={(e) => handleClickOnChip(e, index)}
        title={revealEdgeWhitespace ? email : undefined}
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
          {renderChipLabel(email)}
        </Tag>
      </div>
    )
  }
  return (
    <div className={[styles['email-badge'], disabled && styles['email-badge-disabled']].filter(Boolean).join(' ')}>
      <div
        ref={areaRef}
        className={styles['email-badge-fakeInput']}
        onClick={setFocus}
        onKeyDown={handleAreaKeyDown}
        tabIndex="0"
        data-testid="email-area"
      >
        {emails.length === 0 && inputValue === '' ? (
          <span className={styles['email-badge-placeholder']}>
            {typeof placeholder === 'string'
              ? placeholder
              : 'john@email.com, federico@email.com, sara@email.com'}
          </span>
        ) : (
          emails.map(renderChip)
        )}
        <span className={styles['email-badge-wrapper']}>
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
        <span className={styles['email-badge-error']}>{error.message}</span>
      )}
    </div>
  )
}

EmailsBadge.propTypes = {
  name: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
  value: PropTypes.arrayOf(PropTypes.string),
  validateUserTyping: PropTypes.func,
  validateChip: PropTypes.oneOfType([PropTypes.object, PropTypes.func]),
  trimChips: PropTypes.bool,
  revealEdgeWhitespace: PropTypes.bool,
  placeholder: PropTypes.string,
  disabled: PropTypes.bool,
  error: PropTypes.object,
}
