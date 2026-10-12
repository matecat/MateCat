import React, {
  createRef,
  useCallback,
  useContext,
  useMemo,
  useState,
} from 'react'
import {XliffSettingsContext} from './XliffSettingsContext'
import {XliffRulesRow} from './XliffRulesRow'
import {Accordion} from '../../../../common/Accordion/Accordion'
import xliffOptions from '../../defaultTemplates/xliffOptions.json'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'
import IconAdd from '../../../../../../img/icons/IconAdd'
import {isEqual} from 'lodash'
import InfoIcon from '../../../../../../img/icons/InfoIcon'
import Tooltip from '../../../../common/Tooltip'

export const Xliff12 = () => {
  const {currentTemplate, modifyingCurrentTemplate, templates} =
    useContext(XliffSettingsContext)

  const [isExpanded, setIsExpanded] = useState(false)

  const xliff12 = useMemo(
    () =>
      currentTemplate.rules.xliff12.map((item, index) => ({
        ...item,
        id: index,
      })),
    [currentTemplate.rules.xliff12],
  )

  const onChange = useCallback(
    (value) => {
      const {id, ...restProps} = value
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        rules: {
          ...prevTemplate.rules,
          xliff12: prevTemplate.rules.xliff12.map((row, index) =>
            index === id ? restProps : row,
          ),
        },
      }))
    },
    [modifyingCurrentTemplate],
  )

  const getFirstStateOfList = () =>
    xliffOptions.xliff12.states.filter(
      (state) =>
        !xliff12
          .reduce((acc, {states}) => [...acc, ...(states ?? [])], [])
          .some((stateCompare) => state === stateCompare),
    )[0]

  const onAdd = () => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      rules: {
        ...prevTemplate.rules,
        xliff12: [
          ...prevTemplate.rules.xliff12,
          {
            id: prevTemplate.rules.length,
            states: [getFirstStateOfList()],
            analysis: 'new',
          },
        ],
      },
    }))
  }

  const onDelete = useCallback(
    (id) => {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        rules: {
          ...prevTemplate.rules,
          xliff12: prevTemplate.rules.xliff12.filter(
            (row, index) => index !== id,
          ),
        },
      }))
    },
    [modifyingCurrentTemplate],
  )

  const isModified = !isEqual(
    currentTemplate.rules.xliff12,
    templates.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    ).rules.xliff12,
  )

  return (
    <Accordion
      id="xliff12"
      title={
        isModified ? (
          <div>
            <span className="settings-panel-tab-modifyng-icon">●</span>
            XLIFF 1.2
          </div>
        ) : (
          'XLIFF 1.2'
        )
      }
      expanded={isExpanded}
      onShow={() => setIsExpanded((prevState) => !prevState)}
    >
      <div className="xliff-settings-content">
        <div className="xliff-settings-table">
          <span className="xliff-settings-column-name xliff-settings-column-name-state">
            State / State qualifier
            <Tooltip
              content={
                <>
                  Matches segment attributes in the XLIFF file. <b>No state</b>{' '}
                  applies to segments without a state attribute where target
                  content differs from source.
                  <br />
                  It also acts as the fallback rule for any state not explicitly
                  defined above.
                </>
              }
            >
              <Button
                ref={createRef()}
                mode={BUTTON_MODE.GHOST}
                size={BUTTON_SIZE.ICON_SMALL}
              >
                <InfoIcon size={16} />
              </Button>
            </Tooltip>
          </span>
          <span className="xliff-settings-column-name">Analysis behavior</span>
          <span className="xliff-settings-column-name xliff-settings-column-name-editor">
            State in editor
          </span>
          {xliff12.map((row, index) => (
            <XliffRulesRow
              key={index}
              value={row}
              onChange={onChange}
              onDelete={onDelete}
              currentXliffData={currentTemplate.rules.xliff12}
              xliffOptions={xliffOptions.xliff12}
            />
          ))}
        </div>
        {getFirstStateOfList() && (
          <Button
            className="button-add-rule"
            type={BUTTON_TYPE.PRIMARY}
            onClick={onAdd}
          >
            <IconAdd size={22} /> Add rule
          </Button>
        )}
      </div>
    </Accordion>
  )
}
