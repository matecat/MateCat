import React, {
  useState,
  useRef,
  useEffect,
  Fragment,
  useCallback,
  forwardRef,
  useImperativeHandle,
} from 'react'
import PropTypes from 'prop-types'

import Check from '../../../../../img/icons/Check'
import Search from '../../../../../img/icons/Search'
import TEXT_UTILS from '../../utils/textUtils'

export const Dropdown = forwardRef(
  (
    {
      className,
      wrapper,
      options,
      activeOption,
      activeOptions,
      mostPopularOptions,
      showSearchBar = false,
      searchPlaceholder = 'Search...',
      multipleSelect = 'off',
      tooltipPosition = 'left',
      onSelect = () => {},
      onToggleOption = () => {},
      onSearchBarFocus = () => {},
      optionsSelectedCopySingular = () => {},
      optionsSelectedCopyPlural = () => {},
      resetSelectedOptions = () => {},
      onClose = () => {},
      children,
    },
    ref,
  ) => {
    const [queryFilter, setQueryFilter] = useState('')
    const [highlightedOption, setHighlightedOption] = useState()
    const [rowTooltip, setRowTooltip] = useState()

    const listRef = useRef()
    const textInputRef = useRef()
    const queryFilterRef = useRef('')
    const itemsOnTopRef = useRef(activeOptions)

    useImperativeHandle(ref, () => ({
      getListRef: () => listRef?.current,
      setListMaxHeight: (value) => {
        if (listRef?.current) {
          listRef.current.style.maxHeight = `${value}px`
          listRef.current.parentElement.style.height = `${
            listRef.current.offsetHeight
              ? listRef.current.offsetHeight + 'px'
              : 'auto'
          }`
          listRef.current.parentElement.ontransitionend = () =>
            (listRef.current.parentElement.style.height = 'auto')
        }
      },
    }))

    const handleClick = (option) => {
      if (multipleSelect !== 'off') {
        onToggleOption(option, onClose)
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

    const getFilteredOptions = useCallback(
      (filter = null) => {
        const filteredOptions = options
          ? options.filter((option) => {
              return !!option.id
            })
          : []
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
      },
      [activeOption, options],
    )

    const updateQueryFilter = (event) => {
      queryFilterRef.current = event.target.value
      setQueryFilter(event.target.value)
    }

    useEffect(() => {
      if (listRef && listRef.current) {
        listRef.current.scrollTop = 0
      }
    }, [listRef, queryFilter])

    useEffect(() => {
      if (!showSearchBar) return
      const timer = setTimeout(() => textInputRef.current.focus(), 0)

      return () => clearTimeout(timer)
    }, [showSearchBar])

    // keyboard shortcuts
    useEffect(() => {
      if (!wrapper?.current) return
      const {current} = wrapper
      const {current: currentList} = listRef

      const scrollToRow = ({index, direction = 'down', reset = false}) => {
        const getMargins = (element) =>
          parseInt(
            window.getComputedStyle(element).marginTop.split('px')?.[0] || 0,
          ) +
          parseInt(
            window.getComputedStyle(element).marginBottom.split('px')?.[0] || 0,
          )

        const maxHeight = parseInt(
          window.getComputedStyle(currentList).maxHeight.split('px')?.[0] || 0,
        )
        const firstRow = currentList.getElementsByTagName('li')[0]
        const rowHeight = firstRow.offsetHeight + getMargins(firstRow)

        const scrollPoint = currentList.scrollTop

        let indexIter = 0
        const pointY = Array.from(currentList.children).reduce((acc, item) => {
          const isOptionElement = item.tagName.toLowerCase() === 'li'
          const value =
            acc +
            (indexIter <= index ? item.offsetHeight + getMargins(item) : 0)
          if (isOptionElement) indexIter++
          return value
        }, 0)
        if (direction === 'down' && reset) {
          return (currentList.scrollTop = 0)
        } else if (direction === 'top' && reset) {
          return (currentList.scrollTop = Array.from(
            currentList.children,
          ).reduce(
            (acc, item) => acc + item.offsetHeight + getMargins(item),
            0,
          ))
        }
        if (
          direction === 'down' &&
          pointY > maxHeight + scrollPoint - rowHeight
        ) {
          currentList.scrollTop =
            pointY - (Math.floor(maxHeight / rowHeight) - 1) * rowHeight
        } else if (
          direction === 'top' &&
          pointY - rowHeight * 3 <= Math.ceil(scrollPoint)
        ) {
          currentList.scrollTop = pointY - rowHeight * 3
        } else {
          if (pointY > scrollPoint + maxHeight || pointY <= scrollPoint) {
            currentList.scrollTop = index * rowHeight
          }
        }
      }

      const navigateItems = (e) => {
        const options = getFilteredOptions()
        if (!options.length) return
        if (e.key === 'ArrowUp') {
          setHighlightedOption((prevState) => {
            const getPrevIndex = ({index, lastIndex}) =>
              index >= 0 ? index : lastIndex

            const lastIndex = options.length - 1

            const findIndex = prevState
              ? options.findIndex(({id}) => id === prevState.id) - 1
              : lastIndex

            const prevIndex = getPrevIndex({index: findIndex, lastIndex})
            scrollToRow({
              index: prevIndex,
              direction: 'top',
              reset: findIndex < 0,
            })
            return options[getPrevIndex({index: prevIndex, lastIndex})]
              ? options[getPrevIndex({index: prevIndex, lastIndex})]
              : 0
          })
        } else if (e.key === 'ArrowDown') {
          setHighlightedOption((prevState) => {
            const getNextIndex = ({index}) =>
              index < options.length ? index : 0

            const findIndex = prevState
              ? options.findIndex(({id}) => id === prevState.id) + 1
              : 0

            const nextIndex = getNextIndex({index: findIndex})
            scrollToRow({
              index: nextIndex,
              direction: 'down',
              reset: findIndex === options.length,
            })
            return options[getNextIndex({index: nextIndex})]
              ? options[getNextIndex({index: nextIndex})]
              : 0
          })
        } else if (e.key === 'Enter' && highlightedOption) {
          handleClick(highlightedOption)
        } else if (e.key === 'Escape') {
          onClose()
        } else {
          return
        }

        e.stopPropagation()
      }

      current.addEventListener('keydown', navigateItems)

      return () => current.removeEventListener('keydown', navigateItems)
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
      wrapper,
      listRef,
      highlightedOption,
      activeOptions,
      showSearchBar,
      getFilteredOptions,
    ])

    useEffect(() => {
      setHighlightedOption(undefined)
    }, [queryFilter])

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
      const {
        beforeRow,
        row,
        afterRow,
        cancelHandleClick,
        getElementToEllipsis,
      } =
        children?.({
          index,
          ...option,
          optionsLength: !isNoResultsFound ? getFilteredOptions().length : 1,
          queryFilter,
          showActiveOptionIcon,
          resetQueryFilter: () => {
            setQueryFilter('')
            queryFilterRef.current = ''
          },
          onClose,
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
              isHighlightedOption
                ? 'dropdown__option--is-highlighted-option'
                : ''
            } ${
              isNoResultsFound ? 'dropdown__option--is-no-results-found' : ''
            }`}
            onClick={() => {
              if (!isNoResultsFound && !cancelHandleClick) handleClick(option)
            }}
            onMouseEnter={(e) =>
              TEXT_UTILS.isContentTextEllipsis(
                getElementToEllipsis?.()
                  ? getElementToEllipsis()
                  : e.target?.firstChild,
              ) &&
              setRowTooltip({
                label: option.name,
                top: e.target.offsetTop - listRef?.current.scrollTop,
              })
            }
            onMouseLeave={() => setRowTooltip()}
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
        {rowTooltip && (
          <div
            className={`dropdown__tooltip dropdown__tooltip-${tooltipPosition}`}
            aria-label={rowTooltip.label}
            tooltip-position={tooltipPosition}
            style={{top: rowTooltip.top}}
          ></div>
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
        <div className="container__dropdown__list">
          <ul className="dropdown__list" ref={listRef}>
            {filteredOptions.map(renderOption)}
            {showSearchBar &&
              filteredOptions.length === 0 &&
              renderOption(noResultsFoundOption, 0)}
          </ul>
        </div>
      </div>
    )
  },
)

Dropdown.displayName = 'Dropdown'

Dropdown.propTypes = {
  className: PropTypes.string,
  wrapper: PropTypes.any,
  options: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.node,
    }),
  ).isRequired,
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
  mostPopularOptions: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.node,
    }),
  ),
  showSearchBar: PropTypes.bool,
  searchPlaceholder: PropTypes.string,
  multipleSelect: PropTypes.oneOf(['off', 'dropdown', 'modal']),
  tooltipPosition: PropTypes.oneOf(['left', 'right']),
  onSelect: PropTypes.func,
  onToggleOption: PropTypes.func,
  onSearchBarFocus: PropTypes.func,
  optionsSelectedCopySingular: PropTypes.func,
  optionsSelectedCopyPlural: PropTypes.func,
  resetSelectedOptions: PropTypes.func,
  onClose: PropTypes.func,
  children: PropTypes.func,
}
