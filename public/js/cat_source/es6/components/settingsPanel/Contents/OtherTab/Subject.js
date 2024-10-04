import React, {useContext} from 'react'
import {Select} from '../../../common/Select'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

const subjectsArray = config.subject_array.map((item) => {
  return {...item, id: item.key, name: item.display}
})

export const Subject = () => {
  const {SELECT_HEIGHT} = useContext(CreateProjectContext)
  const {subject, setSubject} = useContext(CreateProjectContext)

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Subject</h3>Select subject.
      </div>
      <div className="options-select-container">
        <Select
          label="Select subject"
          id="project-subject"
          name={'project-subject'}
          maxHeightDroplist={SELECT_HEIGHT}
          showSearchBar={true}
          options={subjectsArray}
          activeOption={subject}
          checkSpaceToReverse={false}
          onSelect={(option) => setSubject(option)}
        />
      </div>
    </div>
  )
}
