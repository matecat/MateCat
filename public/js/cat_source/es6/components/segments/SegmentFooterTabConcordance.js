import React from 'react'
import Cookies from 'js-cookie'
import _ from 'lodash'

import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import Immutable from 'immutable'
import TagUtils from '../../utils/tagUtils'
import CommonUtils from '../../utils/commonUtils'
import OfflineUtils from '../../utils/offlineUtils'
import {getConcordance} from '../../api/getConcordance'
import {SegmentContext} from './SegmentContext'
import {SegmentFooterTabError} from './SegmentFooterTabError'

class SegmentFooterTabConcordance extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    let extended = false
    if (Cookies.get('segment_footer_extendend_concordance')) {
      extended = Cookies.get('segment_footer_extendend_concordance') === 'true'
    }

    this.state = {
      noResults: false,
      numDisplayContributionMatches: 3,
      results: this.props.segment.concordance
        ? this.props.segment.concordance
        : [],
      loading: false,
      source: '',
      target: '',
      extended: extended,
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
          results: [],
        })
      } else {
        this.setState({
          source: data.text,
          target: '',
          results: [],
        })
      }
      setTimeout(() => this.searchSubmit())
    }
  }

  sourceChange(event) {
    const previousResult = this.state.results
    this.setState({
      source: event.target.value,
      target: '',
      results: [],
    })
  }

  targetChange(event) {
    const previousResult = this.state.results
    this.setState({
      source: '',
      target: event.target.value,
      results: [],
    })
  }

  getConcordance(query, type) {
    //type 0 = source, 1 = target
    getConcordance(query, type).catch(() => {
      OfflineUtils.failedConnection(this, 'getConcordance')
    })
    this.setState({
      loading: true,
      results: [],
    })
  }

  renderConcordances(sid, data) {
    if (sid !== this.props.segment.sid) return
    if (data.length) {
      this.setState({
        results: data,
        noResults: false,
        loading: false,
      })
    } else {
      this.setState({
        noResults: true,
        results: [],
        loading: false,
      })
    }
  }

  processResults() {
    let self = this
    let segment_id = this.props.segment.sid
    let array = []

    if (this.state.results.length) {
      let matches = _.orderBy(
        this.state.results,
        ['last_update_date'],
        ['desc'],
      )
      _.each(matches, function (item, index) {
        if (item.segment === '' || item.translation === '') return
        let prime =
          index < self.state.numDisplayContributionMatches ? ' prime' : ''

        let cb = item.created_by

        let leftTxt = item.segment
          .replace(/&/g, '&amp;')
          .replace(/</gi, '&lt;')
          .replace(/>/gi, '&gt;')
        leftTxt = TagUtils.decodePlaceholdersToTextSimple(leftTxt)
        leftTxt = leftTxt.replace(/#\{/gi, '<mark>')
        leftTxt = leftTxt.replace(/\}#/gi, '</mark>')

        let rightTxt = item.translation
          .replace(/&/g, '&amp;')
          .replace(/</gi, '&lt;')
          .replace(/>/gi, '&gt;')
        rightTxt = TagUtils.decodePlaceholdersToTextSimple(rightTxt)
        rightTxt = rightTxt.replace(/#\{/gi, '<mark>')
        rightTxt = rightTxt.replace(/\}#/gi, '</mark>')

        let element = (
          <ul
            key={index}
            className={['graysmall', prime].join(' ')}
            data-item={index + 1}
            data-id={item.id}
          >
            <li className={'sugg-source'}>
              <span
                id={segment_id + '-tm-' + item.id + '-source'}
                className={'suggestion_source'}
                dangerouslySetInnerHTML={self.allowHTML(leftTxt)}
              />
            </li>
            <li className={'b sugg-target'}>
              <span
                id={segment_id + '-tm-' + item.id + '-translation'}
                className={'translation'}
                dangerouslySetInnerHTML={self.allowHTML(rightTxt)}
              />
            </li>
            <ul className={'graysmall-details'}>
              <li>{item.last_update_date}</li>
              <li className={'graydesc'}>
                <span className={'bold'}>
                  {CommonUtils.getLanguageNameFromLocale(item.target)}
                </span>
              </li>
              <li className={'graydesc'}>
                Source: <span className={'bold'}>{cb}</span>
              </li>
            </ul>
          </ul>
        )
        array.push(element)
      })
    }
    return array
  }

  searchSubmit(event) {
    event ? event.preventDefault() : ''
    if (this.state.source.length > 0) {
      this.getConcordance(this.state.source, 0)
    } else if (this.state.target.length > 0) {
      this.getConcordance(this.state.target, 1)
    }
  }

  toggleExtendend() {
    if (this.state.extended) {
      Cookies.set('segment_footer_extendend_concordance', false, {
        expires: 3650,
        secure: true,
      })
    } else {
      Cookies.set('segment_footer_extendend_concordance', true, {
        expires: 3650,
        secure: true,
      })
    }
    this.setState({
      extended: !this.state.extended,
    })
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
      this.state.extended !== nextState.extended ||
      !Immutable.fromJS(this.state.results).equals(
        Immutable.fromJS(nextState.results),
      ) ||
      this.state.loading !== nextState.loading ||
      this.state.noResults !== nextState.noResults ||
      this.state.source !== nextState.source ||
      this.state.target !== nextState.target ||
      this.props.active_class !== nextProps.active_class ||
      this.props.tab_class !== nextProps.tab_class
    )
  }

  render() {
    const {clientConnected} = this.context
    let html = '',
      results = '',
      loadingClass = '',
      extended = '',
      haveResults = '',
      isExtendedClass = this.state.extended ? 'extended' : ''
    extended = (
      <a className={'more'} onClick={this.toggleExtendend.bind(this)}>
        {this.state.extended ? 'Fewer' : 'More'}
      </a>
    )

    if (this.state.results.length > 0) {
      haveResults = 'have-results'
    }
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

    if (this.state.results.length > 0 && !this.state.noResults) {
      results = this.processResults()
    }
    if (this.state.noResults) {
      results = (
        <ul className={'graysmall message prime'}>
          <li>Can&apos;t find any matches. Check the language combination.</li>
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
          this.props.tab_class +
          ' ' +
          isExtendedClass +
          ' ' +
          haveResults
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
              <div className="results">{results}</div>
            </div>
            <br className="clear" />
            {this.state.results.length > 3 ? extended : null}
          </>
        )}
      </div>
    )
  }
}

export default SegmentFooterTabConcordance
