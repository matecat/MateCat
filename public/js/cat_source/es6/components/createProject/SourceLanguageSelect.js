import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../common/Select'
import {CreateProjectContext} from './CreateProjectContext'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'

export const SourceLanguageSelect = ({
  isRenderedInsideTab = false,
  dropdownClassName,
}) => {
  const {
    SELECT_HEIGHT,
    languages,
    sourceLang,
    changeSourceLanguage,
    projectTemplates,
  } = useContext(CreateProjectContext)
  const {isUserLogged} = useContext(ApplicationWrapperContext)

  return (
    <Select
      {...(!isRenderedInsideTab
        ? {label: 'From'}
        : {isPortalDropdown: true, dropdownClassName})}
      id="source-lang"
      name="source-lang"
      maxHeightDroplist={SELECT_HEIGHT}
      showSearchBar={true}
      options={languages}
      activeOption={Object.keys(sourceLang).length ? sourceLang : undefined}
      checkSpaceToReverse={isRenderedInsideTab}
      onSelect={(option) => changeSourceLanguage(option)}
      isDisabled={!isUserLogged || !projectTemplates.length}
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
  isRenderedInsideTab: PropTypes.bool,
  dropdownClassName: PropTypes.string,
}
