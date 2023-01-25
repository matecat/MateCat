import React from 'react'
import Immutable from 'immutable'
import _ from 'lodash'
import CommonUtils from '../../utils/commonUtils'
import TEXT_UTILS from '../../utils/textUtils'

class SegmentFooterTabMessages extends React.Component {
  constructor(props) {
    super(props)
    //Parameter to exclude notes tha match this regexp, used by plugins to exclude some notes
    this.excludeMatchingNotesRegExp
  }

  getNotes() {
    let notesHtml = []
    let self = this

    const getNoteContentStructure = (note) =>
      TEXT_UTILS.getContentWithAllowedLinkRedirect(note).length > 1
        ? TEXT_UTILS.getContentWithAllowedLinkRedirect(note).map(
            (content, index) =>
              typeof content === 'object' && content.isLink ? (
                <a key={index} href={content.link} target="_blank">
                  {content.link}
                </a>
              ) : (
                content
              ),
          )
        : note

    if (this.props.notes) {
      this.props.notes.forEach(function (item, index) {
        if (item.note && item.note !== '') {
          if (
            self.excludeMatchingNotesRegExp &&
            self.excludeMatchingNotesRegExp.test(item.note)
          ) {
            return
          }
          let note = item.note
          let html = (
            <div className="note" key={'note-' + index}>
              <span className="note-label">Note: </span>
              <span>{getNoteContentStructure(note)}</span>
            </div>
          )
          notesHtml.push(html)
        } else if (
          item.json &&
          typeof item.json === 'object' &&
          Object.keys(item.json).length > 0
        ) {
          Object.keys(item.json).forEach(function (key, index) {
            let html = (
              <div className="note" key={'note-json' + index}>
                <span className="note-label">
                  {key.charAt(0).toUpperCase() + key.slice(1)}:{' '}
                </span>
                <span> {item.json[key]} </span>
              </div>
            )
            notesHtml.push(html)
          })
        } else if (typeof item.json === 'string') {
          let text = item.json
          let html = (
            <div key={'note-json' + index} className="note">
              {text}
            </div>
          )
          notesHtml.push(html)
        }
      })
    }
    if (this.props.context_groups && this.props.context_groups.context_json) {
      this.props.context_groups.context_json.forEach((contextGroup) => {
        if (contextGroup.attr.length > 0 && contextGroup.contexts.length > 0) {
          const contextElems = contextGroup.contexts.map((context) => (
            <div
              className="context-item"
              key={contextGroup.id + context.attr['context-type']}
            >
              <span className="context-item-name">{context.content}</span>
            </div>
          ))
          notesHtml.push(
            <div
              className="context-group"
              key={contextGroup.id + contextGroup.attr.name}
            >
              <span className="context-group-name">
                {contextGroup.attr.name}:{' '}
              </span>
              {contextElems}
            </div>,
          )
        }
      })
    }

    // metadata notes
    if (this.props.metadata?.length > 0) {
      notesHtml.push(this.getMetadataNoteTemplate())
    }
    return notesHtml
  }

  getMetadataNoteTemplate() {
    let metadataNotes = []
    for (const [index, item] of this.props.metadata.entries()) {
      const {meta_key: label, meta_value: body} = item
      metadataNotes.push(
        <div className="note" key={`meta-${index}`}>
          <span className="note-label">{label}: </span>
          <span>{body}</span>
        </div>,
      )
    }
    return (
      <div className="metadata-notes" key="metadata-notes">
        {metadataNotes}
      </div>
    )
  }

  componentDidMount() {}

  componentWillUnmount() {}

  allowHTML(string) {
    return {__html: string}
  }

  shouldComponentUpdate(nextProps) {
    return (
      _.isUndefined(nextProps.notes) ||
      _.isUndefined(this.props.note) ||
      !Immutable.fromJS(this.props.notes).equals(
        Immutable.fromJS(nextProps.notes),
      ) ||
      this.props.loading !== nextProps.loading ||
      this.props.active_class !== nextProps.active_class ||
      this.props.tab_class !== nextProps.tab_class
    )
  }

  render() {
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
        <div className="overflow">
          <div className="segment-notes-container">
            <div className="segment-notes-panel-body">
              <div className="segments-notes-container">{this.getNotes()}</div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}

export default SegmentFooterTabMessages
