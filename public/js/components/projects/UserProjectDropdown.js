import React, {useCallback, useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {BUTTON_SIZE, BUTTON_TYPE, Button} from '../common/Button/Button'
import IconAdd from '../icons/IconAdd'
import CommonUtils from '../../utils/commonUtils'
import {INPUT_SIZE, Input} from '../common/Input/Input'
import IconSearch from '../icons/IconSearch'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import IconClose from '../icons/IconClose'
import * as Popover from '@radix-ui/react-popover'

export const UserProjectDropdown = ({
  users,
  project,
  openAddMember,
  changeUser,
  idAssignee,
}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const [open, setOpen] = useState(false)
  const [filterUsers, setFilterUsers] = useState()

  const selectedUser = users.find(({user}) => user.uid === idAssignee)
  const isPersonalTeam = userInfo
    ? userInfo.teams.find(({id}) => id === project.id_team).type === 'personal'
    : undefined

  const onChangeSearch = useCallback(
    ({currentTarget: {value}}) => setFilterUsers(value),
    [],
  )

  const handlerAddMember = () => openAddMember()

  const onChangeUser = (userData) => changeUser(userData.user.uid)

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

  const usersList = users.filter(
    ({user}) =>
      !filterUsers ||
      (filterUsers &&
        (new RegExp(filterUsers, 'i').test(user.first_name) ||
          new RegExp(filterUsers, 'i').test(user.last_name))),
  )

  const removeAssignee = (event) => {
    event.stopPropagation()
    changeUser(-1)
  }

  const isDisabled = isPersonalTeam
  const isNotAssignee = !selectedUser

  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      <Popover.Trigger asChild>
        <Button
          testId="project-teams"
          className={`user-project-dropdown user-project-dropdown-trigger${isNotAssignee ? ' not-assignee' : ''}`}
          type={BUTTON_TYPE.BASIC}
          size={BUTTON_SIZE.SMALL}
          disabled={isDisabled}
        >
          {!isNotAssignee ? (
            <>
              {getImgUser(selectedUser)}
              {selectedUser
                ? `${selectedUser.user.first_name} ${selectedUser.user.last_name}`
                : 'Not assigned'}
              {!isDisabled && (
                <div
                  className="button-remove-assignee"
                  onClick={removeAssignee}
                >
                  <IconClose />
                </div>
              )}
            </>
          ) : (
            <>
              <span>
                <i className="icon-user22" />
                Not assignee
              </span>
            </>
          )}
        </Button>
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Content sideOffset={5}>
          <div className="user-project-dropdown-content">
            <ul>
              <li className="add-new-member" onClick={handlerAddMember}>
                Add new member <IconAdd size={22} />
              </li>
              <li className="search-by-name">
                <Input
                  name="searchByName"
                  size={INPUT_SIZE.COMPRESSED}
                  placeholder="Search by name"
                  value={filterUsers}
                  onChange={onChangeSearch}
                  icon={<IconSearch />}
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
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  )
}

UserProjectDropdown.propTypes = {
  users: PropTypes.array.isRequired,
  project: PropTypes.object.isRequired,
  openAddMember: PropTypes.func.isRequired,
  changeUser: PropTypes.func.isRequired,
  idAssignee: PropTypes.number,
}
