import React, {useCallback, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {BUTTON_SIZE, BUTTON_TYPE, Button} from '../common/Button/Button'
import IconAdd from '../icons/IconAdd'
import CommonUtils from '../../utils/commonUtils'
import {Input} from '../common/Input/Input'

export const UserProjectDropdown = ({
  users,
  openAddMember,
  changeUser,
  idAssignee,
}) => {
  const [isDropdownVisible, setIsDropdownVisible] = useState(false)
  const [filterUsers, setFilterUsers] = useState()

  const wrapperRef = useRef()

  const selectedUser = users.find(({user}) => user.uid === idAssignee)

  const onChangeSearch = useCallback(
    ({currentTarget: {value}}) => setFilterUsers(value),
    [],
  )

  const closeDropdown = useCallback((e) => {
    if (e) e.stopPropagation()
    if (wrapperRef.current && !wrapperRef.current.contains(e?.target)) {
      window.eventHandler.removeEventListener(
        'click.userprojectdropdown',
        closeDropdown,
      )

      setIsDropdownVisible(false)
    }
  }, [])

  const toggleDropdown = () => {
    if (isDropdownVisible) {
      window.eventHandler.removeEventListener(
        'click.userprojectdropdown',
        closeDropdown,
      )
    } else {
      window.eventHandler.addEventListener(
        'click.userprojectdropdown',
        closeDropdown,
      )
    }

    setIsDropdownVisible((prevState) => !prevState)
  }

  const onChangeUser = (userData) => {
    setIsDropdownVisible(false)
    changeUser(userData.user.uid)
  }

  const getImgUser = (userData) => {
    const {user_metadata: metadata, user} = userData

    return metadata ? (
      <img
        className="ui avatar image ui-user-dropdown-image"
        src={metadata.gplus_picture + '?sz=80'}
      />
    ) : (
      <a className="ui circular label">{CommonUtils.getUserShortName(user)}</a>
    )
  }

  const usersList = users.filter(
    ({user}) =>
      !filterUsers ||
      (filterUsers &&
        (new RegExp(filterUsers, 'i').test(user.first_name) ||
          new RegExp(filterUsers, 'i').test(user.last_name))),
  )

  return (
    <div
      ref={wrapperRef}
      className={`user-project-dropdown-container${isDropdownVisible ? ' open' : ''}`}
    >
      <Button
        testId="project-teams"
        className={`trigger-button user-project-dropdown${isDropdownVisible ? ' open' : ''}`}
        type={BUTTON_TYPE.BASIC}
        size={BUTTON_SIZE.SMALL}
        onClick={toggleDropdown}
      >
        {getImgUser(selectedUser)}
        {selectedUser
          ? `${selectedUser.user.first_name} ${selectedUser.user.last_name}`
          : 'Not assigned'}
      </Button>
      <div className={`dropdown${isDropdownVisible ? ' open' : ''}`}>
        <ul>
          <li className="add-new-member" onClick={openAddMember}>
            Add new member <IconAdd size={22} />
          </li>
          <li className="search-by-name">
            <Input
              name="searchByName"
              placeholder="Search by name"
              value={filterUsers}
              onChange={onChangeSearch}
            />
          </li>
          {usersList.length > 0 ? (
            usersList.map((userData) => (
              <li
                key={userData.user.uid}
                onClick={() => onChangeUser(userData)}
              >
                {getImgUser(userData)}
                {`${userData.user.first_name} ${userData.user.last_name}`}
              </li>
            ))
          ) : (
            <span className="no-results">No results found.</span>
          )}
        </ul>
      </div>
    </div>
  )
}

UserProjectDropdown.propTypes = {
  users: PropTypes.array.isRequired,
  openAddMember: PropTypes.func.isRequired,
  changeUser: PropTypes.func.isRequired,
  idAssignee: PropTypes.number,
}
