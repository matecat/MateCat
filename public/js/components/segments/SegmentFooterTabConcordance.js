import React, {memo, useContext, useState, useRef, useEffect} from 'react'

import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import OfflineUtils from '../../utils/offlineUtils'
import {getConcordance as getConcordanceApi} from '../../api/getConcordance'
import {SegmentContext} from './SegmentContext'
import {SegmentFooterTabError} from './SegmentFooterTabError'
import {TabConcordanceResults} from './TabConcordanceResults'

const SegmentFooterTabConcordance = memo(
  (props) => {
    const {clientConnected} = useContext(SegmentContext)
    const [loading, setLoading] = useState(false)
    const [source, setSource] = useState('')
    const [target, setTarget] = useState('')

    const resultsRef = useRef(null)

    // Mirrors `this.props`/`this.state` always reflecting the CURRENT values when read live inside
    // a stable, ref-backed callback that was only created once (on mount) — same technique as
    // SegmentsCommentsIcon.js earlier in this wave. Updated on every render, AND patched directly
    // inside findConcordance itself (see below) so a same-tick setTimeout read sees the fresh value
    // without waiting for the next render to flow through.
    const liveRef = useRef({segment: props.segment, source, target})
    liveRef.current = {segment: props.segment, source, target}

    const getConcordanceRef = useRef((query, type) => {
      //type 0 = source, 1 = target
      getConcordanceApi(query, type).catch(() => {
        OfflineUtils.failedConnection()
      })
      setLoading(true)

      // reset component results
      resultsRef.current && resultsRef.current.reset()
    })

    const searchSubmitRef = useRef((event) => {
      event ? event.preventDefault() : ''
      const {source, target} = liveRef.current
      if (source.length > 0) {
        getConcordanceRef.current(source, 0)
      } else if (target.length > 0) {
        getConcordanceRef.current(target, 1)
      }
    })

    const findConcordanceRef = useRef((sid, data) => {
      const {segment} = liveRef.current
      if (segment.sid == sid) {
        if (data.inTarget) {
          setSource('')
          setTarget(data.text)
          liveRef.current = {...liveRef.current, source: '', target: data.text}
        } else {
          setSource(data.text)
          setTarget('')
          liveRef.current = {...liveRef.current, source: data.text, target: ''}
        }
        setTimeout(() => searchSubmitRef.current())
        // reset component results
        resultsRef.current.reset()
      }
    })

    const renderConcordancesRef = useRef((sid, data) => {
      const {segment} = liveRef.current
      if (sid !== segment.sid) return
      if (data.length) {
        setLoading(false)
      } else {
        setLoading(false)
      }
    })

    useEffect(() => {
      const findConcordance = findConcordanceRef.current
      const renderConcordances = renderConcordancesRef.current
      SegmentStore.addListener(
        SegmentConstants.FIND_CONCORDANCE,
        findConcordance,
      )
      SegmentStore.addListener(
        SegmentConstants.CONCORDANCE_RESULT,
        renderConcordances,
      )
      return () => {
        SegmentStore.removeListener(
          SegmentConstants.FIND_CONCORDANCE,
          findConcordance,
        )
        SegmentStore.removeListener(
          SegmentConstants.CONCORDANCE_RESULT,
          renderConcordances,
        )
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    /* eslint-disable-next-line no-unused-vars */
    const allowHTML = (string) => ({__html: string})

    const sourceChange = (event) => {
      setSource(event.target.value)
      setTarget('')

      // reset component results
      resultsRef.current.reset()
    }

    const targetChange = (event) => {
      setSource('')
      setTarget(event.target.value)

      // reset component results
      resultsRef.current.reset()
    }

    const copyText = async (e) => {
      const internalClipboard = document.getSelection()
      if (internalClipboard) {
        e.preventDefault()
        // Get plain text form internalClipboard fragment
        const plainText = internalClipboard
          .toString()
          .replace(
            new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'),
            '',
          )
          .replace(/·/g, ' ')
        try {
          await navigator.clipboard.writeText(plainText)
        } catch {
          // The browser or OS denied clipboard permission — nothing more we can do here.
        }
      }
    }

    let html = '',
      loadingClass = ''

    if (loading) {
      loadingClass = 'loading'
    }
    if (config.tms_enabled) {
      html = (
        <div className={'cc-search ' + loadingClass}>
          <form onSubmit={searchSubmitRef.current}>
            <div className="input-group">
              <input
                type="text"
                className="input search-source"
                onChange={sourceChange}
                value={source}
              />
            </div>
            <div className="input-group">
              <input
                type="text"
                className="input search-target"
                onChange={targetChange}
                value={target}
              />
            </div>
            <input
              type="submit"
              value=""
              style={{
                visibility: 'hidden',
                width: '0',
                padding: '0',
                border: 'none',
              }}
            />
          </form>
        </div>
      )
    } else {
      html = (
        <ul className={'graysmall message prime'}>
          <li>TM Search is not available when the TM feature is disabled</li>
        </ul>
      )
    }

    return (
      <div
        key={'container_' + props.code}
        className={
          'tab sub-editor ' + props.active_class + ' ' + props.tab_class
        }
        id={'segment-' + props.segment.sid + '-' + props.tab_class}
        onCopy={copyText}
        onCut={copyText}
      >
        {' '}
        {!clientConnected ? (
          clientConnected === false && <SegmentFooterTabError />
        ) : (
          <>
            <div className="overflow">
              {html}
              <TabConcordanceResults
                ref={(ref) => (resultsRef.current = ref)}
                segment={props.segment}
                isActive={props.active_class === 'open'}
              />
            </div>
          </>
        )}
      </div>
    )
  },
  (prevProps, nextProps) => {
    // shouldComponentUpdate compared state too, but memo's comparator only receives props —
    // state comparisons don't apply here since a memoized function component's internal useState
    // changes always trigger its own re-render regardless of memo (memo only gates re-renders
    // caused by the PARENT re-rendering with new/same props — it cannot and does not block a
    // component's own internal state updates). So the comparator only needs the PROPS half of the
    // original condition:
    return !(
      prevProps.active_class !== nextProps.active_class ||
      prevProps.tab_class !== nextProps.tab_class
    )
  },
)

SegmentFooterTabConcordance.displayName = 'SegmentFooterTabConcordance'

export default SegmentFooterTabConcordance
