import React from 'react'
import _ from 'lodash'
import Immutable from 'immutable'

import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import TranslationMatches from './utils/translationMatches'
import TagUtils from '../../utils/tagUtils'
import TextUtils from '../../utils/textUtils'
import SegmentActions from '../../actions/SegmentActions'

class SegmentFooterTabMatches extends React.Component {
  constructor(props) {
    super(props)
    this.suggestionShortcutLabel = 'CTRL+'
    this.processContributions = this.processContributions.bind(this)
    this.chooseSuggestion = this.chooseSuggestion.bind(this)
    SegmentActions.getContributions(
      this.props.segment.sid,
      this.props.fid,
      this.props.segment.segment,
    )
  }

  processContributions(matches) {
    var self = this
    var matchesProcessed = []
    // SegmentActions.createFooter(this.props.id_segment);
    $.each(matches, function () {
      if (
        _.isUndefined(this.segment) ||
        this.segment === '' ||
        this.translation === ''
      )
        return true
      var item = {}
      item.id = this.id
      item.disabled = this.id == '0' ? true : false
      item.cb = this.created_by
      item.segment = this.segment
      item.translation = this.translation
      if (
        'sentence_confidence' in this &&
        this.sentence_confidence !== '' &&
        this.sentence_confidence !== 0 &&
        this.sentence_confidence !== '0' &&
        this.sentence_confidence !== null &&
        this.sentence_confidence !== false &&
        typeof this.sentence_confidence !== 'undefined'
      ) {
        item.suggestion_info =
          'Quality: <b>' + this.sentence_confidence + '</b>'
      } else if (this.match != 'MT') {
        item.suggestion_info = this.last_update_date
      } else {
        item.suggestion_info = ''
      }

      item.percentClass = TranslationMatches.getPercentuageClass(this.match)
      item.percentText = this.match

      // Attention Bug: We are mixing the view mode and the raw data mode.
      // before doing a enhanced  view you will need to add a data-original tag
      //
      item.suggestionDecodedHtml = TagUtils.matchTag(
        TagUtils.decodeHtmlInTag(
          TagUtils.decodePlaceholdersToTextSimple(this.segment),
          config.isSourceRTL,
        ),
      )
      item.translationDecodedHtml = TagUtils.matchTag(
        TagUtils.decodeHtmlInTag(
          TagUtils.decodePlaceholdersToTextSimple(this.translation),
          config.isTargetRTL,
        ),
      )
      item.sourceDiff = item.suggestionDecodedHtml

      if (
        this.match !== 'MT' &&
        parseInt(this.match) > 74 &&
        parseInt(this.match) < 100
      ) {
        item.sourceDiff = TextUtils.getDiffHtml(
          this.segment,
          self.props.segment.segment,
        )
        item.sourceDiff = item.sourceDiff.replace(/&amp;/g, '&')
        item.sourceDiff = TagUtils.matchTag(
          TagUtils.decodeHtmlInTag(
            TagUtils.decodePlaceholdersToTextSimple(item.sourceDiff),
          ),
        )
      }

      if (!_.isUndefined(this.tm_properties)) {
        item.tm_properties = this.tm_properties
      }
      let matchToInsert = self.processMatchCallback(item)
      if (matchToInsert) {
        matchesProcessed.push(item)
      }
    })
    return matchesProcessed
  }

  /**
   * Used by the plugins to override matches
   * @param item
   * @returns {*}
   */
  processMatchCallback(item) {
    return item
  }

  chooseSuggestion(sid, index) {
    if (this.props.id_segment === sid) {
      this.suggestionDblClick(this.props.segment.contributions, index)
    }
  }

  suggestionDblClick(match, index) {
    setTimeout(() => {
      SegmentActions.setFocusOnEditArea()
      SegmentActions.disableTPOnSegment(this.props.segment)
      SegmentActions.setChoosenSuggestion(
        this.props.segment.original_sid,
        index,
      )
      TranslationMatches.copySuggestionInEditarea(this.props.segment, index)
    }, 200)
  }

  deleteSuggestion(match) {
    var source = TextUtils.htmlDecode(match.segment)
    var target = TextUtils.htmlDecode(match.translation)
    target = TextUtils.view2rawxliff(target)
    source = TextUtils.view2rawxliff(source)
    SegmentActions.deleteContribution(
      source,
      target,
      match.id,
      this.props.segment.original_sid,
    )
  }

  getMatchInfo(match) {
    return (
      <ul className="graysmall-details">
        <li className={'percent ' + match.percentClass}>{match.percentText}</li>
        <li>{match.suggestion_info}</li>
        <li className="graydesc">
          Source:
          <span className="bold" style={{fontSize: '14px'}}>
            {' '}
            {match.cb}
          </span>
        </li>
        {this.getMatchInfoMetadata(match)}
      </ul>
    )
  }

  /**
   * Get others match info metadata, function overrided inside plugin
   *
   * @param {object} match
   * @returns {object}
   */
  getMatchInfoMetadata() {
    return ''
  }

  componentDidMount() {
    this._isMounted = true
    SegmentStore.addListener(
      SegmentConstants.CHOOSE_CONTRIBUTION,
      this.chooseSuggestion,
    )
  }

  componentWillUnmount() {
    this._isMounted = false
    SegmentStore.removeListener(
      SegmentConstants.CHOOSE_CONTRIBUTION,
      this.chooseSuggestion,
    )
  }

  /**
   * Do not delete, overwritten by plugin
   */
  componentDidUpdate(prevProps) {
    if (!prevProps.segment.unlocked && this.props.segment.unlocked) {
      SegmentActions.getContribution(this.props.segment.sid)
    }
  }

  shouldComponentUpdate(nextProps) {
    return (
      ((!_.isUndefined(nextProps.segment.contributions) ||
        !_.isUndefined(this.props.segment.contributions)) &&
        ((!_.isUndefined(nextProps.segment.contributions) &&
          _.isUndefined(this.props.segment.contributions)) ||
          !Immutable.fromJS(this.props.segment.contributions).equals(
            Immutable.fromJS(nextProps.segment.contributions),
          ))) ||
      this.props.active_class !== nextProps.active_class ||
      this.props.tab_class !== nextProps.tab_class ||
      this.props.segment.unlocked !== nextProps.segment.unlocked
    )
  }

  allowHTML(string) {
    return {__html: string}
  }

  render() {
    let matchesHtml = []
    let self = this
    if (
      this.props.segment.contributions &&
      this.props.segment.contributions.matches &&
      this.props.segment.contributions.matches.length > 0
    ) {
      let tpmMatches = this.processContributions(
        this.props.segment.contributions.matches,
      )
      tpmMatches.forEach(function (match, index) {
        var trashIcon = match.disabled ? (
          ''
        ) : (
          <span
            id={self.props.id_segment + '-tm-' + match.id + '-delete'}
            className="trash"
            title="delete this row"
            onClick={self.deleteSuggestion.bind(self, match, index)}
          />
        )
        var item = (
          <ul
            key={match.id}
            className="suggestion-item graysmall"
            data-item={index + 1}
            data-id={match.id}
            data-original={match.segment}
            onDoubleClick={self.suggestionDblClick.bind(self, match, index + 1)}
          >
            <li className="sugg-source">
              <span
                id={self.props.id_segment + '-tm-' + match.id + '-source'}
                className="suggestion_source"
                dangerouslySetInnerHTML={self.allowHTML(match.sourceDiff)}
              ></span>
            </li>
            <li className="b sugg-target">
              <span className="graysmall-message">
                {' '}
                {self.suggestionShortcutLabel + (index + 1)}
              </span>
              <span
                id={self.props.id_segment + '-tm-' + match.id + '-translation'}
                className="translation"
                dangerouslySetInnerHTML={self.allowHTML(
                  match.translationDecodedHtml,
                )}
              ></span>
              {trashIcon}
            </li>
            {self.getMatchInfo(match)}
          </ul>
        )
        matchesHtml.push(item)
      })
    } else if (
      this.props.segment.contributions &&
      this.props.segment.contributions.matches &&
      this.props.segment.contributions.matches.length === 0
    ) {
      if (config.mt_enabled) {
        matchesHtml.push(
          <ul key={0} className="graysmall message">
            <li>
              No matches could be found for this segment. Please, contact{' '}
              <a href="mailto:support@matecat.com">support@matecat.com</a> if
              you think this is an error.
            </li>
          </ul>,
        )
      } else {
        matchesHtml.push(
          <ul key={0} className="graysmall message">
            <li>No match found for this segment</li>
          </ul>,
        )
      }
    }

    let errors = []
    if (
      this.props.segment.contributions &&
      this.props.segment.contributions.error &&
      this.props.segment.contributions.errors.length > 0
    ) {
      this.props.segment.contributions.errors.forEach((error) => {
        let toAdd = false,
          messageClass,
          imgClass,
          messageTypeText

        switch (error.code) {
          case '-2001':
            toAdd = true
            messageClass = 'error'
            imgClass = 'error-img'
            messageTypeText = 'Error: '
            break
          case '-2002':
            toAdd = true
            messageClass = 'warning'
            imgClass = 'warning-img'
            messageTypeText = 'Warning: '
            break
        }
        if (toAdd) {
          let item = (
            <ul className="engine-error-item graysmall">
              <li className="engine-error">
                <div className={imgClass} />
                <span className={'engine-error-message ' + messageClass}>
                  {messageTypeText + ' ' + error.message}
                </span>
              </li>
            </ul>
          )

          errors.push(item)
        }
      })
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
        id={'segment-' + this.props.id_segment + '-' + this.props.tab_class}
      >
        <div className="overflow">
          {!_.isUndefined(matchesHtml) && matchesHtml.length > 0 ? (
            matchesHtml
          ) : (
            <span className="loader loader_on" />
          )}
        </div>
        <div className="engine-errors">{errors}</div>
      </div>
    )
  }
}

export default SegmentFooterTabMatches
