import {isUndefined} from 'lodash'
import {regexWordDelimiter} from '../components/segments/utils/DraftMatecatUtils/textConstants'
import CommonUtils from './commonUtils'
import {tagSignatures} from '../components/segments/utils/DraftMatecatUtils/tagModel'

const TEXT_UTILS = {
  /**
   * Replace temporaly tags with placeholder
   * @param text
   */
  replaceTempTags: (text) => {
    let tags = []
    const makeid = (length) => {
      let result = ''
      const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
      const charactersLength = characters.length
      let counter = 0
      while (counter < length) {
        result += characters.charAt(
          Math.floor(Math.random() * charactersLength),
        )
        counter += 1
      }
      return result
    }
    text = text.replace(
      /<(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?>/gi,
      function (match) {
        var id = makeid(5)
        tags.push({
          id,
          match,
        })
        return '#_' + id + '_#'
      },
    )
    return {tags, text}
  },
  restoreTempTags(tags, text) {
    text = text.replace(/#_([a-zA-Z]+?)_#/gi, (match, id) => {
      try {
        const tag = tags.find((item) => {
          return item.id === id
        })
        if (!isUndefined(tag)) {
          return tag.match
        }
        return match
      } catch (e) {
        return match
      }
    })
    return text
  },
  htmlEncode: function (value) {
    if (value) {
      var txt = document.createElement('textarea')
      txt.textContent = value
      return txt.innerHTML
    } else {
      return ''
    }
  },
  htmlDecode: function (value) {
    if (value) {
      var txt = document.createElement('textarea')
      txt.innerHTML = value
      return txt.value
    } else {
      return ''
    }
  },

  escapeRegExp(str = '') {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') // $& means the whole matched string
  },

  justSelecting: function () {
    const selection = window.getSelection()
    if (selection.isCollapsed) return false

    let shouldBreakCycle = false
    let container = selection.getRangeAt(0).startContainer

    while (!shouldBreakCycle) {
      container = container.parentNode
      const nodeName = container.nodeName.toLowerCase()

      if (
        nodeName === 'body' ||
        container.classList.contains('segment-body-content')
      ) {
        shouldBreakCycle = true
        if (nodeName === 'body') container = undefined
      }
    }

    return !!container
  },
  replaceUrl: function (textToReplace) {
    let regExpUrl =
      /(http|https):\/\/([\w_-]+(?:(?:\.[\w_-]+)+))([\wàèìòùÀÈÌÒÙáéíóúýÁÉÍÓÚÝâêîôûÂÊÎÔÛãñõÃÑÕäëïöüÿÄËÏÖÜŸçÇßØøÅåÆæœ.,@?^=%&:/~+#'-]*[\wàèìòùÀÈÌÒÙáéíóúýÁÉÍÓÚÝâêîôûÂÊÎÔÛãñõÃÑÕäëïöüÿÄËÏÖÜŸçÇßØøÅåÆæœ@?^=%&/~+#'-])/gim
    return TEXT_UTILS.htmlDecode(textToReplace).replace(
      regExpUrl,
      function (match) {
        let href =
          match[match.length - 1] === '.'
            ? match.substring(0, match.length - 1)
            : match
        return '<a href="' + href + '" target="_blank">' + match + '</a>'
      },
    )
  },
  isContentTextEllipsis: ({offsetWidth, scrollWidth} = {}) =>
    offsetWidth < scrollWidth,
  isSupportingRegexLookAheadLookBehind: () => {
    try {
      return (
        'hibyehihi'
          .replace(new RegExp('(?<=hi)hi', 'g'), 'hello')
          .replace(new RegExp('hi(?!bye)', 'g'), 'hey') === 'hibyeheyhello'
      )
    } catch (error) {
      return false
    }
  },
  getGlossaryMatchRegex: (matches) => {
    // find regex using look ahead and behind
    const findWithRegex = (regex, contentBlock, callback) => {
      const text = contentBlock.getText()
      let matchArr, start, end
      while ((matchArr = regex.exec(text)) !== null) {
        try {
          const difference = matchArr[0].length - matchArr[2].length ?? 0
          start = matchArr.index + difference
          end = start + matchArr[2].length
          TEXT_UTILS.handleTagInside(start, end, contentBlock, callback)
        } catch (e) {
          return false
        }
      }
    }

    // find regex using for safari that not support regex look ahead and behind
    const findWithRegexWordSeparator = (regex, contentBlock, callback) => {
      const text = contentBlock.getText()
      let matchArr, start, end
      while ((matchArr = regex.exec(text)) !== null) {
        try {
          start = matchArr.index
          end = start + matchArr[0].length

          const isPreviousBreakWord =
            (start > 0 && regexWordDelimiter.test(text[start - 1])) ||
            start === 0
          const isNextBreakWord =
            regexWordDelimiter.test(text[end]) || !text[end]

          if (isPreviousBreakWord && isNextBreakWord)
            TEXT_UTILS.handleTagInside(start, end, contentBlock, callback)
        } catch (e) {
          return false
        }
      }
    }

    const findWithRegexCJK = (regex, contentBlock, callback) => {
      const text = contentBlock.getText()
      let matchArr, start, end
      while ((matchArr = regex.exec(text)) !== null) {
        try {
          start = matchArr.index
          end = start + matchArr[0].length
          TEXT_UTILS.handleTagInside(start, end, contentBlock, callback)
        } catch (e) {
          return false
        }
      }
    }

    try {
      const escapedMatches = matches.flatMap((match) =>
        match ? [TEXT_UTILS.escapeRegExp(match)] : [],
      )

      const regex =
        TEXT_UTILS.isSupportingRegexLookAheadLookBehind() && !config.isCJK
          ? new RegExp(
              '(^|\\W)(' + escapedMatches.join('|') + ')(?=\\W|$)',
              'gi',
            )
          : new RegExp('(' + escapedMatches.join('|') + ')', 'gi')

      return {
        regex,
        regexCallback:
          TEXT_UTILS.isSupportingRegexLookAheadLookBehind() && !config.isCJK
            ? findWithRegex
            : config.isCJK
              ? findWithRegexCJK
              : findWithRegexWordSeparator,
      }
    } catch (e) {
      return {}
    }
  },
  regexUrlPath:
    /(http(s)?:\/\/.)?(www\.)?[-a-zA-Z0-9@:%._\\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\\+.~#?&//=]*)/g,

  getContentWithAllowedLinkRedirect: (content) => {
    let match
    let start = 0
    let end = 0
    let prevEnd = 0
    let result = []

    while ((match = TEXT_UTILS.regexUrlPath.exec(content)) !== null) {
      try {
        start = match.index
        end = start + match[0].length

        result.push(content.substring(prevEnd, start))
        const link = content.substring(start, end)

        if (CommonUtils.isAllowedLinkRedirect(link))
          result.push({isLink: true, link})
        else result.push(link)

        prevEnd = end
      } catch (e) {
        console.log(e)
        return false
      }
    }

    if (content.substring(prevEnd)) result.push(content.substring(prevEnd))
    return result.reduce((acc, cur) => {
      if (typeof cur === 'object') {
        return [...acc, cur]
      } else {
        const copy = [...acc]
        const lastItem = copy.pop()
        const newItem =
          typeof lastItem === 'object'
            ? [lastItem, cur]
            : [`${lastItem ? lastItem : ''}${cur}`]
        return [...copy, ...newItem]
      }
    }, [])
  },
  removeHiddenCharacters: (value) => value.replace(/\u2060/g, ''),
  stripUnderscore: (value) =>
    value.replace(/_[^_]/g, (match) => match[1].toUpperCase()),
  hasEntity: (charPosition, contentBlock) =>
    contentBlock.getEntityAt(charPosition),
  handleTagInside: (start, end, contentBlock, callback) => {
    let cursor = start
    while (cursor < end) {
      // start
      while (TEXT_UTILS.hasEntity(cursor, contentBlock) && cursor < end) {
        cursor++
      }
      let tempStart = cursor
      // end
      while (!TEXT_UTILS.hasEntity(cursor, contentBlock) && cursor < end) {
        cursor++
      }
      // no entity between, end loop
      if (cursor === tempStart) {
        cursor = end
      }
      let tempEnd = cursor
      callback(tempStart, tempEnd)
    }
  },
  removeWhitespacePlaceholders: (text) => {
    let spaceRegexp = tagSignatures.space
      ? new RegExp(tagSignatures.space.placeholder, 'g')
      : null
    return text.replace(spaceRegexp, ' ')
  },
}

export default TEXT_UTILS
