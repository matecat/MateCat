import React, {useState, useRef, useEffect, useCallback} from 'react'
import PropTypes from 'prop-types'

import {Dropdown} from './Dropdown'
import TEXT_UTILS from '../../utils/textUtils'
import ChevronDown from '../../../img/icons/ChevronDown'
import Tooltip from './Tooltip'
import usePortal from '../../hooks/usePortal'
import IconClose from '../../../img/icons/IconClose'
import classnames from 'classnames'
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
  onCloseSelect = () => {},
  optionsSelectedCopySingular = () => {},
  optionsSelectedCopyPlural = () => {},
  checkSpaceToReverse = true,
  maxHeightDroplist = 420,
  isPortalDropdown,
  isActiveOptionOnTop,
  dropdownClassName,
  showResetButton = false,
  resetFunction = () => {},
  tooltipContent,
  isActiveOptionCheckmarkVisible,
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

  const hideDropdown = useRef()
  hideDropdown.current = () => {
    if (!wrapperRef.current) return
    const {ownerDocument} = wrapperRef.current
    ownerDocument.removeEventListener('mousedown', checkIfShouldHideDropdown)
    ownerDocument.removeEventListener('keydown', checkIfShouldHideDropdown)

    setDropdownVisibility(false)
    setDropdownReversed(false)
    onCloseSelect?.()
  }
  const toggleDropdown = () => {
    if (!isDisabled) {
      if (isDropdownVisible) {
        hideDropdown.current()
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
      const {setListMaxHeight} = dropDownRef.current
      const wrapperNode = wrapperRef.current

      const SEARCH_BAR_HEIGHT = 56
      const MARGIN_DROPDOWN = 4

      const wrapperRect = wrapperNode.getBoundingClientRect()

      if (isPortalDropdown) {
        const referenceRect = offsetParent
          ? offsetParent.getBoundingClientRect()
          : {top: 0, bottom: window.innerHeight}

        const availableHeightBelow = Math.abs(
          wrapperRect.y +
            wrapperRect.height +
            (MARGIN_DROPDOWN + 20) +
            (showSearchBar ? SEARCH_BAR_HEIGHT : 0) -
            referenceRect.bottom,
        )

        if (availableHeightBelow > maxHeightDroplist) {
          setListMaxHeight(maxHeightDroplist)
        } else {
          if (checkSpaceToReverse) {
            setDropdownReversed(true)
          }

          const labelHeight =
            wrapperNode
              .getElementsByTagName('label')?.[0]
              ?.getBoundingClientRect().height ?? 0

          const availableHeightAbove =
            wrapperRect.y -
            (MARGIN_DROPDOWN + 20) -
            labelHeight -
            referenceRect.top

          setListMaxHeight(
            Math.max(0, Math.min(availableHeightAbove, maxHeightDroplist)),
          )
        }
        return
      }

      const offsetParentElement = offsetParent
        ? offsetParent
        : (wrapperNode.offsetParent ?? document.body)

      const parentHeight = offsetParentElement.getBoundingClientRect().height

      const parentScrollTop =
        offsetParentElement === document.body
          ? document.documentElement.scrollTop
          : offsetParentElement.offsetTop

      const availableHeightBelow = Math.abs(
        wrapperRect.y +
          wrapperRect.height +
          (MARGIN_DROPDOWN + 20) +
          (showSearchBar ? SEARCH_BAR_HEIGHT : 0) -
          (parentHeight + parentScrollTop),
      )

      if (availableHeightBelow > maxHeightDroplist) {
        setListMaxHeight(maxHeightDroplist)
      } else {
        if (checkSpaceToReverse) {
          setDropdownReversed(true)
        }

        const labelHeight =
          wrapperNode
            .getElementsByTagName('label')?.[0]
            ?.getBoundingClientRect().height ?? 0

        const availableHeightAbove =
          wrapperRect.y - (MARGIN_DROPDOWN + 20) - labelHeight - parentScrollTop

        setListMaxHeight(
          Math.max(0, Math.min(availableHeightAbove, maxHeightDroplist)),
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
  ])

  useEffect(() => {
    if (!isDropdownVisible || !isPortalDropdown) return

    let rafId

    // Matches Dropdown.scss's $dropdown-to-bottom-margin — the same gap the
    // non-reversed (opens-below) case gets between the trigger and the
    // dropdown.
    const DROPDOWN_TO_TRIGGER_GAP = 4

    const updatePosition = () => {
      const rect = wrapperRef.current?.getBoundingClientRect()

      if (rect && wrapperDropDownRef.current) {
        const x = rect.x + window.scrollX
        let y = rect.y + window.scrollY
        const style = wrapperDropDownRef.current.style

        if (isDropdownReversed) {
          // `position: absolute` has no positioned ancestor here (the portal
          // root is a plain, unpositioned div), so its containing block is
          // the initial containing block — sized to the *viewport*, not the
          // document, and anchored at the document's own top-left corner
          // (not at the current scroll position). `bottom` has to be
          // expressed in that same document-relative space: how far the
          // trigger's document-relative top (y) sits above the containing
          // block's document-relative bottom edge (window.innerHeight).

          y = wrapperRef.current.lastChild?.getBoundingClientRect?.()
            ? wrapperRef.current.lastChild.getBoundingClientRect().y
            : rect.y

          style.transform = `translateX(${x}px)`
          style.top = 'auto'
          style.bottom = `${window.innerHeight - y + DROPDOWN_TO_TRIGGER_GAP}px`
        } else {
          style.transform = `translate(${x}px,${y + rect.height}px)`
          style.top = ''
          style.bottom = 'auto'
        }
        style.width = `${rect.width}px`
      }
    }

    const handleScroll = () => {
      cancelAnimationFrame(rafId)

      rafId = requestAnimationFrame(() => {
        updatePosition()
      })
    }

    updatePosition()

    window.addEventListener('scroll', handleScroll, true)
    window.addEventListener('resize', handleScroll)

    return () => {
      window.removeEventListener('scroll', handleScroll, true)
      window.removeEventListener('resize', handleScroll)
    }
  }, [isDropdownVisible, isPortalDropdown, isDropdownReversed])

  useEffect(() => {
    setSelectedLabel(renderSelection())
  }, [renderSelection])

  const checkIfShouldHideDropdown = useCallback(
    (event) => {
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
        hideDropdown.current()
      }
    },
    [isPortalDropdown, multipleSelect],
  )

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

    hideDropdown.current()
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

    return classnames(
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
          isActiveOptionOnTop,
          optionsSelectedCopySingular,
          optionsSelectedCopyPlural,
          onClose: hideDropdown.current,
          isActiveOptionCheckmarkVisible,
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
            : tooltipContent
              ? tooltipContent
              : ''
        }
      >
        <div ref={selectedItemRef} className="select-with-icon__wrapper">
          <div className={inputClassName} onClick={toggleDropdown}>
            {showResetButton && (activeOption || activeOptions) && (
              <div
                className="icon-reset"
                onClick={(e) => {
                  e.stopPropagation()
                  resetFunction()
                }}
              >
                <IconClose size={12} />
              </div>
            )}
            <span>{renderSelection()}</span>
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
  checkSpaceToReverse: PropTypes.bool,
  maxHeightDroplist: PropTypes.number,
  isPortalDropdown: PropTypes.bool,
  isActiveOptionOnTop: PropTypes.bool,
  dropdownClassName: PropTypes.string,
  showResetButton: PropTypes.bool,
  resetFunction: PropTypes.func,
  tooltipContent: PropTypes.string,
  isActiveOptionCheckmarkVisible: PropTypes.bool,
  children: PropTypes.func,
}
