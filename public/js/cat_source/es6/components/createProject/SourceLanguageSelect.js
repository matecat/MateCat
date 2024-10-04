import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../common/Select'
import {CreateProjectContext} from './CreateProjectContext'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'

export const SourceLanguageSelect = ({shouldHideLabel = false}) => {
  const {SELECT_HEIGHT, languages, sourceLang, changeSourceLanguage} =
    useContext(CreateProjectContext)
  const {isUserLogged} = useContext(ApplicationWrapperContext)

  return (
    <Select
      {...(!shouldHideLabel && {label: 'From'})}
      id="source-lang"
      name="source-lang"
      maxHeightDroplist={SELECT_HEIGHT}
      showSearchBar={true}
      options={languages}
      activeOption={sourceLang}
      checkSpaceToReverse={false}
      onSelect={(option) => changeSourceLanguage(option)}
      isDisabled={!isUserLogged}
    >
      {({name, code}) => ({
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
  shouldHideLabel: PropTypes.bool,
}
