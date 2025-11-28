import React, {useCallback, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import ManageConstants from '../../../constants/ManageConstants'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../../common/Button/Button'
import TEXT_UTILS from '../../../utils/textUtils'
import CommonUtils from '../../../utils/commonUtils'
import {Input, INPUT_SIZE} from '../../common/Input/Input'
import IconSearch from '../../icons/IconSearch'
import IconDown from '../../icons/IconDown'
import LabelWithTooltip from '../../common/LabelWithTooltip'

const MembersFilter = ({selectedTeam, currentUser, setCurrentUser}) => {
  const [isDropdownVisible, setIsDropdownVisible] = useState(false)
  const [searchFilter, setSearchFilter] = useState()

  const wrapperRef = useRef()

  const getImgUser = (userData) => {
    const {user_metadata: metadata, user} = userData

    return metadata ? (
      <img
        className="ui avatar image ui-user-dropdown-image"
        src={metadata.gplus_picture}
      />
    ) : (
      <a className="ui circular label">{CommonUtils.getUserShortName(user)}</a>
    )
  }

  const closeDropdown = useCallback((e) => {
    if (e) e.stopPropagation()
    if (wrapperRef.current && !wrapperRef.current.contains(e?.target)) {
      window.eventHandler.removeEventListener(
        'click.membersfilterdropdown',
        closeDropdown,
      )

      setIsDropdownVisible(false)
    }
  }, [])

  const toggleDropdown = () => {
    if (isDropdownVisible) {
      window.eventHandler.removeEventListener(
        'click.membersfilterdropdown',
        closeDropdown,
      )
    } else {
      window.eventHandler.addEventListener(
        'click.membersfilterdropdown',
        closeDropdown,
      )
    }

    setIsDropdownVisible((prevState) => !prevState)
  }

  const onChangeUserCallback = (data) => {
    setCurrentUser(data)
    closeDropdown()
  }

  const teamMembers = selectedTeam.get('members').toJS()
  const filteredMembers = searchFilter
    ? teamMembers.filter((member) => {
        const user = member.user
        const fullName = `${user.first_name} ${user.last_name}`
        const regex = new RegExp(TEXT_UTILS.escapeRegExp(searchFilter), 'i')
        if (!searchFilter) return true
        else return regex.test(fullName)
      })
    : teamMembers

  return (
    <div ref={wrapperRef} className="members-filter-dropdown-container">
      <Button
        className={`trigger-button user-project-dropdown${isDropdownVisible ? ' open' : ''}`}
        type={BUTTON_TYPE.BASIC}
        size={BUTTON_SIZE.SMALL}
        onClick={toggleDropdown}
      >
        {currentUser === ManageConstants.ALL_MEMBERS_FILTER ? (
          <div className="item-filter all">
            <span>ALL</span>
            All Members
          </div>
        ) : currentUser === ManageConstants.NOT_ASSIGNED_FILTER ? (
          <div className="item-filter">
            <span>NA</span>
            Not assigned
          </div>
        ) : (
          <>
            {getImgUser(currentUser)}
            <LabelWithTooltip
              className="user-full-name"
              isPositionBottom={true}
            >
              <span>{`${currentUser.user.first_name} ${currentUser.user.last_name}`}</span>
            </LabelWithTooltip>
          </>
        )}
        <IconDown size={20} />
      </Button>
      <div className={`dropdown${isDropdownVisible ? ' open' : ''}`}>
        <ul>
          <li
            className={`item-filter ${currentUser === ManageConstants.NOT_ASSIGNED_FILTER ? 'active' : ''}`}
            onClick={() =>
              onChangeUserCallback(ManageConstants.NOT_ASSIGNED_FILTER)
            }
          >
            <span>NA</span>
            Not assigned
          </li>
          <li
            className={`item-filter all ${currentUser === ManageConstants.ALL_MEMBERS_FILTER ? 'active' : ''}`}
            onClick={() =>
              onChangeUserCallback(ManageConstants.ALL_MEMBERS_FILTER)
            }
          >
            <span>ALL</span>
            All Members
          </li>
          <li className="search-by-name">
            <Input
              name="searchByName"
              size={INPUT_SIZE.COMPRESSED}
              placeholder="Search by name"
              value={searchFilter}
              onChange={({currentTarget: {value}}) => setSearchFilter(value)}
              icon={<IconSearch />}
            />
          </li>
          {filteredMembers.length > 0 ? (
            filteredMembers.map((userData) => (
              <li
                key={userData.user.uid}
                className={`${currentUser?.user?.uid === userData.user.uid ? 'active' : ''} ${userData.projects === 0 ? 'disabled' : ''}`}
                onClick={() => onChangeUserCallback(userData)}
              >
                <div>
                  {getImgUser(userData)}
                  {`${userData.user.first_name} ${userData.user.last_name}`}
                </div>
                <span>{userData.projects}</span>
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

MembersFilter.propTypes = {
  selectedTeam: PropTypes.object,
  currentUser: PropTypes.string,
  setCurrentUser: PropTypes.func,
}

export default MembersFilter
