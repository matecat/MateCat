import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {Button, BUTTON_SIZE} from '../../../common/Button/Button'
import IconClose from '../../../icons/IconClose'
import Tooltip from '../../../common/Tooltip'

export const SeveritiyRow = ({severity}) => {
  const {modifyingCurrentTemplate, templates, currentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const ref = useRef()
  const removeSeverityRef = useRef()

  const [penalty, setPenalty] = useState(severity.penalty)

  useEffect(() => {
    setPenalty(
      typeof severity.penalty === 'number'
        ? Number(severity.penalty)
        : severity.penalty,
    )
  }, [severity.penalty])

  const onChange = ({currentTarget: {value}}) => {
    const isValidInput = typeof value === 'number' || !/[^+0-9.]/g.test(value)
    if (isValidInput) {
      setPenalty(value)
    }
  }
  const selectAll = () => ref.current.select()
  const onBlur = () => {
    const {id, id_category: idCategory} = severity
    modifyingCurrentTemplate((prevTemplate) => {
      const {categories} = prevTemplate

      return {
        ...prevTemplate,
        categories: categories.map((category) => ({
          ...category,
          severities: category.severities.map((severityItem) => {
            if (idCategory === category.id && id === severityItem.id) {
              return {
                ...severityItem,
                penalty:
                  penalty !== '' ? parseFloat(penalty) : severityItem.penalty,
              }
            } else {
              return severityItem
            }
          }),
        })),
      }
    })

    if (penalty === '') setPenalty(severity.penalty)
  }

  const checkIsNotSaved = () => {
    if (!templates?.some(({isTemporary}) => isTemporary)) return false

    const originalCurrentTemplate = templates?.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    )

    const isMatched = originalCurrentTemplate.categories.some(({severities}) =>
      severities.some(({id}) => id === severity.id),
    )

    if (!isMatched) return true

    const originalSeverity = originalCurrentTemplate.categories
      .find(({id}) => id === severity.id_category)
      ?.severities.find(({id}) => id === severity.id)
    const currentSeverity = currentTemplate.categories
      .find(({id}) => id === severity.id_category)
      ?.severities.find(({id}) => id === severity.id)

    const isModified = originalSeverity?.penalty !== currentSeverity.penalty

    return isModified
  }

  const removeSeverity = () => {
    const {id, id_category: idCategory} = severity

    modifyingCurrentTemplate((prevTemplate) => {
      const {categories} = prevTemplate

      return {
        ...prevTemplate,
        categories: categories.map((category) => ({
          ...category,
          ...(idCategory === category.id && {
            severities: category.severities.filter(
              (severityItem) => id !== severityItem.id,
            ),
          }),
        })),
      }
    })
  }

  const isNotSaved = checkIsNotSaved()

  return (
    <div
      className={`cell${isNotSaved ? ' cell-not-saved' : ''}`}
      data-testid={`qf-severity-cell-${severity.id_category}-${severity.id}`}
    >
      <div className="quality-framework-severity-input-container">
        <input
          ref={ref}
          type="text"
          value={penalty}
          onChange={onChange}
          onFocus={selectAll}
          onBlur={onBlur}
        />
        <Tooltip
          stylePointerElement={{position: 'absolute'}}
          content="Remove severity"
        >
          <Button
            ref={removeSeverityRef}
            size={BUTTON_SIZE.ICON_SMALL}
            onClick={removeSeverity}
          >
            <IconClose size={9} />
          </Button>
        </Tooltip>
      </div>
    </div>
  )
}

SeveritiyRow.propTypes = {
  severity: PropTypes.object,
}
