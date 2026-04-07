import React, {
  useState,
  useRef,
  useCallback,
  forwardRef,
  useImperativeHandle,
} from 'react'
import FilterProjectsStatus from './FilterProjectsStatus'
import SearchInput from './SearchInput'
import ManageActions from '../../../actions/ManageActions'
import ManageConstants from '../../../constants/ManageConstants'

const FilterProjects = forwardRef((props, ref) => {
  const [currentStatus, setCurrentStatus] = useState('active')
  const [currentUser, setCurrentUser] = useState(
    ManageConstants.ALL_MEMBERS_FILTER,
  )
  const currentText = useRef()

  const handleSetCurrentUser = useCallback(
    (value) => {
      setCurrentUser(value)

      ManageActions.filterProjects(
        typeof value === 'object' ? value.user.uid : value,
        currentText.current,
        currentStatus,
      )
    },
    [currentStatus],
  )

  const onChangeSearchInput = useCallback(
    (value) => {
      if (
        typeof currentText.current !== 'undefined' &&
        currentText.current !== value
      )
        ManageActions.filterProjects(
          typeof currentUser === 'object' ? currentUser.user.uid : currentUser,
          value,
          currentStatus,
        )

      currentText.current = value
    },
    [currentStatus, currentUser],
  )

  const filterByStatus = useCallback(
    (status) => {
      setCurrentStatus(status)

      ManageActions.filterProjects(
        typeof currentUser === 'object' ? currentUser.user.uid : currentUser,
        currentText.current,
        status,
      )
    },
    [currentUser],
  )

  useImperativeHandle(ref, () => ({
    currentUser,
    handleSetCurrentUser,
  }))

  return (
    <div className="filter-projects-container">
      <SearchInput onChange={onChangeSearchInput} />
      <FilterProjectsStatus filterFunction={filterByStatus} />
    </div>
  )
})

FilterProjects.displayName = 'FilterProjects'

export default FilterProjects
