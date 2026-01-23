import React, {useState, useMemo, useCallback} from 'react'
import parse from 'format-message-parse'
import formatMessage, {date, number, time} from 'format-message'
import {
  removeTagsFromText,
  transformTagsToText,
} from './utils/DraftMatecatUtils/tagUtils'
import pluralRules from '../../resources/pluralRules.json'

const inputTypes = {
  number: 'number',
  duration: 'number',
  spellout: 'number',
  select: 'text',
  plural: 'number',
  selectordinal: 'number',
  date: 'date',
  time: 'time',
  '<>': 'text',
  '': 'text',
}
const SegmentFooterTabIcu = ({segment, active_class, tab_class}) => {
  const [values, setValues] = useState([])

  const variableNames = useMemo(() => {
    try {
      const tree = parse(
        transformTagsToText(removeTagsFromText(segment.translation)),
      )
      const vars = new Set()
      const walk = (node) => {
        if (Array.isArray(node)) {
          const [name, type] = node
          if (typeof name === 'string') vars.add({name, type})
          if (node[2]) {
            Object.values(node[2]).forEach((childNode) => {
              if (Array.isArray(childNode)) {
                childNode.forEach((node) => {
                  if (Array.isArray(node)) {
                    walk(node)
                  }
                })
              }
            })
          }
          /*if (options && typeof options === 'object') {
            Object.values(options).forEach((child) =>
              Array.isArray(child) ? handleChildren(child) : null,
            )
          }*/
        }
      }
      if (Array.isArray(tree)) tree.forEach((n) => walk(n))
      else walk(tree)
      return Array.from(vars)
    } catch {
      return []
    }
  }, [segment.translation])

  const analyzeICU = useMemo(() => {
    const text = transformTagsToText(removeTagsFromText(segment.segment))

    let ast
    try {
      ast = parse(text)
    } catch (err) {
      console.error('Error parsing ICU:', err.message)
      return {hasPlural: false, hasSelectOrdinal: false}
    }

    let hasPlural = false
    let hasSelectOrdinal = false

    function walk(nodes) {
      for (const node of nodes) {
        if (typeof node === 'string') continue

        if (node[1] === 'plural') {
          hasPlural = true
        }
        if (node[1] === 'selectordinal') {
          hasSelectOrdinal = true
        }

        // Se ci sono sotto‐messaggi, analizzali ugualmente
        if (node.value) {
          Object.values(node.value).forEach(walk)
        }
      }
    }

    walk(ast)
    return {hasPlural, hasSelectOrdinal}
  })

  const onChangeValue = (e, name) => {
    const {type, value} = e.target
    setValues((v) => {
      return {...v, [name]: {type, value}}
    })
  }
  const preview = useMemo(() => {
    try {
      let valuesNew = []
      Object.entries(values).forEach(([key, obj]) => {
        let valueFormatted
        const type = obj.type
        const value = obj.value
        switch (type) {
          case 'number':
            valueFormatted = number(value)
            break
          case 'date': {
            const dateFormatted = new Date(value)
            valueFormatted = +dateFormatted
            break
          }
          case 'time': {
            const [hours, minutes] = value.split(':')
            let dateFormatted = new Date()
            dateFormatted.setHours(hours)
            dateFormatted.setMinutes(minutes)
            valueFormatted = +dateFormatted
            break
          }
          case 'text':
            valueFormatted = value
            break

          default:
            valueFormatted = value
        }
        valuesNew[key] = valueFormatted
      })
      formatMessage.setup({
        locale: config.target_code.split('-')[0],
      })
      return formatMessage(
        transformTagsToText(removeTagsFromText(segment.translation)),
        valuesNew,
      )
    } catch {
      return 'Error in ICU message'
    }
  }, [values, segment.translation])

  const renderRule = useCallback(
    ({category, rule, example}) => (
      <div key={category} className="segment-footer-icu-plurals-rule">
        <div className="plural-title">
          <span className="category">{category}</span>
          <span className="rule">{rule}</span>
        </div>
        <div className="plural-example">{example}</div>
      </div>
    ),
    [],
  )

  return (
    <div
      className={`tab sub-editor segment-footer-icu-container ${active_class} ${tab_class}`}
    >
      <div>
        {(analyzeICU.hasPlural || analyzeICU.hasSelectOrdinal) && (
          <div className="segment-footer-icu-plurals">
            {analyzeICU.hasPlural && (
              <div className="segment-footer-icu-plurals-section">
                <div>
                  <h3>Plural Rules</h3>
                </div>
                <div>
                  {pluralRules[config.target_code.split('-')[0]]?.cardinal.map(
                    renderRule,
                  )}
                </div>
              </div>
            )}
            {analyzeICU.hasSelectOrdinal && (
              <div className="segment-footer-icu-plurals-section">
                <div>
                  <h3>SelectOrdinal Rules</h3>
                </div>
                <div>
                  {pluralRules[config.target_code.split('-')[0]]?.ordinal.map(
                    renderRule,
                  )}
                </div>
              </div>
            )}
          </div>
        )}
        <div className="segment-footer-icu-editor">
          <h3>Live preview</h3>
          {variableNames.length === 0 && <h3>No variables</h3>}
          {variableNames.map(({name, type}) => (
            <div key={name}>
              <label>
                <h3>
                  {name}
                  <span>{inputTypes[type]}</span>
                </h3>
                <input
                  value={values[name]?.value || ''}
                  onChange={(e) => onChangeValue(e, name)}
                  style={{width: '10rem'}}
                  type={inputTypes[type]}
                />
              </label>
            </div>
          ))}
          <div className="segment-footer-icu-preview-container">
            <h3>Rendered output</h3>
            <div
              className={`segment-footer-icu-preview ${config.isTargetRTL ? 'rtl' : ''}`}
            >
              {preview}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
export default SegmentFooterTabIcu
