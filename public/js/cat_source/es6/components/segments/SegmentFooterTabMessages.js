/**
 * React Component .

 */
import React from 'react'
import Immutable from 'immutable'
class SegmentFooterTabMessages extends React.Component {
  constructor(props) {
    super(props)
    //Parameter to exclude notes tha match this regexp, used by plugins to exclude some notes
    this.excludeMatchingNotesRegExp
  }

  getNotes() {
    let notesHtml = []
    let self = this
    if (this.props.notes) {
      this.props.notes.forEach(function (item, index) {
        if (item.note && item.note !== '') {
          if (
              self.excludeMatchingNotesRegExp &&
              self.excludeMatchingNotesRegExp.test(item.note)
          ) {
            return
          }
          //parse url link
          // let note = TextUtils.replaceUrl(item.note.replace(/[ ]*\n/g, '<br>\n'))
          let note = item.note;
          let html = (
              <div className="note" key={'note-' + index}>
                <span className="note-label">Note: </span>
                {/*<span dangerouslySetInnerHTML={self.allowHTML(note)} />*/}
                <span >{note}</span>
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
                  <span className="note-label">{key.toUpperCase()}: </span>
                  <span> {item.json[key]} </span>
                </div>
            )
            notesHtml.push(html)
          })
        } else if (typeof item.json === 'string') {
          let text = item.json
          let html = (
              <div
                  key={'note-json' + index}
                  className="note"
              > {text}</div>
          )
          notesHtml.push(html)
        }
      })
    }
    if (this.props.context_groups && this.props.context_groups.context_json) {
      this.props.context_groups.context_json.forEach((contextGroup) => {
        let contextElems = []
        contextGroup.contexts.forEach((context) => {
          contextElems.push(
              <div
                  className="context-item"
                  key={contextGroup.id + context.attr['context-type']}
              >
                {/*<span className="context-item-label">{context.attr["context-type"]}</span>*/}
                <span className="context-item-name">{context.content}</span>
              </div>,
          )
        })
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
      })
    }
    if (notesHtml.length === 0) {
      let html = (
          <div className="note" key={'note-0'}>
            There are no notes available
          </div>
      )
      notesHtml.push(html)
    }
    return notesHtml
  }

  componentDidMount() {}

  componentWillUnmount() {}

  allowHTML(string) {
    return {__html: string}
  }

  shouldComponentUpdate(nextProps, nextState) {
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
            id={'segment-' + this.props.id_segment + '-' + this.props.tab_class}
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
