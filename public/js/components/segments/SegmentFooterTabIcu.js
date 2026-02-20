import React, {useState, useMemo, useCallback} from 'react'
import parse from 'format-message-parse'
import formatMessage, {date, number, time} from 'format-message'
import {
  removeTagsFromText,
  transformTagsToText,
} from './utils/DraftMatecatUtils/tagUtils'
import pluralRules from '../../resources/pluralRules.json'
import textUtils from '../../utils/textUtils'

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
const inputDefaultValue = {
  number: '1',
  duration: '1',
  spellout: '1',
  select: '',
  plural: '1',
  selectordinal: 1,
  date: new Date(),
  time: +new Date(),
  '<>': '',
  '': '',
}
const SegmentFooterTabIcu = ({segment, active_class, tab_class}) => {
  const [values, setValues] = useState([])

  const variableNames = useMemo(() => {
    try {
      const tree = parse(
        textUtils.removeWhitespacePlaceholders(
          transformTagsToText(removeTagsFromText(segment.translation)),
        ),
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
      vars.forEach(({name, type}) => {
        setValues((v) => {
          return {
            ...v,
            [name]: {
              type,
              value: values[name]
                ? values[name].value
                : inputDefaultValue[type],
            },
          }
        })
      })
      return Array.from(vars)
    } catch {
      return []
    }
  }, [segment.translation])

  const analyzeICU = useMemo(() => {
    const text = textUtils.removeWhitespacePlaceholders(
      transformTagsToText(removeTagsFromText(segment.translation)),
    )

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
        if (node[2] && typeof node[2] === 'object') {
          Object.values(node[2]).forEach(walk)
        }
        if (node[3] && typeof node[3] === 'object') {
          Object.values(node[2]).forEach(walk)
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
          // case 'number':
          //   valueFormatted = number(value)
          //   break
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
      console.log(
        'message',
        formatMessage(
          textUtils.removeWhitespacePlaceholders(
            transformTagsToText(removeTagsFromText(segment.translation)),
          ),
          valuesNew,
        ),
        valuesNew,
      )
      return formatMessage(
        textUtils.removeWhitespacePlaceholders(
          transformTagsToText(removeTagsFromText(segment.translation)),
        ),
        valuesNew,
      )
    } catch {
      return 'Invalid ICU string, fix it to enable live preview'
    }
  }, [values, segment.translation])

  const renderRule = useCallback(
    ({category, rule, example}) => (
      <div key={category} className="segment-footer-icu-plurals-rule">
        <div className="plural-title">
          <span className="category">{category}</span>
          {/*<span className="rule">{rule}</span>*/}
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
          <h3>Test values</h3>
          {variableNames.length === 0 && <h3>No variables</h3>}
          <div className="segment-footer-icu-inputs">
            {variableNames.map(({name, type}) => (
              <div key={name}>
                <label>
                  <div>
                    {name}
                    <span>({inputTypes[type]})</span>
                  </div>
                  <input
                    value={values[name]?.value}
                    onChange={(e) => onChangeValue(e, name)}
                    style={{width: '10rem'}}
                    type={inputTypes[type]}
                  />
                </label>
              </div>
            ))}
          </div>
          <div className="segment-footer-icu-preview-container">
            <h3>Live preview</h3>
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
