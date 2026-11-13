// The tooltip of a trimmed or compressed tag chip: the full tag text, shown as TagEntity shows it in Draft
// (common/Tooltip, above the chip, after the same delay). Never for a closing tag, which shows only its number. Shared
// by the editors (editor/matecat/tagTooltip.js) and the read-only segment view; neither Draft nor CodeMirror here.
import React from 'react'
import {createRoot} from 'react-dom/client'
import Tooltip, {TOOLTIP_MODE} from '../common/Tooltip'
import {getTooltipTag} from '../../utils/segmentText/tagModel'

// Tooltip's own default delay, and the height of its arrow.
const DELAY = 300
const ARROW = 7

/**
 * Whether a hovered chip gets a tooltip, TagEntity's rule: a trimmed chip of a tag type that allows it, or a numbered
 * chip while the tags are `compressed` (body.tags-compressed hides its text).
 * @param {{name: string, index: number|null, role: string|null, truncated: boolean}} chip
 */
export const showsTagTooltip = ({name, index, role, truncated}, compressed) =>
  role !== 'close' &&
  ((truncated && getTooltipTag().includes(name)) ||
    (index !== null && compressed))

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
 * One tag tooltip: `onEvent` takes `tagHover` (`{name, index, role, className, label, truncated, rect}`), `tagLeave`
 * and `tagClick`; `hide` removes it.
 * @param {{isCompressed: () => boolean}} options whether the tags are compressed right now
 */
export const createTagTooltip = ({isCompressed}) => {
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
    container.className = 'tag-full-text-tooltip'
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
      if (showsTagTooltip(payload, isCompressed()))
        timer = setTimeout(() => show(payload), DELAY)
    } else if (name === 'tagLeave' || name === 'tagClick') {
      hide()
    }
  }

  return {onEvent, hide}
}

// The chip element under an event target inside `root`, as SegmentView renders it.
const chipOf = (target, root) => {
  const chip = target?.closest?.('[data-tag-name]')
  return chip && root.contains(chip) ? chip : null
}

/**
 * What a hovered SegmentView chip reports, as the editor chips do: the label is the text of its label element, which
 * Tag.scss trims with an ellipsis.
 */
export const chipHover = (chip) => {
  const text = chip.querySelector('.tag-text, [data-text]') ?? chip
  const {top, left, width, height} = chip.getBoundingClientRect()
  return {
    name: chip.dataset.tagName,
    index: chip.dataset.tagIndex === undefined ? null : +chip.dataset.tagIndex,
    role: chip.dataset.tagRole ?? null,
    className: chip.className,
    label: text.textContent,
    truncated: text.offsetWidth < text.scrollWidth,
    rect: {top, left, width, height},
  }
}

let shared = null
// One tooltip for every segment view of the page, created on the first hover.
const sharedTooltip = () => {
  if (!shared) {
    shared = createTagTooltip({
      isCompressed: () => document.body.classList.contains('tags-compressed'),
    })
  }
  return shared
}

/**
 * Delegated hover handlers for a segment view root: one pair per view, one tooltip for all of them, no state per chip.
 */
export const segmentViewTagHandlers = {
  onMouseOver(event) {
    const chip = chipOf(event.target, event.currentTarget)
    if (!chip || chip.contains(event.relatedTarget)) return
    sharedTooltip().onEvent('tagHover', chipHover(chip))
  },
  onMouseOut(event) {
    const chip = chipOf(event.target, event.currentTarget)
    if (!chip || chip.contains(event.relatedTarget)) return
    sharedTooltip().onEvent('tagLeave', {})
  },
  onMouseDown(event) {
    if (chipOf(event.target, event.currentTarget))
      sharedTooltip().onEvent('tagClick', {})
  },
}
