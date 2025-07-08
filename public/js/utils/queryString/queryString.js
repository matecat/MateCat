import {isUndefined} from 'lodash'

/**
 * Convert object nested properties into arrays
 *
 * input: {
        prop1: {
            prop2: {
                prop3: {
                    value: 'result'
                }
            }
        },
        color: 'green'
    }
    output: [
        ["prop1", "prop2", "prop3", "value", "result"]
        ["color", "green"]
    ]
 *
 * @param {object} obj
 * @returns {Array}
 */
const getArraysOfNestedProps = (obj) => {
  if (typeof obj !== 'object') throw new Error(`Invalid argument: ${obj}`)

  const result = []

  const recursive = (obj, path = []) => {
    const keys = [...Object.keys(obj), ...Object.getOwnPropertySymbols(obj)]
    for (const key of keys) {
      const copyPath = [...path, key]
      const prop = obj[key]
      if (typeof prop === 'object' && prop) {
        recursive(
          !Array.isArray(prop)
            ? prop
            : prop.reduce((acc, cur, index) => {
                return {...acc, [index]: cur}
              }, {}),
          copyPath,
        )
      } else {
        copyPath.push(isUndefined(prop) ? '' : prop)
        result.push(copyPath)
      }
    }
  }

  recursive(obj)
  return result
}

/**
 * Convert array of properties into an object property
 * with square brackets separator
 *
 * input: ["prop1", "prop2", "prop3", "value", "result"]
 * output: {prop1[prop2][prop3][value]: "result"}
 *
 * @param {Array} array
 * @returns {object}
 */
const getIndividualPropFromArray = (array) => {
  const props = [...array]
  const value = props.pop()
  const prop = props.reduce((acc, cur) => `${acc}[${cur}]`)
  return {[prop]: value}
}

/**
 * Return array of nested properties flatten
 *
 * @param {object} obj
 * @returns {Array}
 */
const flattenObjectProps = (obj) => {
  const arraysOfProps = getArraysOfNestedProps(obj)
  return arraysOfProps.map(getIndividualPropFromArray)
}

/**
 * Return object of nested properties flatten
 *
 * @param {object} obj
 * @returns {object}
 */
const flattenObject = (obj) => {
  return flattenObjectProps(obj).reduce((acc, curr) => ({...acc, ...curr}), {})
}

/**
 * Return query string from properties without nested properties
 *
 * input: {
        action: 'login',
        token: 'AC42364'
    }

    output: ?action=login&token=AC42364
 *
 * @param {object} props
 * @returns {string}
 */
const getQueryStringFromProps = (props) => {
  const keys = Object.keys(props)
  return keys
    .reduce(
      (acc, cur) =>
        `${acc}${encodeURIComponent(cur)}=${encodeURIComponent(props[cur])}&`,
      '?',
    )
    .slice(0, -1)
}

/**
 * Return query string from properties nested
 *
 * input: {
        prop1: {
            prop2: {
                prop3: {
                    value: 'result'
                },
                color: yellow
            }
        },
        color: 'green'
    }

    output: ?prop1[prop2][prop3][value]=result&prop1[prop2][color]=yellow&color=green (encoded)
 *
 * @param {object} props
 * @returns {string}
 */
const getQueryStringFromNestedProps = (props) => {
  const flattenProps = flattenObjectProps(props)
  return flattenProps
    .reduce((acc, cur) => {
      const key = Object.keys(cur)[0]
      return `${acc}${encodeURIComponent(key)}=${encodeURIComponent(cur[key])}&`
    }, '?')
    .slice(0, -1)
}

export {
  flattenObjectProps,
  getQueryStringFromProps,
  getQueryStringFromNestedProps,
  flattenObject,
}
