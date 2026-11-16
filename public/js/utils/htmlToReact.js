import React from 'react'

/**
 * Turns an HTML string into React elements without injecting HTML into the
 * page: the string is parsed by DOMParser, which runs no scripts and loads
 * nothing, and only an allowlist of tags and attributes is rebuilt.
 *
 * A tag outside the allowlist keeps its text and loses its markup. Script and
 * style elements are dropped with their content. Links keep only http, https
 * and mailto URLs, because React 18 still renders `javascript:` ones.
 */
const ALLOWED_TAGS = new Set([
  'a',
  'b',
  'strong',
  'i',
  'em',
  'u',
  'br',
  'p',
  'span',
  'ul',
  'ol',
  'li',
  'code',
])
const DROPPED_WITH_CONTENT = new Set(['script', 'style', 'template', 'iframe'])
const SAFE_URL = /^(https?:|mailto:)/i

const toLinkProps = (element) => {
  const href = element.getAttribute('href')?.trim()
  if (!href || !SAFE_URL.test(href)) return {}

  return href.toLowerCase().startsWith('mailto:')
    ? {href}
    : {href, target: '_blank', rel: 'noopener noreferrer'}
}

const toReact = (node, key) => {
  if (node.nodeType === Node.TEXT_NODE) return node.textContent
  if (node.nodeType !== Node.ELEMENT_NODE) return null

  const tag = node.tagName.toLowerCase()
  if (DROPPED_WITH_CONTENT.has(tag)) return null

  const children = Array.from(node.childNodes, toReact)
  if (!ALLOWED_TAGS.has(tag))
    return <React.Fragment key={key}>{children}</React.Fragment>
  if (tag === 'br') return <br key={key} />

  const props = tag === 'a' ? toLinkProps(node) : {}
  return React.createElement(
    tag,
    {...props, key},
    children.length ? children : undefined,
  )
}

export const htmlToReact = (html) => {
  if (typeof html !== 'string' || !/[<&]/.test(html)) return html

  const {body} = new DOMParser().parseFromString(html, 'text/html')
  return <>{Array.from(body.childNodes, toReact)}</>
}
