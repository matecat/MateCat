import React, {useState} from 'react'
import PropTypes from 'prop-types'
import {
  DropdownMenu,
  DROPDOWN_MENU_ALIGN,
} from '../../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE} from '../../common/Button/Button'
import IconFilter from '../../icons/IconFilter'

const STATES = [
  {label: 'Active', value: 'active'},
  {label: 'Archived', value: 'archived'},
  {label: 'Cancelled', value: 'cancelled'},
]

const FilterProjectsStatus = ({filterFunction}) => {
  const [currentState, setCurrentState] = useState(STATES[0].value)

  const items = STATES.map(({label, value}) => ({
    label,
    selected: value === currentState,
    testId: `item-${value}`,
    onClick: () => {
      filterFunction(value)
      setCurrentState(value)
    },
  }))

  return (
    <div data-testid="status-filter">
      <DropdownMenu
        dropdownClassName="filter-project-status-dropdown"
        align={DROPDOWN_MENU_ALIGN.RIGHT}
        toggleButtonProps={{
          mode: BUTTON_MODE.BASIC,
          size: BUTTON_SIZE.STANDARD,
          className: 'filter-project-status-dropdown-trigger',
          testId: 'status-filter-trigger',
          children: (
            <>
              <IconFilter width={36} height={36} color={'#002b5c'} />
              {STATES.find(({value}) => value === currentState)?.label}
            </>
          ),
        }}
        items={items}
      />
    </div>
  )
}

FilterProjectsStatus.propTypes = {
  filterFunction: PropTypes.func,
}

export default FilterProjectsStatus
