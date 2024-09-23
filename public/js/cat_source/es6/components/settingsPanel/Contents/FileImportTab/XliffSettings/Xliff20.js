import React, {useCallback, useContext, useMemo, useState} from 'react'
import {XliffSettingsContext} from './XliffSettings'
import {XliffRulesRow} from './XliffRulesRow'
import {Accordion} from '../../../../common/Accordion/Accordion'
import Switch from '../../../../common/Switch'
import xliffOptions from '../../defaultTemplates/xliffOptions.json'
import {
  Button,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'
import IconAdd from '../../../../icons/IconAdd'

export const Xliff20 = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(XliffSettingsContext)

  const [isUseCustomRules, setIsUseCustomRules] = useState(false)

  const xliff20 = useMemo(
    () =>
      currentTemplate.rules.xliff20.map((item, index) => ({
        ...item,
        id: index,
      })),
    [currentTemplate.rules.xliff20],
  )

  const onChange = useCallback(
    (value) => {
      const {id, ...restProps} = value
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        rules: {
          ...prevTemplate.rules,
          xliff20: prevTemplate.rules.xliff20.map((row, index) =>
            index === id ? restProps : row,
          ),
        },
      }))
    },
    [modifyingCurrentTemplate],
  )

  const getFirstStateOfList = () =>
    xliffOptions.xliff20.states.filter(
      (state) =>
        !xliff20
          .reduce((acc, {states}) => [...acc, ...(states ?? [])], [])
          .some((stateCompare) => state === stateCompare),
    )[0]

  const onAdd = () => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      rules: {
        ...prevTemplate.rules,
        xliff20: [
          ...prevTemplate.rules.xliff20,
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
          xliff20: prevTemplate.rules.xliff20.filter(
            (row, index) => index !== id,
          ),
        },
      }))
    },
    [modifyingCurrentTemplate],
  )

  const renderSwitch = (
    <div className="use-custom-rules-switch">
      <Switch
        active={isUseCustomRules}
        onChange={(active) => setIsUseCustomRules(active)}
        activeText={''}
        inactiveText={''}
      />
      Use custom rules
    </div>
  )

  return (
    <div className="xliff-settings-container">
      <h2>Xliff 2.0</h2>
      <Accordion id="xliff20" title={renderSwitch} expanded={isUseCustomRules}>
        <div className="xliff-settings-content">
          <div className="xliff-settings-table">
            <span className="xliff-settings-column-name xliff-settings-column-name-state">
              State / State qualifier
            </span>
            <span className="xliff-settings-column-name">Analysis</span>
            <span className="xliff-settings-column-name xliff-settings-column-name-editor">
              Editor
            </span>
            {xliff20.map((row, index) => (
              <XliffRulesRow
                key={index}
                value={row}
                onChange={onChange}
                onDelete={onDelete}
                currentXliffData={currentTemplate.rules.xliff20}
                xliffOptions={xliffOptions.xliff20}
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
    </div>
  )
}
