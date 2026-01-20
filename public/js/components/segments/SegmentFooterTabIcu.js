import React, {useState, useMemo} from 'react'
import parse from 'format-message-parse'
import formatMessage from 'format-message'
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
      return [...v, {name, type, value}]
    })
  }

  let preview = ''

  try {
    let valuesNew = []
    values.forEach(({type, value, name}) => {
      let valueFormatted
      switch (type) {
        case 'number':
          valueFormatted = Number(value)
          break

        case 'date':
          // Converte in oggetto Date
          var date = new Date(value)
          valueFormatted = +date + date.getTimezoneOffset() * 60 * 1000
          break

        case 'time':
          // Valida il formato dell'ora (esempio: "HH:MM")
          valueFormatted = value
          break

        case 'text':
          valueFormatted = value
          break

        default:
          valueFormatted = value
      }
      valuesNew[name] = valueFormatted
    })
    preview = formatMessage(
      transformTagsToText(removeTagsFromText(segment.translation)),
      valuesNew,
    )
  } catch {
    preview = 'Error in ICU message'
  }
  return (
    <div
      className={`tab sub-editor segment-footer-icu-container ${active_class} ${tab_class}`}
    >
      <div>
        <div>
          {analyzeICU.hasPlural && (
            <div>
              <h3>Plural Rules</h3>
              {pluralRules[config.target_code.split('-')[0]]?.cardinal.map(
                ({category, rule, example}) => (
                  <div key={category} style={{marginBottom: '0.5rem'}}>
                    <strong>{category}</strong>: {rule}
                    <br />
                    <em>Example: {example}</em>
                  </div>
                ),
              )}
            </div>
          )}
          {analyzeICU.hasSelectOrdinal && (
            <div>
              <h3>SelectOrdinal Rules</h3>
              {pluralRules[config.target_code.split('-')[0]]?.ordinal.map(
                ({category, rule, example}) => (
                  <div key={category} style={{marginBottom: '0.5rem'}}>
                    <strong>{category}</strong>: {rule}
                    <br />
                    <em>Example: {example}</em>
                  </div>
                ),
              )}
            </div>
          )}
        </div>
        <div>
          <h3>ICU Editor</h3>

          <h4>Variables</h4>
          {variableNames.length === 0 && <em>No variables</em>}
          {variableNames.map(({name, type}) => (
            <div key={name}>
              <label>
                {name}:&nbsp;
                <input
                  value={values.find(({n}) => n === name) || ''}
                  onChange={(e) => onChangeValue(e, name)}
                  style={{width: '10rem'}}
                  type={inputTypes[type]}
                />
              </label>
            </div>
          ))}

          <h4>Preview final string with values:</h4>
          <div
            style={{
              border: '1px solid #ccc',
              padding: '8px',
              minHeight: '2rem',
              background: '#f9f9f9',
            }}
          >
            {preview}
          </div>
        </div>
      </div>
    </div>
  )
}
export default SegmentFooterTabIcu
