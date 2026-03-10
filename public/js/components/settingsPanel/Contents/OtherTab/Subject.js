import React, {useContext, useMemo} from 'react'
import {Select} from '../../../common/Select'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

export const Subject = () => {
  const {SELECT_HEIGHT, subject, setSubject} = useContext(CreateProjectContext)

  const subjectsArray = useMemo(
    () =>
      config.subject_array.map((item) => {
        return {...item, id: item.key, name: item.display}
      }),
    [],
  )

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Subject</h3>
        <p>Select your project's subject.</p>
      </div>
      <div className="options-select-container">
        <Select
          id="project-subject"
          name={'project-subject'}
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          maxHeightDroplist={SELECT_HEIGHT}
          showSearchBar={true}
          options={subjectsArray}
          activeOption={subject}
          checkSpaceToReverse={true}
          onSelect={(option) => setSubject(option)}
        />
      </div>
    </div>
  )
}
