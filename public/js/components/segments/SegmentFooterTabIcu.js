import React, {useState, useMemo} from 'react'
import parse from 'format-message-parse'
import formatMessage from 'format-message'
import {
  removeTagsFromText,
  transformTagsToText,
} from './utils/DraftMatecatUtils/tagUtils'

const SegmentFooterTabIcu = ({segment}) => {
  const [values, setValues] = useState([])

  const variableNames = useMemo(() => {
    try {
      const tree = parse(
        transformTagsToText(removeTagsFromText(segment.translation)),
      )
      const vars = new Set()
      const walk = (node) => {
        if (Array.isArray(node)) {
          const [name] = node
          if (typeof name === 'string') vars.add(name)
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
    <div style={{fontFamily: 'sans-serif'}}>
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
  )
}
export default SegmentFooterTabIcu
