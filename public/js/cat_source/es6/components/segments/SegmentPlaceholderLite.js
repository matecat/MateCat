import React, {Component} from 'react'
import {CompositeDecorator, Editor, EditorState} from 'draft-js'

import DraftMatecatUtils from './utils/DraftMatecatUtils'
import SegmentUtils from '../../utils/segmentUtils'

class SegmentPlaceholderLite extends React.Component {
  constructor(props) {
    super(props)
    // --- Prepare  Decorator
    this.decoratorsStructureSource = [
      {
        strategy: DraftMatecatUtils.getEntityStrategy('IMMUTABLE'),
        component: TagEntity,
      },
    ]
    //const decorator = new CompoundDecorator(this.decoratorsStructureSource);
    const decorator = new CompositeDecorator(this.decoratorsStructureSource)
    // --- Prepare Source
    const plainEditorStateSource = EditorState.createEmpty(decorator)
    const source = this.props.segment.segment
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(
      this.props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(source)
      : source
    const contentEncodedSource = DraftMatecatUtils.encodeContent(
      plainEditorStateSource,
      cleanSource,
    )
    const {editorState: editorStateSource} = contentEncodedSource

    // --- Prepare Translation
    const plainEditorStateTarget = EditorState.createEmpty(decorator)
    const translation = this.props.segment.translation

    const cleanTranslation = SegmentUtils.checkCurrentSegmentTPEnabled(
      this.props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation
    const contentEncodedTarget = DraftMatecatUtils.encodeContent(
      plainEditorStateTarget,
      cleanTranslation,
    )
    const {editorState: editorStateTarget} = contentEncodedTarget
    // --- Set Editor content
    this.state = {
      editorStateSource,
      editorStateTarget,
    }
    this.onChange = () => {}
  }

  containerRef = null

  componentDidMount() {
    // Set container width as window width
    this.containerRef.style.cssText = `width:${
      window.innerWidth - 10
    }px !important;`
    // Get rendered source and target
    const source = this.containerRef.getElementsByClassName('source')[0],
      target = this.containerRef.getElementsByClassName('target')[0]
    // Get div "source" size
    const sourceBCR = source.getBoundingClientRect()
    // Get Editors
    const sourceEditor = source.getElementsByClassName('DraftEditor-root')[0]
    const targetEditor = target.getElementsByClassName('DraftEditor-root')[0]
    // Set editor width equal to width of div "source"
    sourceEditor.style.cssText = `width:${sourceBCR.width}px !important;`
    const sourceEditorAdjustedBCR = sourceEditor.getBoundingClientRect()
    // Set target width as source width (source is always bigger due to html and css)
    targetEditor.style.cssText = `width:${sourceEditorAdjustedBCR.width}px !important;`
    const targetEditorAdjustedBCR = targetEditor.getBoundingClientRect()
    // Get which editor is bigger
    // let maxEditor = Math.max(sourceEditorAdjustedBCR.height, targetEditorAdjustedBCR.height);
    let maxEditor =
      sourceEditorAdjustedBCR.height > targetEditorAdjustedBCR.height
        ? sourceEditorAdjustedBCR.height
        : targetEditorAdjustedBCR.height
    // Add outer padding
    const outerDivPadding = 33
    // Set min Editor height
    const minEditorHeight = 90
    const computedH =
      maxEditor + outerDivPadding > minEditorHeight
        ? maxEditor + outerDivPadding
        : minEditorHeight
    this.props.computeHeight(computedH)
  }

  render() {
    const segmentPlaceholder = this.getSegmentStructure()
    return (
      <div
        className={'segment-container segment-placeholder'}
        ref={(el) => (this.containerRef = el)}
        style={{display: 'inline-block', width: '100%'}}
      >
        {segmentPlaceholder}
      </div>
    )
  }

  getSegmentStructure = () => {
    const {sideOpen} = this.props
    const {editorStateSource, editorStateTarget} = this.state
    const {onChange} = this

    return (
      <section className={`status-draft ${sideOpen ? 'slide-right' : ''}`}>
        <div className="sid">
          <div className="txt">0000000</div>
          <div className="txt segment-add-inBulk">
            <input type="checkbox" />
          </div>
          <div className="actions">
            <button className="split" title="Click to split segment">
              <i className="icon-split"> </i>
            </button>
            <p className="split-shortcut">CTRL + S</p>
          </div>
        </div>

        <div className="body">
          <div className="header toggle"> </div>
          <div
            className="text segment-body-content"
            style={{boxSizing: 'content-box'}}
          >
            <div className="wrap">
              <div className="outersource">
                <div className="source item" tabIndex="0">
                  <Editor
                    editorState={editorStateSource}
                    onChange={onChange}
                    ref={(el) => (this.editorSource = el)}
                    readOnly={false}
                  />
                </div>
                <div className="copy" title="Copy source to target">
                  <a href="#"> </a>
                  <p>CTRL+I</p>
                </div>
                <div className="target item">
                  <div className="textarea-container">
                    <div className="targetarea editarea" spellCheck="true">
                      <Editor
                        editorState={editorStateTarget}
                        onChange={onChange}
                        ref={(el) => (this.editorTarget = el)}
                        readOnly={false}
                      />
                    </div>
                    <div className="toolbar">
                      <a
                        className="revise-qr-link"
                        title="Segment Quality Report."
                        target="_blank"
                        href="#"
                      >
                        QR
                      </a>
                      <a
                        href="#"
                        className="tagModeToggle "
                        title="Display full/short tags"
                      >
                        <span className="icon-chevron-left"> </span>
                        <span className="icon-tag-expand"> </span>
                        <span className="icon-chevron-right"> </span>
                      </a>
                      <a
                        href="#"
                        className="autofillTag"
                        title="Copy missing tags from source to target"
                      >
                        {' '}
                      </a>
                      <ul className="editToolbar">
                        <li className="uppercase" title="Uppercase">
                          {' '}
                        </li>
                        <li className="lowercase" title="Lowercase">
                          {' '}
                        </li>
                        <li className="capitalize" title="Capitalized">
                          {' '}
                        </li>
                      </ul>
                    </div>
                  </div>
                  <p className="warnings"> </p>
                  <ul className="buttons toggle">
                    <li>
                      <a href="#" className="translated">
                        {' '}
                        Translated{' '}
                      </a>
                      <p>CTRL ENTER</p>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <div className="status-container">
              <a href="#" className="status no-hover">
                {' '}
              </a>
            </div>
          </div>
          <div className="timetoedit" data-raw-time-to-edit="0">
            {' '}
          </div>
          <div className="edit-distance">Edit Distance:</div>
        </div>
        <div className="segment-side-buttons">
          <div
            data-mount="translation-issues-button"
            className="translation-issues-button"
          >
            {' '}
          </div>
        </div>
        <div className="segment-side-container"> </div>
      </section>
    )
  }
}

export default SegmentPlaceholderLite

class TagEntity extends Component {
  constructor(props) {
    super(props)

    const tagStyle = this.selectCorrectStyle()

    this.state = {
      tagStyle,
    }
  }

  render() {
    const {children} = this.props
    const {tagStyle} = this.state

    return (
      <div className={'tag-container'}>
        <span
          className={`tag ${tagStyle} `}
          unselectable="on"
          suppressContentEditableWarning={true}
        >
          {children}
        </span>
      </div>
    )
  }

  selectCorrectStyle = () => {
    const {entityKey, contentState} = this.props
    const entityInstance = contentState.getEntity(entityKey)
    let tagStyle = []

    if (entityInstance.data.openTagId) {
      tagStyle.push('tag-close')
    } else if (entityInstance.data.closeTagId) {
      tagStyle.push('tag-open')
    } else {
      tagStyle.push('tag-selfclosed')
    }
    return tagStyle.join(' ')
  }
}
