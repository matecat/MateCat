import React, {useCallback, useContext, useMemo, useRef} from 'react'
import {Select} from '../../../common/Select'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'

export const taggingTypes = [
  {id: 'markup', name: 'Markup', code: '<text>', default: true},
  {id: 'twig', name: 'Twig', code: '{{text}},{%text%}', default: true},
  {id: 'ruby_on_rails', name: 'Ruby on Rails', code: '%{text}', default: true},
  {id: 'double_snail', name: 'Double snails', code: '@@text@@', default: true},
  {
    id: 'double_square',
    name: 'Double square brackets',
    code: '[[text]]',
    default: true,
  },
  {
    id: 'dollar_curly',
    name: 'Dollar curly brackets',
    code: '${text}',
    default: true,
  },
  {
    id: 'single_curly',
    name: 'Single curly brackets',
    code: '{text}',
    default: false,
  },
  {
    id: 'objective_c_ns',
    name: 'Objective-C NSString',
    code: '%@,%1$@',
    default: true,
  },
  {
    id: 'double_percent',
    name: 'Double percentage signs',
    code: '%%text%%',
    default: true,
  },
  {
    id: 'square_sprintf',
    name: 'Square bracket Sprintf',
    code: '<a target="_blank" href="https://guides.matecat.com/settings#square-bracket-sprintf">See guides page</a>',
    html: true,
    default: true,
  },
  {
    id: 'sprintf',
    name: 'Sprintf',
    code: '<a target="_blank" href="https://guides.matecat.com/settings#sprintf">See guides page</a>',
    html: true,
    default: true,
  },
]

export const Tagging = () => {
  const {SELECT_HEIGHT} = useContext(CreateProjectContext)
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const previousSubfilteringHandlers = useRef()

  const setTagging = useCallback(
    ({options}) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        subfiltering_handlers: options,
      })),
    [modifyingCurrentTemplate],
  )

  const activeOptions = useMemo(() => {
    const tagging = currentProjectTemplate?.subfilteringHandlers
    if (tagging?.length === 0)
      return taggingTypes.filter((type) => type.default)
    else if (tagging?.length > 0) {
      return tagging.map((item) =>
        taggingTypes.find((type) => type.id === item),
      )
    } else {
      return []
    }
  }, [currentProjectTemplate?.subfilteringHandlers])

  const toggleOption = (option) => {
    const optionsIds = activeOptions.map((option) => option.id)
    if (optionsIds.includes(option.id)) {
      optionsIds.splice(optionsIds.indexOf(option.id), 1)
    } else {
      optionsIds.push(option.id)
    }
    const defaultTaggingIds = taggingTypes
      .filter((type) => type.default)
      .map((type) => type.id)

    if (optionsIds.length === 0) {
      setTagging({options: null})
    } else if (
      defaultTaggingIds.every((id) => optionsIds.includes(id)) &&
      optionsIds.length === defaultTaggingIds.length
    ) {
      setTagging({options: []})
    } else {
      setTagging({options: optionsIds})
    }
  }

  const onClose = useCallback(() => {
    const shouldUpdateRender =
      currentProjectTemplate?.subfilteringHandlers?.length !==
        previousSubfilteringHandlers.current?.length ||
      !currentProjectTemplate?.subfilteringHandlers?.every((value) =>
        previousSubfilteringHandlers.current?.some(
          (valueB) => value === valueB,
        ),
      )
    console.log(
      'shouldUpdateRender',
      shouldUpdateRender,
      currentProjectTemplate?.subfilteringHandlers,
      previousSubfilteringHandlers.current,
    )
    if (config.is_cattool && shouldUpdateRender) {
      SegmentActions.removeAllSegments()
      CatToolActions.onRender({segmentToOpen: config.last_opened_segment})
    }

    if (config.is_cattool)
      previousSubfilteringHandlers.current =
        currentProjectTemplate?.subfilteringHandlers
  }, [currentProjectTemplate?.subfilteringHandlers])

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Tagged syntaxes</h3>
        <p>
          Choose which syntaxes to detect and lock in tags during translation.
        </p>
      </div>
      <div className="options-select-container">
        <Select
          id="project-tagging"
          name={'project-tagging'}
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          maxHeightDroplist={SELECT_HEIGHT}
          showSearchBar={true}
          options={taggingTypes}
          activeOptions={activeOptions}
          checkSpaceToReverse={true}
          onToggleOption={toggleOption}
          multipleSelect={'dropdown'}
          onCloseSelect={() => onClose()}
        >
          {({name, code, html}) => ({
            row: (
              <>
                <span>{name}</span>
                {html ? (
                  <div
                    onClick={(e) => e.stopPropagation()}
                    dangerouslySetInnerHTML={{__html: code}}
                  />
                ) : (
                  <div className="codes-container">
                    {code.split(',').map((code) => (
                      <div key={code} className="code-badge">
                        {code}
                      </div>
                    ))}
                  </div>
                )}
              </>
            ),
          })}
        </Select>
      </div>
    </div>
  )
}
