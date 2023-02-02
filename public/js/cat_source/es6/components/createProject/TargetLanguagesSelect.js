import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../common/Select'
import {CreateProjectContext} from './CreateProjectContext'
import ChevronDown from '../../../../../img/icons/ChevronDown'
import {useRef} from 'react'
import TEXT_UTILS from '../../utils/textUtils'

export const TargetLanguagesSelect = ({history = []}) => {
  const {
    SELECT_HEIGHT,
    languages,
    targetLangs,
    setTargetLangs,
    setIsOpenMultiselectLanguages,
  } = useContext(CreateProjectContext)

  const selectedItemRef = useRef()

  const getActiveLabel = () => targetLangs.map(({name}) => name).join(',')
  const openModal = () => setIsOpenMultiselectLanguages(true)

  return (
    <div className="select-with-label__wrapper " id="target-lang" onClick={openModal}>
      <label>To</label>
      <div
        className="select-with-icon__wrapper"
        aria-label={
          TEXT_UTILS.isContentTextEllipsis(selectedItemRef?.current)
            ? getActiveLabel()
            : null
        }
      >
        <span ref={selectedItemRef} className="select select--is-multiple">
          {getActiveLabel()}
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

    // <Select
    //   label="To"
    //   name={'target-lang'}
    //   maxHeightDroplist={SELECT_HEIGHT}
    //   showSearchBar={true}
    //   options={languages}
    //   multipleSelect={'dropdown'}
    //   activeOptions={targetLangs}
    //   checkSpaceToReverse={false}
    //   onToggleOption={(option, onClose) => {
    //     const result = targetLangs.some((item) => item.id === option.id)
    //       ? targetLangs.filter((item) => item.id !== option.id).length
    //         ? targetLangs.filter((item) => item.id !== option.id)
    //         : targetLangs
    //       : [option]
    //     setTargetLangs(result)

    //     if (!targetLangs.some((item) => item.id === option.id)) onClose()
    //   }}
    // >
    //   {({index, onClose}) => ({
    //     ...(index === 0 && {
    //       beforeRow: (
    //         <>
    //           <button
    //             className="button-top-of-list"
    //             onClick={() => {
    //               setIsOpenMultiselectLanguages(true)
    //               onClose()
    //             }}
    //           >
    //             MULTIPLE LANGUAGES
    //             <span className="icon-plus3 icon"></span>
    //           </button>
    //           {history.length > 0 && (
    //             <ul className="history__list">
    //               {history.map((item, index) => (
    //                 <li
    //                   className="dropdown__option"
    //                   onClick={() => {
    //                     setTargetLangs(
    //                       item.map((code) =>
    //                         languages.find((lang) => lang.code === code),
    //                       ),
    //                     )
    //                     onClose()
    //                   }}
    //                   key={index}
    //                 >
    //                   <span>
    //                     {item
    //                       .map(
    //                         (code) =>
    //                           languages.find((lang) => lang.code === code)
    //                             ?.name,
    //                       )
    //                       .join(', ')}
    //                   </span>
    //                 </li>
    //               ))}
    //             </ul>
    //           )}
    //         </>
    //       ),
    //     }),
    //   })}
    // </Select>
  )
}

TargetLanguagesSelect.propTypes = {
  history: PropTypes.arrayOf(PropTypes.array),
}
