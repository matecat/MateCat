// The hover tooltip of the layer marks: one at a time, shown after the pointer rests on a mark, kept open while the
// pointer crosses the gap into it and stays there (its buttons can be used), closed a moment after the pointer has left
// both. The host renders the content; this only decides when it shows and where the pointer is.
import {ViewPlugin} from '@codemirror/view'
import {LAYER_ATTRIBUTE, isLayerHidden, layerRangesAt, layers} from './layers'

export const HOVER_TIME = 300
export const HIDE_DELAY = 250

const keyOf = (ranges) =>
  ranges.map(({name, from, to}) => `${name}:${from}-${to}`).join(' ')

/**
 * The visible layer ranges under a mark element of `view` (the innermost mark holding `target`), or null.
 * @returns {{mark: HTMLElement, ranges: Array<{name, from, to, data}>}|null}
 */
export const rangesUnder = (view, target) => {
  const mark = target?.closest?.(`[${LAYER_ATTRIBUTE}]`)
  if (!mark || !view.contentDOM.contains(mark)) return null
  const pos = view.posAtDOM(mark)
  const ranges = layerRangesAt(view.state, pos).filter(
    ({name, from, to}) =>
      from <= pos && pos < to && !isLayerHidden(view.state, name),
  )
  return ranges.length ? {mark, ranges} : null
}

/**
 * @param {object} options
 * @param {(view: import('@codemirror/view').EditorView, hovered: {ranges: Array<object>, rect: DOMRect,
 *   close: () => void}) => ({dom: HTMLElement, destroy?: () => void}|null)} options.render the tooltip for the
 *   hovered ranges, or null when they have none; `dom` is added to the page body and removed on close
 * @param {number} [options.hoverTime] how long the pointer rests on a mark before its tooltip shows
 * @param {number} [options.hideDelay] how long the tooltip stays after the pointer left it and its mark
 */
export const layerHover = ({
  render,
  hoverTime = HOVER_TIME,
  hideDelay = HIDE_DELAY,
}) =>
  ViewPlugin.fromClass(
    class {
      constructor(view) {
        this.view = view
        this.key = null
        this.pendingKey = null
        this.shown = null
        this.showTimer = null
        this.hideTimer = null
        this.onOver = (event) => this.over(event)
        this.onOut = (event) => this.out(event)
        view.contentDOM.addEventListener('mouseover', this.onOver)
        view.contentDOM.addEventListener('mouseout', this.onOut)
        this.onScroll = () => this.hide()
      }

      over(event) {
        const hovered = rangesUnder(this.view, event.target)
        if (!hovered) return
        const key = keyOf(hovered.ranges)
        if (key === this.key) {
          clearTimeout(this.hideTimer)
          return
        }
        if (key === this.pendingKey) return
        clearTimeout(this.showTimer)
        this.pendingKey = key
        this.showTimer = setTimeout(() => {
          this.pendingKey = null
          this.show(key, hovered)
        }, hoverTime)
      }

      out(event) {
        const next = event.relatedTarget
        if (this.shown?.dom.contains(next)) return
        const hovered = rangesUnder(this.view, next)
        const key = hovered && keyOf(hovered.ranges)
        if (key && (key === this.key || key === this.pendingKey)) return
        clearTimeout(this.showTimer)
        this.pendingKey = null
        this.scheduleHide()
      }

      scheduleHide() {
        if (!this.shown) return
        clearTimeout(this.hideTimer)
        this.hideTimer = setTimeout(() => this.hide(), hideDelay)
      }

      show(key, {mark, ranges}) {
        this.hide()
        if (!mark.isConnected) return
        const shown = render(this.view, {
          ranges,
          rect: mark.getBoundingClientRect(),
          close: () => this.hide(),
        })
        if (!shown) return
        this.key = key
        this.shown = shown
        shown.dom.addEventListener('mouseenter', () =>
          clearTimeout(this.hideTimer),
        )
        shown.dom.addEventListener('mouseleave', (event) => {
          const back = rangesUnder(this.view, event.relatedTarget)
          if (back && keyOf(back.ranges) === this.key) return
          this.scheduleHide()
        })
        document.body.appendChild(shown.dom)
        window.addEventListener('scroll', this.onScroll, true)
      }

      hide() {
        clearTimeout(this.hideTimer)
        this.hideTimer = null
        if (!this.shown) return
        const {dom, destroy} = this.shown
        this.shown = null
        this.key = null
        window.removeEventListener('scroll', this.onScroll, true)
        destroy?.()
        dom.remove()
      }

      update(update) {
        if (!this.shown) return
        // The text under it changed, or the range it explains is gone (an ignored warning): it explains nothing now.
        if (
          update.docChanged ||
          (update.state.field(layers) !== update.startState.field(layers) &&
            !this.stillThere(update.state))
        ) {
          // Not from inside an update: the content may be what dispatched it.
          const {key} = this
          setTimeout(() => {
            if (this.key === key) this.hide()
          })
        }
      }

      stillThere(state) {
        const [first] = this.key.split(' ')
        const [name, span] = first.split(':')
        const [from, to] = span.split('-').map(Number)
        return layerRangesAt(state, from).some(
          (r) => r.name === name && r.from === from && r.to === to,
        )
      }

      destroy() {
        clearTimeout(this.showTimer)
        this.view.contentDOM.removeEventListener('mouseover', this.onOver)
        this.view.contentDOM.removeEventListener('mouseout', this.onOut)
        this.hide()
      }
    },
  )
