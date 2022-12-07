/**
 * React Component .

 */
import React from 'react'
import _ from 'lodash'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import Immutable from 'immutable'
import TagUtils from '../../utils/tagUtils'

class SegmentFooterTabGlossary extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      loading: false,
      openComment: false,
      enableAddButton: false,
      editing: false,
    }
    this.matches = {}
    this.stopLoading = this.stopLoading.bind(this)
  }

  stopLoading(sid) {
    if (sid === this.props.id_segment) {
      this.setState({
        loading: false,
      })
    }
  }

  setTotalMatchesInTab(matches) {
    let totalMatches = _.size(matches)
    if (totalMatches > 0) {
      SegmentActions.setTabIndex(
        this.props.id_segment,
        'glossary',
        totalMatches,
      )
    }
  }

  searchInGlossary(e) {
    if (e.key === 'Enter') {
      e.preventDefault()
      let txt = this.source.textContent
      let target = this.target.textContent
      if (txt.length > 0 && !target) {
        this.setState({
          loading: true,
        })
        SegmentActions.searchGlossary(
          this.props.segment.sid,
          this.props.segment.fid,
          txt,
          false,
        )
      } else if (txt && target) {
        this.setGlossaryItem()
      }
    }
  }

  searchInTarget() {
    let txt = this.target.textContent
    let target = this.source.textContent
    if (txt.length > 0 && !target) {
      this.setState({
        loading: true,
      })
      SegmentActions.searchGlossary(
        this.props.segment.sid,
        this.props.segment.fid,
        txt,
        true,
      )
    } else if (txt && target) {
      this.setGlossaryItem()
    }
  }

  deleteMatch(match, event) {
    event.preventDefault()
    SegmentActions.deleteGlossaryItem(match, this.props.id_segment)
  }

  updateGlossaryItem(match, e) {
    if (e.key === 'Enter') {
      e.preventDefault()
      const newTarget = $(this.matches[match.segment])
        .find('.sugg-target span')
        .text()
      const newComment = match.comment
      //Disable update comment
      // const newComment =
      //   $(this.matches[match.segment]).find('.details .comment').length > 0
      //     ? $(this.matches[match.segment]).find('.details .comment').text()
      //     : $(this.matches[match.segment])
      //         .find('.glossary-add-comment .gl-comment')
      //         .text()

      SegmentActions.updateGlossaryItem(
        match,
        newTarget,
        newComment,
        this.props.id_segment,
      )

      this.setState({
        openComment: false,
        editing: false,
      })
    }
  }

  openAddComment(e) {
    e.preventDefault()
    this.setState({
      openComment: !this.state.openComment,
    })
  }

  openAddCommentExistingMatch(match, e) {
    e.preventDefault()
    $(this.matches[match]).find('.glossary-add-comment .gl-comment').toggle()
  }

  editExistingMatch(match, e) {
    e.preventDefault()
    const {editing} = this.state
    this.setState({editing: !editing})
  }

  onKeyUpSetItem() {
    let source = this.source.textContent
    let target = this.target.textContent
    this.setState({
      enableAddButton: this.checkAddItemButton(source, target),
    })
  }

  onEnterSetItem(e) {
    if (e.key === 'Enter') {
      e.preventDefault()
      this.setGlossaryItem()
    }
  }

  onClickSetItem() {
    this.setGlossaryItem()
  }

  setGlossaryItem() {
    let source = this.source.textContent.trim()
    let target = this.target.textContent.trim()
    if (this.checkAddItemButton(source, target)) {
      let comment = this.comment ? this.comment.textContent : null
      this.setState({
        loading: true,
      })
      SegmentActions.addGlossaryItem(
        source,
        target,
        comment,
        this.props.id_segment,
      )
      this.setState({
        loading: false,
        openComment: false,
        enableAddButton: false,
      })
      this.source.textContent = ''
      this.target.textContent = ''
    } else {
      this.searchInTarget()
    }
  }

  checkAddItemButton(source, target) {
    return !!source && !!target
  }

  copyItemInEditArea(glossaryTranslation) {
    !this.state.editing &&
      SegmentActions.copyGlossaryItemInEditarea(
        glossaryTranslation,
        this.props.segment,
      )
    // GlossaryUtils.copyGlossaryItemInEditareaDraftJs(glossaryTranslation, this.props.segment);
  }
  onPasteEvent(e) {
    // cancel paste
    e.preventDefault()
    // get text representation of clipboard
    var text = (e.originalEvent || e).clipboardData.getData('text/plain')
    // insert text manually
    document.execCommand('insertHTML', false, text)
  }
  renderMatches() {
    let htmlResults = []
    if (this.props.segment.glossary && this.props.segment.glossary.length > 0) {
      this.props.segment.glossary.forEach((match, index) => {
        if (match.segment === '' || match.translation === '') return
        let cb = match.created_by
        let disabled = match.id == '0' ? true : false
        let sourceNoteEmpty =
          _.isUndefined(match.source_note) || match.source_note === ''
        let targetNoteEmpty =
          _.isUndefined(match.target_note) || match.target_note === ''

        if (sourceNoteEmpty && targetNoteEmpty) {
          match.comment = ''
        } else if (!targetNoteEmpty) {
          match.comment = match.target_note
        } else if (!sourceNoteEmpty) {
          match.comment = match.source_note
        }

        let leftTxt = match.segment
        let rightTxt = match.translation
        let commentOriginal = match.comment
        if (commentOriginal) {
          commentOriginal = commentOriginal.replace(/\#\{/gi, '<mark>')
          commentOriginal = commentOriginal.replace(/\}\#/gi, '</mark>')
        }
        // Temporary removed because not working on MM side
        let addCommentHtml = ''
        {
          /*<div className="glossary-add-comment">*/
        }
        {
          /*<a href="#" onClick={this.openAddCommentExistingMatch.bind(this, match.segment)}>Add a Comment</a>*/
        }
        {
          /*<div className="input gl-comment" contentEditable="true" style={{display: 'none'}}*/
        }
        {
          /*onKeyPress={this.updateGlossaryItem.bind(this, match.segment)}/>*/
        }
        {
          /*</div>;*/
        }

        let html = (
          <div
            key={match.segment + '-' + index}
            ref={(matchref) => (this.matches[match.segment] = matchref)}
          >
            <div className="glossary-item">
              <span>{match.segment}</span>
            </div>
            <ul className="graysmall" data-id={match.id}>
              <li className="sugg-source">
                <div
                  id={this.props.id_segment + '-tm-' + match.id + '-edit'}
                  className="switch-editing icon-edit"
                  title="Edit"
                  onClick={this.editExistingMatch.bind(this, match.segment)}
                />
                {disabled ? (
                  ''
                ) : (
                  <span
                    id={this.props.id_segment + '-tm-' + match.id + '-delete'}
                    className="trash"
                    title="delete this row"
                    onClick={this.deleteMatch.bind(this, match)}
                  />
                )}
                <span
                  id={this.props.id_segment + '-tm-' + match.id + '-source'}
                  className="suggestion_source"
                  dangerouslySetInnerHTML={this.allowHTML(
                    TagUtils.decodePlaceholdersToTextSimple(leftTxt, true),
                  )}
                />
              </li>
              <li
                className="b sugg-target"
                onMouseDown={() => this.copyItemInEditArea(rightTxt)}
              >
                <span
                  id={
                    this.props.id_segment + '-tm-' + match.id + '-translation'
                  }
                  className={
                    'translation ' + (this.state.editing ? 'editing' : '')
                  }
                  data-original={TagUtils.decodePlaceholdersToTextSimple(
                    rightTxt,
                    true,
                  )}
                  dangerouslySetInnerHTML={this.allowHTML(
                    TagUtils.decodePlaceholdersToTextSimple(rightTxt, true),
                  )}
                  onKeyPress={this.updateGlossaryItem.bind(this, match)}
                  contentEditable={this.state.editing}
                />
              </li>
              <li className="details">
                {!match.comment || match.comment === '' ? (
                  addCommentHtml
                ) : (
                  <div
                    className={
                      'comment ' + (this.state.editing ? 'editing' : '')
                    }
                    data-original={TagUtils.decodePlaceholdersToTextSimple(
                      commentOriginal,
                      true,
                    )}
                    dangerouslySetInnerHTML={this.allowHTML(
                      TagUtils.decodePlaceholdersToTextSimple(
                        commentOriginal,
                        true,
                      ),
                    )}
                    contentEditable={this.state.editing}
                  />
                )}
                <ul className="graysmall-details">
                  <li>{match.last_update_date}</li>
                  <li className="graydesc">
                    Source:
                    <span className="bold"> {cb}</span>
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        )
        htmlResults.push(html)
      })
    }
    return htmlResults
  }

  componentDidMount() {
    this._isMounted = true
    SegmentStore.addListener(
      SegmentConstants.SET_GLOSSARY_TO_CACHE,
      this.stopLoading,
    )
    // UI.markGlossaryItemsInSource(UI.getSegmentById( this.props.id_segment ), this.props.segment.glossary);
    // setTimeout(()=>this.setTotalMatchesInTab( this.props.segment.glossary), 0 );
  }

  componentWillUnmount() {
    this._isMounted = false
    SegmentStore.removeListener(
      SegmentConstants.SET_GLOSSARY_TO_CACHE,
      this.stopLoading,
    )
  }

  componentDidUpdate(prevProps, prevState) {
    if (
      prevState.openComment !== this.state.openComment &&
      this.state.openComment
    ) {
      this.comment.focus()
    }
  }

  allowHTML(string) {
    return {__html: string}
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      ((!_.isUndefined(nextProps.segment.glossary) ||
        !_.isUndefined(this.props.segment.glossary)) &&
        ((!_.isUndefined(nextProps.segment.glossary) &&
          _.isUndefined(this.props.segment.glossary)) ||
          !Immutable.fromJS(this.props.segment.glossary).equals(
            Immutable.fromJS(nextProps.segment.glossary),
          ))) ||
      this.state.loading !== nextState.loading ||
      this.state.openComment !== nextState.openComment ||
      this.state.editing !== nextState.editing ||
      this.props.active_class !== nextProps.active_class ||
      this.state.enableAddButton !== nextState.enableButton ||
      this.props.tab_class !== nextProps.tab_class
    )
  }

  render() {
    let matches
    if (this.props.segment && this.props.segment.glossary) {
      matches = this.renderMatches()
    }
    let html = ''
    let loading = classnames({
      'gl-search': true,
      loading: this.state.loading,
    })
    if (config.tms_enabled) {
      html = (
        <div className={loading}>
          <div
            ref={(source) => (this.source = source)}
            className="input search-source"
            contentEditable="true"
            onKeyPress={this.searchInGlossary.bind(this)}
            onPaste={this.onPasteEvent.bind(this)}
          />
          <div
            ref={(target) => (this.target = target)}
            className="input search-target"
            contentEditable="true"
            onKeyDown={this.onEnterSetItem.bind(this)}
            onPaste={this.onPasteEvent.bind(this)}
            onKeyUp={this.onKeyUpSetItem.bind(this)}
          />
          {this.state.enableAddButton ? (
            <span
              className="set-glossary"
              onClick={this.onClickSetItem.bind(this)}
            />
          ) : (
            <span className="set-glossary disabled" />
          )}
          <div className="comment">
            <a href="#" onClick={this.openAddComment.bind(this)}>
              (+) Comment
            </a>
            {this.state.openComment ? (
              <div
                ref={(comment) => (this.comment = comment)}
                className="input gl-comment"
                contentEditable="true"
                onKeyDown={this.onEnterSetItem.bind(this)}
              />
            ) : null}
          </div>
          <div className="results">{matches}</div>
        </div>
      )
    } else {
      html = (
        <ul className="graysmall message">
          <li>Glossary is not available when the TM feature is disabled</li>
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
        id={'segment-' + this.props.id_segment + '-' + this.props.tab_class}
      >
        <div className="overflow">{html}</div>
      </div>
    )
  }
}

export default SegmentFooterTabGlossary
