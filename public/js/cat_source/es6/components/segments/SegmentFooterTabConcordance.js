import React from 'react'

import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import OfflineUtils from '../../utils/offlineUtils'
import {getConcordance} from '../../api/getConcordance'
import {SegmentContext} from './SegmentContext'
import {SegmentFooterTabError} from './SegmentFooterTabError'
import {TabConcordanceResults} from './TabConcordanceResults'

class SegmentFooterTabConcordance extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)

    this.state = {
      numDisplayContributionMatches: 3,
      loading: false,
      source: '',
      target: '',
    }

    this.searchSubmit = this.searchSubmit.bind(this)
    this.findConcordance = this.findConcordance.bind(this)
    this.renderConcordances = this.renderConcordances.bind(this)
  }

  allowHTML(string) {
    return {__html: string}
  }

  findConcordance(sid, data) {
    if (this.props.segment.sid == sid) {
      if (data.inTarget) {
        this.setState({
          source: '',
          target: data.text,
        })
      } else {
        this.setState({
          source: data.text,
          target: '',
        })
      }
      setTimeout(() => this.searchSubmit())
      // reset component results
      this.resultsRef.reset()
    }
  }

  sourceChange(event) {
    this.setState({
      source: event.target.value,
      target: '',
    })

    // reset component results
    this.resultsRef.reset()
  }

  targetChange(event) {
    this.setState({
      source: '',
      target: event.target.value,
    })

    // reset component results
    this.resultsRef.reset()
  }

  getConcordance(query, type) {
    //type 0 = source, 1 = target
    getConcordance(query, type).catch(() => {
      OfflineUtils.failedConnection(this, 'getConcordance')
    })
    this.setState({
      loading: true,
    })

    // reset component results
    this.resultsRef && this.resultsRef.reset()
  }

  renderConcordances(sid, data) {
    if (sid !== this.props.segment.sid) return
    if (data.length) {
      this.setState({
        loading: false,
      })
    } else {
      this.setState({
        loading: false,
      })
    }
  }

  searchSubmit(event) {
    event ? event.preventDefault() : ''
    if (this.state.source.length > 0) {
      this.getConcordance(this.state.source, 0)
    } else if (this.state.target.length > 0) {
      this.getConcordance(this.state.target, 1)
    }
  }

  componentDidMount() {
    SegmentStore.addListener(
      SegmentConstants.FIND_CONCORDANCE,
      this.findConcordance,
    )
    SegmentStore.addListener(
      SegmentConstants.CONCORDANCE_RESULT,
      this.renderConcordances,
    )
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.FIND_CONCORDANCE,
      this.findConcordance,
    )
    SegmentStore.removeListener(
      SegmentConstants.CONCORDANCE_RESULT,
      this.renderConcordances,
    )
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      this.state.loading !== nextState.loading ||
      this.state.source !== nextState.source ||
      this.state.target !== nextState.target ||
      this.props.active_class !== nextProps.active_class ||
      this.props.tab_class !== nextProps.tab_class
    )
  }

  render() {
    const {clientConnected} = this.context
    let html = '',
      loadingClass = ''

    if (this.state.loading) {
      loadingClass = 'loading'
    }
    if (config.tms_enabled) {
      html = (
        <div className={'cc-search ' + loadingClass}>
          <form onSubmit={this.searchSubmit}>
            <div className="input-group">
              <input
                type="text"
                className="input search-source"
                onChange={this.sourceChange.bind(this)}
                value={this.state.source}
              />
            </div>
            <div className="input-group">
              <input
                type="text"
                className="input search-target"
                onChange={this.targetChange.bind(this)}
                value={this.state.target}
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
        key={'container_' + this.props.code}
        className={
          'tab sub-editor ' +
          this.props.active_class +
          ' ' +
          this.props.tab_class
        }
        id={'segment-' + this.props.segment.sid + '-' + this.props.tab_class}
      >
        {' '}
        {!clientConnected ? (
          <SegmentFooterTabError />
        ) : (
          <>
            <div className="overflow">
              {html}
              <TabConcordanceResults
                ref={(resultsRef) => (this.resultsRef = resultsRef)}
                segment={this.props.segment}
                isActive={this.props.active_class === 'open'}
              />
            </div>
          </>
        )}
      </div>
    )
  }
}

export default SegmentFooterTabConcordance
