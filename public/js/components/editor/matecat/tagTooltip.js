// The tooltip of a trimmed or compressed tag chip: the full tag text, shown as TagEntity shows it in Draft
// (common/Tooltip, above the chip, after the same delay). Never for a closing tag, which shows only its number.
import React from 'react'
import {createRoot} from 'react-dom/client'
import {EditorView} from '@codemirror/view'
import Tooltip, {TOOLTIP_MODE} from '../../common/Tooltip'
import CatToolStore from '../../../stores/CatToolStore'
import {getTooltipTag} from '../../../utils/segmentText/tagModel'

// Tooltip's own default delay, and the height of its arrow.
const DELAY = 300
const ARROW = 7

/**
 * Whether a hovered chip (a `tagHover` payload) gets a tooltip, TagEntity's rule: a trimmed chip of a tag type that
 * allows it, or a numbered chip while the tags are compressed (body.tags-compressed hides its text).
 */
export const showsTagTooltip = ({name, index, role, truncated}) =>
  role !== 'close' &&
  ((truncated && getTooltipTag().includes(name)) ||
    (index !== null && CatToolStore.isTagsCompressed()))

// The chip classes TagEntity puts on its tooltip copy: not the editor size, nor the pair shape.
const contentClass = (className) =>
  className
    .split(' ')
    .filter(
      (c) =>
        c &&
        c !== 'small' &&
        c !== 'tag-numbered' &&
        !c.startsWith('tag-paired-'),
    )
    .join(' ')

/**
 * The tag tooltip of one editor: `onEvent` takes the chip events of `tagEventSink`, `extension` hides the tooltip
 * when the document changes under it, `destroy` removes it.
 */
export const createTagTooltip = () => {
  let timer = null
  let container = null
  let root = null

  const onScroll = () => hide()

  const hide = () => {
    clearTimeout(timer)
    timer = null
    if (!root) return
    const current = root
    const node = container
    root = null
    container = null
    window.removeEventListener('scroll', onScroll, true)
    // Unmounting while React is rendering warns: wait for the current task to end.
    setTimeout(() => {
      current.unmount()
      node.remove()
    })
  }

  const show = ({className, label, rect}) => {
    container = document.createElement('div')
    container.className = 'cm-tag-tooltip'
    document.body.appendChild(container)
    root = createRoot(container)
    root.render(
      <Tooltip
        mode={TOOLTIP_MODE.STATIC}
        style={{
          top: rect.top + window.scrollY - ARROW,
          left: rect.left + window.scrollX + rect.width / 2,
        }}
        content={
          <span className={contentClass(className)}>
            <span>{label}</span>
          </span>
        }
      />,
    )
    window.addEventListener('scroll', onScroll, true)
  }

  const onEvent = (name, payload) => {
    if (name === 'tagHover') {
      hide()
      if (showsTagTooltip(payload))
        timer = setTimeout(() => show(payload), DELAY)
    } else if (name === 'tagLeave' || name === 'tagClick') {
      hide()
    }
  }

  // A rebuilt chip never reports the mouse leaving it.
  const extension = EditorView.updateListener.of((update) => {
    if (update.docChanged) hide()
  })

  return {onEvent, extension, destroy: hide}
}
