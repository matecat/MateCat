import React, {useState, useRef, useEffect, Fragment} from 'react'
import PropTypes from 'prop-types'

import Check from '../../../../../img/icons/Check'
import Search from '../../../../../img/icons/Search'

export const Dropdown = ({
  className,
  listRef,
  options,
  activeOption,
  activeOptions,
  mostPopularOptions,
  showSearchBar = false,
  searchPlaceholder = 'Search...',
  multipleSelect = 'off',
  onSelect = () => {},
  onToggleOption = () => {},
  onSearchBarFocus = () => {},
  optionsSelectedCopySingular = () => {},
  optionsSelectedCopyPlural = () => {},
  resetSelectedOptions = () => {},
  onClose = () => {},
  children,
}) => {
  const [queryFilter, setQueryFilter] = useState('')
  const [highlightedOption, setHighlightedOption] = useState()

  const textInputRef = useRef()
  const queryFilterRef = useRef('')
  const highlightedOptionRef = useRef()
  const itemsOnTopRef = useRef(activeOptions)

  const updateHighlightedOption = (newValue) => {
    highlightedOptionRef.current = newValue
    setHighlightedOption(newValue)
  }

  const handleClick = (option) => {
    if (multipleSelect !== 'off') {
      onToggleOption(option)
    } else {
      onSelect(option)
    }
  }

  const getOptionsSelectedCopy = () => {
    if (optionsSelectedCopySingular && optionsSelectedCopyPlural) {
      const optionsSelectedLength = activeOptions ? activeOptions.length : 0
      return optionsSelectedLength === 1
        ? optionsSelectedCopySingular(optionsSelectedLength)
        : optionsSelectedCopyPlural(optionsSelectedLength)
    } else {
      return ''
    }
  }

  const getFilteredOptions = (filter = null) => {
    const filteredOptions = options.filter((option) => {
      return !!option.id
    })
    const currentFilter = filter || queryFilterRef.current

    if (currentFilter) {
      const lowerQueryFilter = currentFilter.toLowerCase()
      return filteredOptions
        .filter((option) => {
          return (
            option.name.toLowerCase().indexOf(lowerQueryFilter) != -1 ||
            option.id.toLowerCase().indexOf(lowerQueryFilter) != -1
          )
        })
        .sort((optionA, optionB) => {
          const queryPositionInOptionA = optionA.name
            .toLowerCase()
            .indexOf(lowerQueryFilter)
          const queryPositionInOptionB = optionB.name
            .toLowerCase()
            .indexOf(lowerQueryFilter)
          const queryPositionInOptionANormalized =
            queryPositionInOptionA > -1 ? queryPositionInOptionA : 1000
          const queryPositionInOptionBNormalized =
            queryPositionInOptionB > -1 ? queryPositionInOptionB : 1000
          return queryPositionInOptionANormalized >
            queryPositionInOptionBNormalized
            ? 1
            : queryPositionInOptionANormalized <
              queryPositionInOptionBNormalized
            ? -1
            : 0
        })
    } else {
      const standardOptions = []
      const activeOptions = itemsOnTopRef.current || []
      const activeOptionId = activeOption ? activeOption.id : undefined
      const onTopOptions = []
      filteredOptions.map((option) => {
        const isActiveOption = option.id === activeOptionId
        const isActiveOptions =
          activeOptions.filter((activeOption) => {
            return activeOption.id === option.id
          }).length > 0
        const isOnTop = isActiveOption || !!isActiveOptions
        if (isOnTop) {
          onTopOptions.push(option)
        } else {
          standardOptions.push(option)
        }
      })
      return onTopOptions.concat(standardOptions)
    }
  }

  const updateQueryFilter = (event) => {
    const newHighlightedOption = getFilteredOptions(event.target.value)[0]
    queryFilterRef.current = event.target.value
    setQueryFilter(event.target.value)
    updateHighlightedOption(newHighlightedOption)
  }

  useEffect(() => {
    if (listRef && listRef.current) {
      listRef.current.scrollTop = 0
    }
  }, [listRef, queryFilter])

  /* keyboard navigation */
  // useEvent(document, 'keydown', (event) => {
  //   if (showSearchBar) {
  //     const keyCode = event.keyCode
  //     const previousHighlightedOption =
  //       highlightedOptionRef.current || activeOption
  //     const previousHighlightedOptionIndex = getFilteredOptions().reduce(
  //       (highlightedOptionIndex, currentOption, index) => {
  //         const previousHighlightedOptionId = previousHighlightedOption
  //           ? previousHighlightedOption.id
  //           : undefined
  //         return currentOption.id === previousHighlightedOptionId
  //           ? index
  //           : highlightedOptionIndex
  //       },
  //       0,
  //     )
  //     const optionsPerColumn = Math.ceil(options.length / 4)
  //
  //     if (keyCode === 38) {
  //       // up key
  //       const nextHighlightedOptionIndex =
  //         previousHighlightedOptionIndex > 0
  //           ? previousHighlightedOptionIndex - 1
  //           : 0
  //       const nextHighlightedOption = getFilteredOptions().filter(
  //         (option, index) => {
  //           return index === nextHighlightedOptionIndex
  //         },
  //       )[0]
  //
  //       updateHighlightedOption(
  //         nextHighlightedOption && nextHighlightedOption.id
  //           ? nextHighlightedOption
  //           : previousHighlightedOption,
  //       )
  //     } else if (keyCode === 40) {
  //       // down key
  //       const nextHighlightedOptionIndex =
  //         previousHighlightedOptionIndex < getFilteredOptions().length
  //           ? previousHighlightedOptionIndex + 1
  //           : getFilteredOptions().length - 1
  //       const nextHighlightedOption = getFilteredOptions().filter(
  //         (option, index) => {
  //           return index === nextHighlightedOptionIndex
  //         },
  //       )[0]
  //
  //       updateHighlightedOption(
  //         nextHighlightedOption && nextHighlightedOption.id
  //           ? nextHighlightedOption
  //           : previousHighlightedOption,
  //       )
  //     } else if (keyCode === 37) {
  //       // left key
  //       const nextHighlightedOptionIndex =
  //         previousHighlightedOptionIndex - optionsPerColumn + 1 > 0
  //           ? Math.floor(previousHighlightedOptionIndex - optionsPerColumn)
  //           : previousHighlightedOptionIndex
  //       const nextHighlightedOption = getFilteredOptions().filter(
  //         (option, index) => {
  //           return index === nextHighlightedOptionIndex
  //         },
  //       )[0]
  //
  //       updateHighlightedOption(
  //         nextHighlightedOption && nextHighlightedOption.id
  //           ? nextHighlightedOption
  //           : previousHighlightedOption,
  //       )
  //     } else if (keyCode === 39) {
  //       // right key
  //       const nextHighlightedOptionIndex =
  //         previousHighlightedOptionIndex + optionsPerColumn <
  //         getFilteredOptions().length
  //           ? Math.floor(previousHighlightedOptionIndex + optionsPerColumn)
  //           : getFilteredOptions().length - 1
  //       const nextHighlightedOption = getFilteredOptions().filter(
  //         (option, index) => {
  //           return index === nextHighlightedOptionIndex
  //         },
  //       )[0]
  //
  //       updateHighlightedOption(
  //         nextHighlightedOption && nextHighlightedOption.id
  //           ? nextHighlightedOption
  //           : previousHighlightedOption,
  //       )
  //     } else if (keyCode === 13 || keyCode === 9) {
  //       event.stopPropagation()
  //       event.preventDefault()
  //       if (multipleSelect !== 'off') {
  //         onToggleOption(highlightedOptionRef.current)
  //       } else {
  //         onSelect(highlightedOptionRef.current)
  //       }
  //     }
  //   }
  // })

  useEffect(() => {
    if (listRef && listRef.current) {
      const listRefNode = listRef.current
      const selectedItem = listRefNode.querySelector(
        '.dropdown__option--is-highlighted-option',
      )
      const selectedItemOffsetTopPosition = selectedItem
        ? selectedItem.offsetTop
        : 0
      const elementIsVisibleFromTop =
        selectedItemOffsetTopPosition - 48 > listRefNode.scrollTop
      const elementIsVisibleFromBottom =
        selectedItemOffsetTopPosition - 32 <
        listRefNode.scrollTop + listRefNode.offsetHeight
      if (!elementIsVisibleFromTop) {
        listRefNode.scrollTop = selectedItemOffsetTopPosition - 56
      } else if (!elementIsVisibleFromBottom) {
        listRefNode.scrollTop =
          selectedItemOffsetTopPosition - listRefNode.offsetHeight - 8
      }
    }
  }, [highlightedOption, listRef])
  /* end keyboard navigation */

  useEffect(() => {
    if (!showSearchBar) return
    const timer = setTimeout(() => textInputRef.current.focus(), 0)

    return () => clearTimeout(timer)
  }, [showSearchBar])

  const renderOption = (option, index) => {
    const isHighlightedOption = highlightedOption
      ? option.id === highlightedOption.id
      : false
    const currentActiveOptions = activeOptions || []
    const isActiveOption =
      option.id === (activeOption ? activeOption.id : undefined)
    const isActiveOptions =
      currentActiveOptions.filter((activeOption) => {
        return activeOption.id === option.id
      }).length > 0
    const isNoResultsFound = option.id === 'noResultsFound'
    const showActiveOptionIcon = isActiveOption || isActiveOptions

    const {beforeRow, row, afterRow, cancelHandleClick} =
      children?.({
        index,
        ...option,
        optionsLength: !isNoResultsFound ? getFilteredOptions().length : 1,
        queryFilter,
        resetQueryFilter: () => {
          setQueryFilter('')
          queryFilterRef.current = ''
        },
      }) || {}

    return (
      <Fragment key={index}>
        {beforeRow && beforeRow}
        <li
          className={`dropdown__option ${
            isActiveOption || isActiveOptions
              ? 'dropdown__option--is-active-option'
              : ''
          } ${
            isHighlightedOption ? 'dropdown__option--is-highlighted-option' : ''
          } ${isNoResultsFound ? 'dropdown__option--is-no-results-found' : ''}`}
          onClick={() => {
            if (!isNoResultsFound && !cancelHandleClick) handleClick(option)
          }}
        >
          {row && !isNoResultsFound ? (
            row
          ) : (
            <>
              <span>{option.name}</span>
              {showActiveOptionIcon && <Check size={16} />}
            </>
          )}
        </li>
        {afterRow && afterRow}
      </Fragment>
    )
  }

  const renderMostPopularOption = (option, index) => {
    const isOptionSelected =
      activeOptions &&
      activeOptions.filter(function (activeOption) {
        return option.id === activeOption.id
      }).length > 0

    return (
      <span
        key={index}
        className={`dropdown__most-popular-option${
          isOptionSelected ? ' dropdown__most-popular-option--selected' : ''
        }`}
        onClick={() => handleClick(option)}
      >
        {option.name}
      </span>
    )
  }

  const filteredOptions = getFilteredOptions()
  const noResultsFoundOption = {
    name: `No results found for "${queryFilter}"`,
    id: 'noResultsFound',
  }
  const optionsSelectedCopy = getOptionsSelectedCopy()

  return (
    <div
      className={`custom-dropdown ${className} ${
        showSearchBar ? 'dropdown--has-search-bar' : ''
      } ${multipleSelect === 'modal' ? 'dropdown--is-multiple-select' : ''}`}
    >
      {(showSearchBar || multipleSelect === 'modal') && (
        <div data-testid="dropdown-search" className="dropdown__search-bar">
          <input
            className="dropdown__search-bar-input"
            placeholder={searchPlaceholder}
            type="text"
            ref={textInputRef}
            value={queryFilter}
            onChange={updateQueryFilter}
            onFocus={onSearchBarFocus}
          />
          <Search size={20} />
        </div>
      )}
      {mostPopularOptions && mostPopularOptions.length > 0 && (
        <div className="dropdown__most-popular">
          <div className="dropdown__most-popular-wrapper">
            <span className="dropdown__most-popular-label">Most popular</span>
            {mostPopularOptions &&
              mostPopularOptions.map(renderMostPopularOption)}
          </div>
        </div>
      )}
      <ul className="dropdown__list" ref={listRef}>
        {filteredOptions.map(renderOption)}
        {showSearchBar &&
          filteredOptions.length === 0 &&
          renderOption(noResultsFoundOption, 0)}
      </ul>
    </div>
  )
}

Dropdown.propTypes = {
  className: PropTypes.string,
  listRef: PropTypes.any,
  options: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.string,
    }),
  ).isRequired,
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
  mostPopularOptions: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.string,
    }),
  ),
  showSearchBar: PropTypes.bool,
  searchPlaceholder: PropTypes.string,
  multipleSelect: PropTypes.oneOf(['off', 'dropdown', 'modal']),
  onSelect: PropTypes.func,
  onToggleOption: PropTypes.func,
  onSearchBarFocus: PropTypes.func,
  optionsSelectedCopySingular: PropTypes.func,
  optionsSelectedCopyPlural: PropTypes.func,
  resetSelectedOptions: PropTypes.func,
  onClose: PropTypes.func,
  children: PropTypes.func,
}
