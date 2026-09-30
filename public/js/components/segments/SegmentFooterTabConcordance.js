import React, {useContext, useEffect, useRef, useState} from 'react'

import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import OfflineUtils from '../../utils/offlineUtils'
import {getConcordance} from '../../api/getConcordance'
import {SegmentContext} from './SegmentContext'
import {SegmentFooterTabError} from './SegmentFooterTabError'
import {TabConcordanceResults} from './TabConcordanceResults'

const copyText = async (e) => {
  const internalClipboard = document.getSelection()
  if (internalClipboard) {
    e.preventDefault()
    // Get plain text form internalClipboard fragment
    const plainText = internalClipboard
      .toString()
      .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'), '')
      .replace(/·/g, ' ')
    try {
      await navigator.clipboard.writeText(plainText)
    } catch {
      // The browser or OS denied clipboard permission — nothing more we can do here.
    }
  }
}

const SegmentFooterTabConcordance = ({
  segment,
  code,
  active_class,
  tab_class,
}) => {
  const {clientConnected} = useContext(SegmentContext)

  const [loading, setLoading] = useState(false)
  const [source, setSource] = useState('')
  const [target, setTarget] = useState('')

  const resultsRef = useRef(null)

  //type 0 = source, 1 = target
  const runSearch = (query, type) => {
    getConcordance(query, type).catch(() => {
      OfflineUtils.failedConnection()
    })
    setLoading(true)
    resultsRef.current && resultsRef.current.reset()
  }

  // Built inside the effect so each listener compares against the sid it was
  // registered with rather than the one from the first render.
  useEffect(() => {
    const findConcordance = (sid, data) => {
      if (segment.sid != sid) return
      if (data.inTarget) {
        setSource('')
        setTarget(data.text)
      } else {
        setSource(data.text)
        setTarget('')
      }
      // The class deferred to searchSubmit, which read the state it had just
      // set; here the text is already in hand, so search it directly rather
      // than through state a timeout would read stale.
      setTimeout(() => runSearch(data.text, data.inTarget ? 1 : 0))
      resultsRef.current.reset()
    }

    // Both branches of the class's version set loading false, so the result is
    // simply that the search finished.
    const renderConcordances = (sid) => {
      if (sid !== segment.sid) return
      setLoading(false)
    }

    SegmentStore.addListener(SegmentConstants.FIND_CONCORDANCE, findConcordance)
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
  }, [segment.sid])

  const sourceChange = (event) => {
    setSource(event.target.value)
    setTarget('')
    resultsRef.current.reset()
  }

  const targetChange = (event) => {
    setSource('')
    setTarget(event.target.value)
    resultsRef.current.reset()
  }

  const searchSubmit = (event) => {
    event ? event.preventDefault() : ''
    if (source.length > 0) {
      runSearch(source, 0)
    } else if (target.length > 0) {
      runSearch(target, 1)
    }
  }

  let html = ''
  if (config.tms_enabled) {
    html = (
      <div className={'cc-search ' + (loading ? 'loading' : '')}>
        <form onSubmit={searchSubmit}>
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
      key={'container_' + code}
      className={'tab sub-editor ' + active_class + ' ' + tab_class}
      id={'segment-' + segment.sid + '-' + tab_class}
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
              ref={resultsRef}
              segment={segment}
              isActive={active_class === 'open'}
            />
          </div>
        </>
      )}
    </div>
  )
}

// The class's shouldComponentUpdate also listed its own state, which now
// re-renders on its own; only the prop clauses remain meaningful here.
export default React.memo(
  SegmentFooterTabConcordance,
  (prev, next) =>
    prev.active_class === next.active_class &&
    prev.tab_class === next.tab_class,
)
