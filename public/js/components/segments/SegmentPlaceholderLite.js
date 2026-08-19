import React, {useEffect, useRef, useState} from 'react'
import {CompositeDecorator, Editor, EditorState} from 'draft-js'

import DraftMatecatUtils from './utils/DraftMatecatUtils'
import SegmentUtils from '../../utils/segmentUtils'
import IconSplit from '../../../img/icons/IconSplit'

const SegmentPlaceholderLite = (props) => {
  const containerRef = useRef(null)
  const editorSourceRef = useRef(null)
  const editorTargetRef = useRef(null)

  // --- Prepare Source
  const [editorStateSource] = useState(() => {
    const decoratorsStructureSource = [
      {
        strategy: DraftMatecatUtils.getEntityStrategy('IMMUTABLE'),
        component: TagEntity,
      },
    ]
    //const decorator = new CompoundDecorator(decoratorsStructureSource);
    const decorator = new CompositeDecorator(decoratorsStructureSource)
    const plainEditorStateSource = EditorState.createEmpty(decorator)
    const source = props.segment.segment
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(
      props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(source)
      : source
    const contentEncodedSource = DraftMatecatUtils.encodeContent(
      plainEditorStateSource,
      cleanSource,
    )
    const {editorState: editorStateSource} = contentEncodedSource
    return editorStateSource
  })

  // --- Prepare Translation
  const [editorStateTarget] = useState(() => {
    const decoratorsStructureSource = [
      {
        strategy: DraftMatecatUtils.getEntityStrategy('IMMUTABLE'),
        component: TagEntity,
      },
    ]
    const decorator = new CompositeDecorator(decoratorsStructureSource)
    const plainEditorStateTarget = EditorState.createEmpty(decorator)
    const translation = props.segment.translation

    const cleanTranslation = SegmentUtils.checkCurrentSegmentTPEnabled(
      props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation
    const contentEncodedTarget = DraftMatecatUtils.encodeContent(
      plainEditorStateTarget,
      cleanTranslation,
    )
    const {editorState: editorStateTarget} = contentEncodedTarget
    return editorStateTarget
  })

  const onChange = () => {}

  useEffect(() => {
    // Set container width as window width
    containerRef.current.style.cssText = `width:${
      window.innerWidth - 10
    }px !important;`
    // Get rendered source and target
    const source = containerRef.current.getElementsByClassName('source')[0],
      target = containerRef.current.getElementsByClassName('target')[0]
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
    props.computeHeight(computedH)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const getSegmentStructure = () => {
    const {sideOpen} = props

    return (
      <section className={`status-draft ${sideOpen ? 'slide-right' : ''}`}>
        <div className="sid">
          <div className="txt">0000000</div>
          <div className="txt segment-add-inBulk">
            <input type="checkbox" />
          </div>
          <div className="actions">
            <button className="split" title="Click to split segment">
              <IconSplit />
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
                    ref={(el) => (editorSourceRef.current = el)}
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
                        ref={(el) => (editorTargetRef.current = el)}
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
                      <p>CTRL+ENTER</p>
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

  const segmentPlaceholder = getSegmentStructure()
  return (
    <div
      className={'segment-container segment-placeholder'}
      ref={(el) => (containerRef.current = el)}
      style={{display: 'inline-block', width: '100%'}}
    >
      {segmentPlaceholder}
    </div>
  )
}

export default SegmentPlaceholderLite

const TagEntity = (props) => {
  const selectCorrectStyle = () => {
    const {entityKey, contentState} = props
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

  const [tagStyle] = useState(() => selectCorrectStyle())

  const {children} = props

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
