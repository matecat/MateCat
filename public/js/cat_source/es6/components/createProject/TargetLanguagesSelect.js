import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../common/Select'
import {CreateProjectContext} from './CreateProjectContext'

export const TargetLanguagesSelect = ({history = []}) => {
  const {
    SELECT_HEIGHT,
    languages,
    targetLangs,
    setTargetLangs,
    setIsOpenMultiselectLanguages,
  } = useContext(CreateProjectContext)

  return (
    <Select
      label="To"
      name={'target-lang'}
      maxHeightDroplist={SELECT_HEIGHT}
      showSearchBar={true}
      options={languages}
      multipleSelect={'dropdown'}
      activeOptions={targetLangs}
      checkSpaceToReverse={false}
      onToggleOption={(option) => {
        setTargetLangs((prevState) =>
          prevState.some((item) => item.id === option.id)
            ? prevState.filter((item) => item.id !== option.id).length
              ? prevState.filter((item) => item.id !== option.id)
              : prevState
            : [...prevState, option],
        )
      }}
    >
      {({index, onClose}) => ({
        ...(index === 0 && {
          beforeRow: (
            <>
              <button
                className="button-top-of-list"
                onClick={() => {
                  setIsOpenMultiselectLanguages(true)
                  onClose()
                }}
              >
                MULTIPLE LANGUAGES
                <span className="icon-plus3 icon"></span>
              </button>
              {history.length > 0 && (
                <ul className="history__list">
                  {history.map((item, index) => (
                    <li
                      className="dropdown__option"
                      onClick={() => {
                        setTargetLangs(
                          item.map((code) =>
                            languages.find((lang) => lang.code === code),
                          ),
                        )
                        onClose()
                      }}
                      key={index}
                    >
                      <span>
                        {item
                          .map(
                            (code) =>
                              languages.find((lang) => lang.code === code)
                                ?.name,
                          )
                          .join(', ')}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </>
          ),
        }),
      })}
    </Select>
  )
}

TargetLanguagesSelect.propTypes = {
  history: PropTypes.arrayOf(PropTypes.array),
}
