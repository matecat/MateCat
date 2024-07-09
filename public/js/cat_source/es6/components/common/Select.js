import React, {useState, useRef, useEffect, useCallback} from 'react'
import PropTypes from 'prop-types'

import {Dropdown} from './Dropdown'
import TEXT_UTILS from '../../utils/textUtils'
import ChevronDown from '../../../../../img/icons/ChevronDown'

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
  id,
  name,
  placeholder,
  options,
  activeOption,
  activeOptions,
  isValid = false,
  showValidation = false,
  showSearchBar = false,
  searchPlaceholder,
  multipleSelect = 'off',
  tooltipPosition,
  isDisabled = false,
  offsetParent,
  onSelect = () => {},
  onToggleOption = () => {},
  optionsSelectedCopySingular = () => {},
  optionsSelectedCopyPlural = () => {},
  resetSelectedOptions = () => {},
  checkSpaceToReverse = true,
  maxHeightDroplist = 128,
  children,
}) => {
  const dropDownRef = useRef()
  const wrapperRef = useRef()
  const selectedItemRef = useRef()

  const [value, setValue] = useState(activeOption ? activeOption.id : '')
  const [isDropdownVisible, setDropdownVisibility] = useState(false)
  const [isDropdownReversed, setDropdownReversed] = useState(false)
  const [selectedLabel, setSelectedLabel] = useState('')

  const renderSelection = useCallback(() => {
    if (multipleSelect !== 'off' && activeOptions && activeOptions.length > 0) {
      const array = activeOptions.map((option, index) => {
        return option.name
      })
      return array.join(', ')
    }
    return activeOption ? activeOption.name : placeholder
  }, [activeOption, activeOptions, multipleSelect, placeholder])

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
    if (
      isDropdownVisible &&
      multipleSelect !== 'modal' &&
      wrapperRef.current &&
      dropDownRef.current
    ) {
      const {getListRef, setListMaxHeight} = dropDownRef.current
      const listNode = getListRef()
      const wrapperNode = wrapperRef.current
      const listTopPosition =
        listNode.getBoundingClientRect().top -
        wrapperNode.getBoundingClientRect().top
      const wrapperTopPosition = wrapperNode.offsetTop
      const offsetParentElement = offsetParent
        ? offsetParent
        : wrapperNode.offsetParent ?? document.body
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
      if (availableHeight > maxHeightDroplist) {
        setListMaxHeight(availableHeight)
      } else {
        if (checkSpaceToReverse) {
          setDropdownReversed(true)
        }
        availableHeight =
          wrapperTopPosition -
          parentScrollTop +
          (label ? 32 : 0) -
          32 -
          (showSearchBar ? 48 : 0) // 32 = margins; 32 = label height; 48 = searchBar height
        setListMaxHeight(
          availableHeight > maxHeightDroplist
            ? availableHeight
            : maxHeightDroplist,
        )
      }
    }
  }, [
    isDropdownVisible,
    multipleSelect,
    label,
    showSearchBar,
    offsetParent,
    maxHeightDroplist,
  ])

  useEffect(() => {
    setSelectedLabel(renderSelection())
  }, [renderSelection])

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
    if (!isDisabled) showDropdown()
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

  return (
    <div
      className={`select-with-label__wrapper ${className ? className : ''}`}
      ref={wrapperRef}
      id={id ? id : null}
    >
      <input type="hidden" name={`${name}-hidden`} value={inputValue} />{' '}
      {label && (
        <label htmlFor={name} onClick={toggleDropdown}>
          {label}
        </label>
      )}
      <div
        className="select-with-icon__wrapper"
        aria-label={
          TEXT_UTILS.isContentTextEllipsis(selectedItemRef?.current)
            ? selectedLabel
            : null
        }
      >
        <span
          ref={selectedItemRef}
          className={inputClassName}
          onClick={toggleDropdown}
        >
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
            {...{
              ref: dropDownRef,
              className: 'select__dropdown',
              wrapper: wrapperRef,
              showSearchBar,
              searchPlaceholder,
              activeOption,
              activeOptions,
              options,
              onSelect: handleSelect,
              onToggleOption,
              multipleSelect,
              tooltipPosition,
              optionsSelectedCopySingular,
              optionsSelectedCopyPlural,
              resetSelectedOptions,
              onClose: hideDropdown,
            }}
          >
            {children}
          </Dropdown>
        </div>
      )}
    </div>
  )
}

Select.propTypes = {
  className: PropTypes.string,
  label: PropTypes.node,
  id: PropTypes.string,
  name: PropTypes.string,
  placeholder: PropTypes.string,
  options: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.node,
    }),
  ),
  activeOption: PropTypes.shape({
    id: PropTypes.string,
    name: PropTypes.node,
  }),
  activeOptions: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.node,
    }),
  ),
  isValid: PropTypes.bool,
  showValidation: PropTypes.bool,
  showSearchBar: PropTypes.bool,
  searchPlaceholder: PropTypes.string,
  multipleSelect: PropTypes.oneOf(['off', 'dropdown']),
  tooltipPosition: PropTypes.string,
  isDisabled: PropTypes.bool,
  offsetParent: PropTypes.object,
  onSelect: PropTypes.func,
  onToggleOption: PropTypes.func,
  optionsSelectedCopySingular: PropTypes.func,
  optionsSelectedCopyPlural: PropTypes.func,
  resetSelectedOptions: PropTypes.func,
  checkSpaceToReverse: PropTypes.bool,
  maxHeightDroplist: PropTypes.number,
  children: PropTypes.func,
}
