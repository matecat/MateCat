import React, {useState} from 'react'
import PropTypes from 'prop-types'
import ManageConstants from '../../../constants/ManageConstants'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../../common/Button/Button'
import TEXT_UTILS from '../../../utils/textUtils'
import CommonUtils from '../../../utils/commonUtils'
import {Input, INPUT_SIZE} from '../../common/Input/Input'
import IconSearch from '../../icons/IconSearch'
import IconDown from '../../icons/IconDown'
import LabelWithTooltip from '../../common/LabelWithTooltip'
import * as Popover from '@radix-ui/react-popover'

const MembersFilter = ({selectedTeam, currentUser, setCurrentUser}) => {
  const [searchFilter, setSearchFilter] = useState()
  const [open, setOpen] = useState(false)

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

  const onChangeUserCallback = (data) => {
    setCurrentUser(data)
    setOpen(false)
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
    <Popover.Root open={open} onOpenChange={setOpen}>
      <Popover.Trigger asChild>
        <Button
          className={`members-filter-trigger-button user-project-dropdown${open ? ' members-filter-open' : ''}`}
          type={BUTTON_TYPE.BASIC}
          size={BUTTON_SIZE.SMALL}
        >
          {currentUser === ManageConstants.ALL_MEMBERS_FILTER ? (
            <div className="members-filter-item-filter members-filter-all members-filter-user-full-name">
              <span>ALL</span>
              All Members
            </div>
          ) : currentUser === ManageConstants.NOT_ASSIGNED_FILTER ? (
            <div className="members-filter-item-filter members-filter-user-full-name">
              <span>NA</span>
              Not assigned
            </div>
          ) : (
            <>
              {getImgUser(currentUser)}
              <LabelWithTooltip
                className="members-filter-user-full-name"
                isPositionBottom={true}
              >
                <span>{`${currentUser.user.first_name} ${currentUser.user.last_name}`}</span>
              </LabelWithTooltip>
            </>
          )}
          <IconDown size={20} />
        </Button>
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Content
          sideOffset={5}
          className="members-filter-popover-content"
        >
          <div className="members-filter-dropdown-content">
            <ul>
              <li
                className={`members-filter-item-filter ${currentUser === ManageConstants.NOT_ASSIGNED_FILTER ? 'active' : ''}`}
                onClick={() =>
                  onChangeUserCallback(ManageConstants.NOT_ASSIGNED_FILTER)
                }
              >
                <span>NA</span>
                Not assigned
              </li>
              <li
                className={`members-filter-item-filter members-filter-all ${currentUser === ManageConstants.ALL_MEMBERS_FILTER ? 'active' : ''}`}
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
                  onChange={({currentTarget: {value}}) =>
                    setSearchFilter(value)
                  }
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
                <span className="members-filter-no-results">
                  No results found.
                </span>
              )}
            </ul>
          </div>
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  )
}

MembersFilter.propTypes = {
  selectedTeam: PropTypes.object,
  currentUser: PropTypes.string,
  setCurrentUser: PropTypes.func,
}

export default MembersFilter
