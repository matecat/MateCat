import React, {useCallback, useContext, useMemo, useState} from 'react'
import {XliffSettingsContext} from './XliffSettings'
import {XliffRulesRow} from './XliffRulesRow'
import {Accordion} from '../../../../common/Accordion/Accordion'
import xliffOptions from '../../defaultTemplates/xliffOptions.json'
import {
  Button,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'
import IconAdd from '../../../../icons/IconAdd'
import {isEqual} from 'lodash'

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
      title={<span className={isModified ? 'unsaved' : ''}>XLIFF 1.2</span>}
      expanded={isExpanded}
      onShow={() => setIsExpanded((prevState) => !prevState)}
    >
      <div className="xliff-settings-content">
        <div className="xliff-settings-table">
          <span className="xliff-settings-column-name xliff-settings-column-name-state">
            State / State qualifier
          </span>
          <span className="xliff-settings-column-name">Analysis</span>
          <span className="xliff-settings-column-name xliff-settings-column-name-editor">
            Editor
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
            size={BUTTON_SIZE.MEDIUM}
            onClick={onAdd}
          >
            <IconAdd size={22} /> Add rule
          </Button>
        )}
      </div>
    </Accordion>
  )
}
