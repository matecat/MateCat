import React, {
  useState,
  useRef,
  useEffect,
  useCallback,
  useLayoutEffect,
} from 'react'
import PropTypes from 'prop-types'

import {Dropdown} from './Dropdown'
import TEXT_UTILS from '../../utils/textUtils'
import ChevronDown from '../../../../../img/icons/ChevronDown'
import Tooltip from './Tooltip'
import usePortal from '../../hooks/usePortal'
import IconCloseCircle from '../icons/IconCloseCircle'

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
  isPortalDropdown,
  dropdownClassName,
  showResetButton = false,
  resetFunction = () => {},
  children,
}) => {
  const dropDownRef = useRef()
  const wrapperRef = useRef()
  const wrapperDropDownRef = useRef()
  const selectedItemRef = useRef()

  const [value, setValue] = useState(activeOption?.id ? activeOption.id : '')
  const [isDropdownVisible, setDropdownVisibility] = useState(false)
  const [isDropdownReversed, setDropdownReversed] = useState(false)
  const [selectedLabel, setSelectedLabel] = useState('')
  const [portalCoords, setPortalCoords] = useState()

  const Portal = usePortal(document.body)

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
    setPortalCoords()
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
      dropDownRef.current &&
      (!isPortalDropdown || (isPortalDropdown && portalCoords))
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
        : (wrapperNode.offsetParent ?? document.body)
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
    checkSpaceToReverse,
    isPortalDropdown,
    portalCoords,
  ])

  useEffect(() => {
    setSelectedLabel(renderSelection())
  }, [renderSelection])

  useLayoutEffect(() => {
    if (isDropdownVisible && isPortalDropdown) {
      const getPortalCoords = () => {
        const {x, y, width, height} = wrapperRef.current.getBoundingClientRect()
        setPortalCoords({x, y, width, height})
      }
      window.addEventListener('resize', getPortalCoords)

      getPortalCoords()

      return () => {
        window.removeEventListener('resize', getPortalCoords)
      }
    }
  }, [isDropdownVisible, isPortalDropdown])

  const checkIfShouldHideDropdown = (event) => {
    const isTabPressed = event.keyCode === 9
    const isEscPressed = event.keyCode === 27

    const containsTarget = isPortalDropdown
      ? wrapperDropDownRef.current &&
        !wrapperDropDownRef.current.contains(event.target) &&
        wrapperRef.current &&
        !wrapperRef.current.contains(event.target)
      : wrapperRef.current && !wrapperRef.current.contains(event.target)

    if (
      (multipleSelect === 'modal' && (isTabPressed || isEscPressed)) ||
      (multipleSelect !== 'modal' &&
        (isTabPressed || isEscPressed || containsTarget))
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

  const dropdownRender = isDropdownVisible && (
    <div
      ref={wrapperDropDownRef}
      className={`select__dropdown-wrapper ${
        multipleSelect === 'modal'
          ? 'select__dropdown-wrapper--is-multiselect'
          : ''
      } ${isDropdownReversed ? 'select__dropdown--is-reversed' : ''} ${
        dropdownClassName ? dropdownClassName : ''
      } ${isPortalDropdown ? 'select-with-label__wrapper-is-portal' : ''}`}
      {...(portalCoords && {
        style: {
          transform: `translate(${portalCoords.x}px,${!isDropdownReversed ? portalCoords.y + portalCoords.height : portalCoords.y}px)`,
          width: `${portalCoords.width}px`,
        },
      })}
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
  )

  const portalDropdownRender = (
    <Portal id="portal-root">{dropdownRender}</Portal>
  )

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
      <Tooltip
        content={
          TEXT_UTILS.isContentTextEllipsis(selectedItemRef?.current?.firstChild)
            ? selectedLabel
            : ''
        }
      >
        <div ref={selectedItemRef} className="select-with-icon__wrapper">
          <div className={inputClassName} onClick={toggleDropdown}>
            {renderSelection()}
            {showResetButton && activeOption ? (
              <div
                className="icon-reset"
                onClick={(e) => {
                  e.stopPropagation()
                  resetFunction()
                }}
              >
                <IconCloseCircle />
              </div>
            ) : undefined}
          </div>
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
      </Tooltip>
      {isPortalDropdown ? portalDropdownRender : dropdownRender}
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
  isPortalDropdown: PropTypes.bool,
  dropdownClassName: PropTypes.string,
  showResetButton: PropTypes.bool,
  resetFunction: PropTypes.func,
  children: PropTypes.func,
}
