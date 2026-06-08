export const getIdAttributeRegEx = () => {
  return /id="(-?\w+)"/g
}

export const unescapeHTMLinTags = (escapedHTML) => {
  try {
    return escapedHTML
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&amp;amp;/g, '&')
      .replace(/&amp;/g, '&')
      .replace(/&nbsp;/g, ' ')
      .replace(/&apos;/g, "'")
      .replace(/&quot;/g, '"')
  } catch (e) {
    return ''
  }
}

export const unescapeHTMLRecursive = (escapedHTML) => {
  const regex = /&amp;|&lt;|&gt;|&nbsp;|&apos;|&quot;/

  try {
    while (regex.exec(escapedHTML) !== null) {
      escapedHTML = unescapeHTMLinTags(escapedHTML)
    }
  } catch (e) {
    console.error('Error unescapeHTMLRecursive')
  }

  return escapedHTML
}
