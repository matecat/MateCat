import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../common/Select'
import {CreateProjectContext} from './CreateProjectContext'

export const SourceLanguageSelect = ({history = []}) => {
  const {SELECT_HEIGHT, languages, sourceLang, changeSourceLanguage} =
    useContext(CreateProjectContext)

  return (
    <Select
      label="From"
      id="source-lang"
      name={'source-lang'}
      maxHeightDroplist={SELECT_HEIGHT}
      showSearchBar={true}
      options={languages}
      activeOption={sourceLang}
      checkSpaceToReverse={false}
      onSelect={(option) => changeSourceLanguage(option)}
    >
      {({index, name, code, onClose}) => ({
        ...(index === 0 && {
          beforeRow: (
            <ul className="history__list">
              {history.map((item, index) => (
                <li
                  className="dropdown__option"
                  onClick={() => {
                    changeSourceLanguage(
                      languages.find((lang) => lang.code === item),
                    )
                    onClose()
                  }}
                  key={index}
                >
                  <span>
                    {languages.find((lang) => lang.code === item)?.name}
                  </span>
                </li>
              ))}
            </ul>
          ),
        }),
        row: (
          <div className="language-dropdown-item-container">
            <div className="code-badge">
              <span>{code}</span>
            </div>

            <span>{name}</span>
          </div>
        ),
      })}
    </Select>
  )
}

SourceLanguageSelect.propTypes = {
  history: PropTypes.arrayOf(PropTypes.string),
}
