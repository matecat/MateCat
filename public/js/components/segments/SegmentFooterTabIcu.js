import React, {useState, useMemo} from 'react'
import parse from 'format-message-parse'
import formatMessage from 'format-message'
import {
  removeTagsFromText,
  transformTagsToText,
} from './utils/DraftMatecatUtils/tagUtils'
import pluralRules from '../../resources/pluralRules.json'

const SegmentFooterTabIcu = ({segment}) => {
  const [values, setValues] = useState([])
  const [thereisPlural, setThereisPlural] = useState(false)
  const [thereIsSelectOrdinal, setThereIsSelectOrdinal] = useState(false)

  const variableNames = useMemo(() => {
    try {
      const tree = parse(
        transformTagsToText(removeTagsFromText(segment.translation)),
      )
      setThereisPlural(false)
      setThereIsSelectOrdinal(false)
      const vars = new Set()
      const walk = (node) => {
        if (Array.isArray(node)) {
          const [name, type] = node
          if (typeof name === 'string') vars.add(name)
          if (typeof type === 'string' && type === 'plural')
            setThereisPlural(true)
          if (typeof type === 'string' && type === 'selectordinal')
            setThereIsSelectOrdinal(true)
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

  const onChangeValue = (name, val) => setValues((v) => ({...v, [name]: val}))

  let preview = ''

  try {
    preview = formatMessage(
      transformTagsToText(removeTagsFromText(segment.translation)),
      values,
    )
  } catch {
    preview = 'Error in ICU message'
  }
  return (
    <div className="segment-footer-icu-container">
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
        {variableNames.map((name) => (
          <div key={name}>
            <label>
              {name}:&nbsp;
              <input
                value={values[name] || ''}
                onChange={(e) => onChangeValue(name, e.target.value)}
                style={{width: '10rem'}}
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
  )
}
export default SegmentFooterTabIcu
