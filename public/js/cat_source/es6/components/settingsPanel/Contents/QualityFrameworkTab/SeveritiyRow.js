import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const SeveritiyRow = ({severity}) => {
  const {modifyingCurrentTemplate} = useContext(QualityFrameworkTabContext)

  const ref = useRef()

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
                penalty: parseFloat(penalty),
              }
            } else {
              return severityItem
            }
          }),
        })),
      }
    })
  }

  return (
    <div className="cell">
      <input
        ref={ref}
        className="quality-framework-input"
        type="text"
        value={penalty}
        onChange={onChange}
        onFocus={selectAll}
        onBlur={onBlur}
      />
    </div>
  )
}

SeveritiyRow.propTypes = {
  severity: PropTypes.object,
}
