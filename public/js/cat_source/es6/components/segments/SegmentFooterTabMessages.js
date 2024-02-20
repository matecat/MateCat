import React from 'react'
import Immutable from 'immutable'
import {isUndefined} from 'lodash'
import TEXT_UTILS from '../../utils/textUtils'

class SegmentFooterTabMessages extends React.Component {
  constructor(props) {
    super(props)
    //Parameter to exclude notes tha match this regexp, used by plugins to exclude some notes
    this.excludeMatchingNotesRegExp
  }

  getFilteredMetadataKeys() {
    return this.props.metadata.filter(
      ({meta_key}) => meta_key !== 'sizeRestriction',
    )
  }
  getNoteContentStructure(note) {
    return TEXT_UTILS.getContentWithAllowedLinkRedirect(note).length > 1
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
  }
  getNotes() {
    let notesHtml = []
    if (this.props.notes) {
      this.props.notes.forEach((item, index) => {
        if (item.note && item.note !== '') {
          if (
            this.excludeMatchingNotesRegExp &&
            this.excludeMatchingNotesRegExp.test(item.note)
          ) {
            return
          }
          let note = item.note
          let html = (
            <div className="note" key={'note-' + index}>
              <span className="note-label">Note: </span>
              <span
                dangerouslySetInnerHTML={{
                  __html: this.getNoteContentStructure(note),
                }}
              />
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
      this.props.context_groups.context_json.forEach((contextGroup, index) => {
        if (
          contextGroup.attr?.purpose &&
          contextGroup.attr.purpose === 'information' &&
          contextGroup.contexts.length > 0
        ) {
          const contextElems = contextGroup.contexts.map((context, i) => (
            <span key={'context-item' + i} className="context-item-name">
              {i > 0 ? ' ;' : ''}
              {context['raw-content']}
            </span>
          ))
          notesHtml.push(
            <div className="context-group" key={'context-group' + index}>
              <span className="context-group-name">Context: </span>
              {contextElems}
            </div>,
          )
        }
      })
    }

    // metadata notes
    const metadata =
      typeof this.getFilteredMetadataKeys === 'function'
        ? this.getFilteredMetadataKeys()
        : this.props.metadata.filter(
            (item) => item.meta_key !== 'sizeRestriction',
          )
    if (metadata?.length > 0) {
      notesHtml.push(this.getMetadataNoteTemplate())
    }
    return notesHtml
  }

  getMetadataNoteTemplate() {
    const metadata =
      typeof this.getFilteredMetadataKeys === 'function'
        ? this.getFilteredMetadataKeys()
        : this.props.metadata
    let metadataNotes = []
    for (const [index, item] of metadata.entries()) {
      const {meta_key, meta_value: body} = item
      const label = meta_key

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
      isUndefined(nextProps.notes) ||
      isUndefined(this.props.note) ||
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
