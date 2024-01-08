import React from 'react'

import PropTypes from 'prop-types'

function queriesDidChange(prevQueries, nextQueries) {
  if (nextQueries === prevQueries) return false
  const nextQueriesArr = Object.values(nextQueries)
  const prevQueriesArr = Object.values(prevQueries)
  if (nextQueriesArr.length !== prevQueriesArr.length) return true
  if (nextQueriesArr.some((q, i) => q !== prevQueriesArr[i])) return true
  const prevKeys = Object.keys(prevQueries)
  return Object.keys(nextQueries).some((n, i) => n !== prevKeys[i])
}

function init(queries) {
  const queryKeys = Object.keys(queries)
  /* istanbul ignore next */
  if (typeof window === 'undefined')
    return queryKeys.reduce(
      (curr, key) => {
        curr.matches[key] = false
        curr.mediaQueries[key] = {}
        return curr
      },
      {mediaQueries: {}, matches: {}},
    )

  return queryKeys.reduce(
    (state, name) => {
      const mql = window.matchMedia(queries[name])
      state.mediaQueries[name] = mql
      state.matches[name] = mql.matches
      return state
    },
    {mediaQueries: {}, matches: {}},
  )
}

function reducer(state, action) {
  switch (action.type) {
    case 'updateMatches':
      return {
        matches: Object.keys(state.mediaQueries).reduce((prev, key) => {
          prev[key] = state.mediaQueries[key].matches
          return prev
        }, {}),
        mediaQueries: state.mediaQueries,
      }

    case 'setQueries':
      return init(action.queries)
  }
}

/**
 * A hook that returns a [`MediaQueryMatches`](#mediaquerymatches) object which will
 * tell you if specific media queries matched, all media queries matched, or
 * any media queries matched. Matches in this hook will always return `false` when
 * rendering on the server.
 *
 * @param queryMap The media queries you want to match against e.g. `{screen: "screen", width: "(min-width: 12em)"}`
 */
export function useMediaQueries(queryMap) {
  const prevQueries = React.useRef(queryMap)
  const [state, dispatch] = React.useReducer(reducer, queryMap, init)

  React.useEffect(() => {
    if (queriesDidChange(queryMap, prevQueries.current)) {
      dispatch({type: 'setQueries', queries: queryMap})
      prevQueries.current = queryMap
    }
  }, [queryMap])

  React.useEffect(() => {
    const queries = Object.values(state.mediaQueries)
    const callbacks = queries.map((mq) => {
      const callback = () => dispatch({type: 'updateMatches'})
      if (typeof mq.addListener !== 'undefined') mq.addListener(callback)
      else mq.addEventListener('change', callback)

      return callback
    })

    return () => {
      queries.forEach((mq, i) => {
        if (typeof mq.addListener !== 'undefined')
          mq.removeListener(callbacks[i])
        else mq.removeEventListener('change', callbacks[i])
      })
    }
  }, [state.mediaQueries])

  const {matches} = state
  const matchValues = React.useMemo(() => Object.values(matches), [matches])

  return {
    matches,
    matchesAny: matchValues.some(Boolean),
    matchesAll: matchValues.length > 0 && matchValues.every(Boolean),
  }
}

useMediaQueries.propTypes = {
  queryMap: PropTypes.any,
}

/**
 * A hook that returns `true` if the media query matched and `false` if not. This
 * hook will always return `false` when rendering on the server.
 *
 * @param query The media query you want to match against e.g. `"only screen and (min-width: 12em)"`
 */
export function useMediaQuery(query) {
  return useMediaQueries(getObj(query)).matchesAll
}

useMediaQuery.propTypes = {
  query: PropTypes.any,
}

const cache = {}

function getObj(query) {
  if (cache[query] === void 0) cache[query] = {default: query}
  return cache[query]
}
