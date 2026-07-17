import React, {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
} from 'react'
import getEntityStrategy from './utils/DraftMatecatUtils/getEntityStrategy'
import {
  CompositeDecorator,
  Editor,
  EditorState,
  Modifier,
  SelectionState,
} from 'draft-js'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import PropTypes from 'prop-types'
import getContentStateFragment from './utils/DraftMatecatUtils/DraftSource/src/model/transaction/getContentStateFragment'
import SegmentActions from '../../actions/SegmentActions'
import {TagEntityLite} from './TagEntity/TagEntityLite'

export const EditorLite = forwardRef(({content, highlightSnippet}, ref) => {
  const fragmentHighlightRef = useRef()

  const applyStyleToSnippet = useCallback(
    (editorState) => {
      if (!highlightSnippet.text) return editorState

      let contentState = editorState.getCurrentContent()
      let {editorState: editorStateTarget} = DraftMatecatUtils.encodeContent(
        EditorState.createEmpty(),
        highlightSnippet.text,
      )

      const target = editorStateTarget.getCurrentContent().getPlainText()

      contentState.getBlockMap().forEach((block) => {
        const text = block.getText()
        const start = text.indexOf(target)

        if (start !== -1) {
          const end = start + target.length

          const selection = editorState.getSelection().merge({
            anchorKey: block.getKey(),
            focusKey: block.getKey(),
            anchorOffset: start,
            focusOffset: end,
          })

          let fragment = getContentStateFragment(contentState, selection)
          const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(
            fragment,
            editorState,
          )
          const fragmentStringfy = JSON.stringify({
            orderedMap: fragment,
            entitiesMap: entitiesMap,
          })

          fragmentHighlightRef.current = {
            fragmentStringfy,
            target,
          }

          contentState = Modifier.applyInlineStyle(
            contentState,
            selection,
            'BOLD',
          )
        }
      })

      return EditorState.createWithContent(
        contentState,
        editorState.getDecorator(),
      )
    },
    [highlightSnippet],
  )

  const editor = useMemo(() => {
    const decoratorsStructure = [
      {
        name: 'tags',
        strategy: getEntityStrategy('IMMUTABLE'),
        component: TagEntityLite,
        props: {
          isTarget: false,
          isRTL: config.isTargetRTL,
        },
      },
    ]

    const decorator = new CompositeDecorator(decoratorsStructure)
    const editorState = EditorState.createEmpty(decorator)
    let {editorState: editorStateUpdate} = DraftMatecatUtils.encodeContent(
      editorState,
      content,
    )

    editorStateUpdate = applyStyleToSnippet(editorStateUpdate)

    return editorStateUpdate
  }, [content, applyStyleToSnippet])

  const [editorState, setEditorState] = useState(editor)

  useImperativeHandle(ref, () => ({
    copyToClipboard: () => {
      let contentState = editorState.getCurrentContent()
      const firstBlock = contentState.getFirstBlock()
      const lastBlock = contentState.getLastBlock()

      const selection = SelectionState.createEmpty(firstBlock.getKey()).merge({
        anchorKey: firstBlock.getKey(),
        anchorOffset: 0,
        focusKey: lastBlock.getKey(),
        focusOffset: lastBlock.getLength(),
      })

      contentState = Modifier.removeInlineStyle(contentState, selection, 'BOLD')

      let fragment = getContentStateFragment(contentState, selection)
      const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(
        fragment,
        editorState,
      )
      const fragmentStringfy = JSON.stringify({
        orderedMap: fragment,
        entitiesMap: entitiesMap,
      })

      navigator.clipboard.writeText(contentState.getPlainText())
      SegmentActions.copyFragmentToClipboard(
        fragmentStringfy,
        contentState.getPlainText(),
      )
    },
    copyToClipboardHighlight: () => {
      if (fragmentHighlightRef.current) {
        const {fragmentStringfy, target} = fragmentHighlightRef.current

        navigator.clipboard.writeText(target)
        SegmentActions.copyFragmentToClipboard(fragmentStringfy, target)
      }
    },
  }))

  useEffect(() => {
    setEditorState(editor)
  }, [editor])

  return (
    <Editor
      editorState={editorState}
      onChange={() => false}
      readOnly={false}
      textAlignment={config.isTargetRTL ? 'right' : 'left'}
      textDirectionality={config.isTargetRTL ? 'RTL' : 'LTR'}
    />
  )
})

EditorLite.displayName = 'EditorLite'

EditorLite.propTypes = {
  content: PropTypes.string.isRequired,
  highlightSnippet: PropTypes.shape({
    text: PropTypes.string,
    style: PropTypes.oneOf(['BOLD', 'ITALIC', 'UNDERLINE']),
  }),
}
