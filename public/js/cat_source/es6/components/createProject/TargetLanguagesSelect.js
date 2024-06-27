import React, {useContext} from 'react'
import {CreateProjectContext} from './CreateProjectContext'
import ChevronDown from '../../../../../img/icons/ChevronDown'
import {useRef} from 'react'
import TEXT_UTILS from '../../utils/textUtils'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'

export const TargetLanguagesSelect = () => {
  const {targetLangs, setIsOpenMultiselectLanguages} =
    useContext(CreateProjectContext)
  const {isUserLogged} = useContext(ApplicationWrapperContext)
  const selectedItemRef = useRef()

  const getActiveLabel = () => targetLangs.map(({name}) => name).join(',')
  const openModal = () => isUserLogged && setIsOpenMultiselectLanguages(true)

  return (
    <div
      className="select-with-label__wrapper "
      id="target-lang"
      onClick={openModal}
    >
      <label>To</label>
      <div className="select-with-icon__wrapper" aria-label={getActiveLabel()}>
        <span ref={selectedItemRef} className="select select--is-multiple">
          {targetLangs.length > 1
            ? `${targetLangs.length} languages`
            : targetLangs?.[0]?.name}
        </span>
        <input
          readOnly={true}
          type="text"
          className="input--invisible"
          onFocus={openModal}
        />
        <ChevronDown />
      </div>
    </div>
  )
}
