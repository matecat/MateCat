import React, {useState, useRef, useEffect} from 'react'
import PropTypes from 'prop-types'

import {Dropdown} from './../common/Dropdown'

const mergeClassNames = (...args) => {
  return (
    Array.prototype.slice
      // eslint-disable-next-line no-undef
      .call(args)
      .reduce(
        (classList, arg) =>
          typeof arg === 'string' || Array.isArray(arg)
            ? classList.concat(arg)
            : classList,
        [],
      )
      .filter(Boolean)
      .join(' ')
  )
}
export const Select = ({
  className,
  label,
  name,
  placeholder,
  options,
  activeOption,
  activeOptions,
  isValid = false,
  showValidation = false,
  showSearchBar = false,
  multipleSelect = 'off',
  isDisabled = false,
  offsetParent,
  onSelect = () => {},
  onToggleOption = () => {},
  optionsSelectedCopySingular = () => {},
  optionsSelectedCopyPlural = () => {},
  resetSelectedOptions = () => {},
}) => {
  const listRef = useRef()
  const wrapperRef = useRef()

  const [value, setValue] = useState(activeOption ? activeOption.id : '')
  const [isDropdownVisible, setDropdownVisibility] = useState(false)
  const [isDropdownReversed, setDropdownReversed] = useState(false)

  useEffect(() => {
    if (activeOption && (!value || activeOption.id !== value)) {
      setValue(activeOption.id)
    } else if (!activeOption && value) {
      setValue('')
    }
  }, [activeOption, value])

  const showDropdown = () => {
    if (!wrapperRef.current) return
    const {ownerDocument} = wrapperRef.current
    ownerDocument.addEventListener('mousedown', checkIfShouldHideDropdown)
    ownerDocument.addEventListener('keydown', checkIfShouldHideDropdown)
    setDropdownVisibility(true)
  }
  const hideDropdown = () => {
    if (!wrapperRef.current) return
    const {ownerDocument} = wrapperRef.current
    ownerDocument.removeEventListener('mousedown', checkIfShouldHideDropdown)
    ownerDocument.removeEventListener('keydown', checkIfShouldHideDropdown)

    setDropdownVisibility(false)
    setDropdownReversed(false)
  }
  const toggleDropdown = () => {
    if (!isDisabled) {
      if (isDropdownVisible) {
        hideDropdown()
      } else {
        showDropdown()
      }
    }
  }

  useEffect(() => {
    if (isDropdownVisible && multipleSelect !== 'modal' && wrapperRef.current) {
      const listNode = listRef.current
      const wrapperNode = wrapperRef.current
      const listTopPosition =
        listNode.getBoundingClientRect().top -
        wrapperNode.getBoundingClientRect().top
      const wrapperTopPosition = wrapperNode.offsetTop
      const offsetParentElement = offsetParent
        ? offsetParent
        : wrapperNode.offsetParent
      //console.log('Select offsetParent:', offsetParentElement);
      const parentHeight = offsetParentElement.getBoundingClientRect().height
      const parentScrollTop =
        offsetParentElement === document.body
          ? document.documentElement.scrollTop
          : offsetParentElement.scrollTop
      let availableHeight =
        parentHeight +
        parentScrollTop -
        wrapperTopPosition -
        listTopPosition -
        16 // 16 = margins
      if (availableHeight > 128) {
        listNode.style.maxHeight = `${availableHeight}px`
      } else {
        setDropdownReversed(true)
        availableHeight =
          wrapperTopPosition -
          parentScrollTop +
          (label ? 32 : 0) -
          32 -
          (showSearchBar ? 48 : 0) // 32 = margins; 32 = label height; 48 = searchBar height
        listNode.style.maxHeight = `${
          availableHeight > 128 ? availableHeight : 128
        }px`
      }
    }
  }, [isDropdownVisible, multipleSelect, label, showSearchBar, offsetParent])

  const checkIfShouldHideDropdown = (event) => {
    const isTabPressed = event.keyCode === 9
    const isEscPressed = event.keyCode === 27

    if (
      (multipleSelect === 'modal' && (isTabPressed || isEscPressed)) ||
      (multipleSelect !== 'modal' &&
        (isTabPressed ||
          isEscPressed ||
          (wrapperRef.current && !wrapperRef.current.contains(event.target))))
    ) {
      hideDropdown()
    }
  }

  const handleFocus = () => {
    showDropdown()
  }

  const handleSelect = (option) => {
    if (option && option.id) {
      setValue(option.id)
      onSelect(option)
    } else if (activeOptions && activeOptions.length) {
      onSelect(activeOptions)
    }

    hideDropdown()
  }

  const getInputClassName = () => {
    const defaultClassName = 'select'
    const inputPlaceholderClassName =
      !activeOption &&
      (!activeOptions || (activeOptions && activeOptions.length === 0))
        ? 'select--is-placeholder'
        : ''
    const inputIsFocusedClassName = isDropdownVisible
      ? 'select--is-focused'
      : ''
    const inputIsInvalidClassName =
      showValidation && !isValid ? 'select--is-invalid' : ''
    const inputIsDisabledClassName = isDisabled ? 'select--is-disabled' : ''
    const inputMultipleCLassName =
      multipleSelect !== 'off' ? 'select--is-multiple' : ''

    return mergeClassNames(
      defaultClassName,
      inputPlaceholderClassName,
      inputIsFocusedClassName,
      inputIsInvalidClassName,
      inputIsDisabledClassName,
      inputMultipleCLassName,
    )
  }

  const inputClassName = getInputClassName()
  const inputValue =
    (activeOptions && activeOptions.map((v) => v.id).join(',')) || value

  const renderSelection = () => {
    return activeOption ? activeOption.name : placeholder
  }

  return (
    <div
      className={`select-with-label__wrapper ${className ? className : ''}`}
      ref={wrapperRef}
    >
      <input type="hidden" name={`${name}-hidden`} value={inputValue} />{' '}
      {label && (
        <label htmlFor={name} onClick={toggleDropdown}>
          {label}
        </label>
      )}
      <div
        className="select-with-icon__wrapper"
        aria-label={multipleSelect === 'off' ? renderSelection() : null}
      >
        <span className={inputClassName} onClick={toggleDropdown}>
          {renderSelection()}
        </span>
        <input
          name={name}
          readOnly={true}
          type="text"
          className="input--invisible"
          placeholder={placeholder}
          onFocus={handleFocus}
          value={value}
        />
        <ChevronDown />
      </div>
      {isDropdownVisible && (
        <div
          className={`select__dropdown-wrapper ${
            multipleSelect === 'modal'
              ? 'select__dropdown-wrapper--is-multiselect'
              : ''
          } ${isDropdownReversed ? 'select__dropdown--is-reversed' : ''}`}
        >
          <Dropdown
            className="select__dropdown"
            showSearchBar={showSearchBar}
            listRef={listRef}
            activeOption={activeOption}
            activeOptions={activeOptions}
            options={options}
            onSelect={handleSelect}
            onToggleOption={onToggleOption}
            multipleSelect={multipleSelect}
            optionsSelectedCopySingular={optionsSelectedCopySingular}
            optionsSelectedCopyPlural={optionsSelectedCopyPlural}
            resetSelectedOptions={resetSelectedOptions}
            onClose={hideDropdown}
          />
        </div>
      )}
    </div>
  )
}

Select.propTypes = {
  className: PropTypes.string,
  label: PropTypes.node,
  name: PropTypes.string,
  placeholder: PropTypes.string,
  options: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.string,
    }),
  ),
  activeOption: PropTypes.shape({
    id: PropTypes.string,
    name: PropTypes.string,
  }),
  activeOptions: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.string,
    }),
  ),
  isValid: PropTypes.bool,
  showValidation: PropTypes.bool,
  showSearchBar: PropTypes.bool,
  multipleSelect: PropTypes.oneOf(['off', 'dropdown']),
  isDisabled: PropTypes.bool,
  offsetParent: PropTypes.object,
  onSelect: PropTypes.func,
  onToggleOption: PropTypes.func,
  optionsSelectedCopySingular: PropTypes.func,
  optionsSelectedCopyPlural: PropTypes.func,
  resetSelectedOptions: PropTypes.func,
}

const ChevronDown = () => {
  return (
    <svg width="14" height="8" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="m1 1 6 6 6-6"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
