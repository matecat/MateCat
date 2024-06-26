import {isUndefined} from 'lodash'

import {regexWordDelimiter} from '../components/segments/utils/DraftMatecatUtils/textUtils'
import CommonUtils from './commonUtils'
import diff_match_patch from 'diff-match-patch'

const TEXT_UTILS = {
  diffMatchPatch: new diff_match_patch(),
  getDiffHtml: function (source, target) {
    let dmp = new diff_match_patch()
    /*
        There are problems when you delete or add a tag next to another, the algorithm that makes the diff fails to recognize the tags,
        they come out of the function broken.
        Before passing them to the function that makes the diff we replace all the tags with placeholders and we keep a map of the tags
        indexed with the id of the tags.
         */
    var phTagsObject = []
    var diff
    try {
      source = source.replace(
        /<(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?>/gi,
        function (match) {
          const existingTag = phTagsObject.find((item) => item.match === match)
          if (!existingTag) {
            const id = Math.floor(Math.random() * 10000)
            phTagsObject.push({
              id,
              match,
            })
            return '<' + id + '>'
          } else {
            return '<' + existingTag.id + '>'
          }
        },
      )

      target = target.replace(
        /<(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?>/gi,
        function (match) {
          const existingTag = phTagsObject.find((item) => item.match === match)
          if (!existingTag) {
            const id = Math.floor(Math.random() * 10000)
            phTagsObject.push({
              id,
              match,
            })
            return '<' + id + '>'
          } else {
            return '<' + existingTag.id + '>'
          }
        },
      )

      diff = dmp.diff_main(
        this.replacePlaceholder(
          source.replace(/&nbsp; /g, '  ').replace(/&nbsp;/g, ''),
        ),
        this.replacePlaceholder(
          target.replace(/&nbsp; /g, '  ').replace(/&nbsp;/g, ''),
        ),
      )

      dmp.diff_cleanupSemantic(diff)

      /*
      Before adding spans to identify added or subtracted portions we need to check and fix broken tags
      */
      diff = this.setUnclosedTagsInDiff(diff)
      var diffTxt = ''
      $.each(diff, (index, text) => {
        text[1] = text[1].replace(/<(.*?)>/gi, (match, id) => {
          try {
            var tag = phTagsObject.find((item) => {
              return item.id === parseInt(id)
            })
            if (!isUndefined(tag)) {
              return tag.match
            }
            return match
          } catch (e) {
            return match
          }
        })

        if (text[0] === -1) {
          diffTxt += '<span class="deleted">' + text[1] + '</span>'
        } else if (text[0] === 1) {
          diffTxt += '<span class="added">' + text[1] + '</span>'
        } else {
          diffTxt += text[1]
        }
      })
      return this.restorePlaceholders(diffTxt)
    } catch (e) {
      return source
    }
  },
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
    text = text.replace(/#_([a-zA-Z]*?)_#/gi, (match, id) => {
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
  /**
   *This function takes in the array that exits the TextUtils.diffMatchPatch.diff_main function and parses the array elements to see if they contain broken tags.
   * The array is of the type:
   *
   * [0, "text"],
   * [-1, "deletedText"]
   * [1, "addedText"]
   *
   * For each element of the array in the first position there is 0, 1, -1 which indicate if the text is equal, added, removed
   */
  setUnclosedTagsInDiff: function (array) {
    /*
        Function to understand if an element contains broken tags
         */
    var thereAreUncompletedTagsInDiff = function (text) {
      return (
        (text.indexOf('<') > -1 || text.indexOf('>') > -1) &&
        (text.split('<').length - 1 !== text.split('>').length - 1 ||
          text.indexOf('<') >= text.indexOf('>'))
      )
    }
    /*
        Function to understand if an element contains broken tags where the opening part is missing
         */
    var thereAreCloseTags = function (text) {
      return (
        thereAreUncompletedTagsInDiff(text) &&
        (item[1].split('<').length - 1 < item[1].split('>').length - 1 ||
          (item[1].indexOf('>') > -1 &&
            item[1].indexOf('>') < item[1].indexOf('<')))
      )
    }
    /*
        Function to understand if an element contains broken tags where the closing part is missing
         */
    var thereAreOpenTags = function (text) {
      return (
        thereAreUncompletedTagsInDiff(text) &&
        (item[1].split('<').length - 1 < item[1].split('>').length - 1 ||
          (item[1].indexOf('<') > -1 &&
            item[1].indexOf('>') < item[1].indexOf('<')))
      )
    }
    var i
    var indexTemp
    var adding = false
    var tagToMoveOpen = ''
    var tagToMoveClose = ''
    for (i = 0; i < array.length; i++) {
      var item = array[i]
      var thereAreUnclosedTags = thereAreUncompletedTagsInDiff(item[1])
      if (!adding && item[0] === 0) {
        if (thereAreUnclosedTags) {
          tagToMoveOpen = item[1].substr(
            item[1].lastIndexOf('<'),
            item[1].length + 1,
          )
          array[i][1] = item[1].substr(0, item[1].lastIndexOf('<'))
          indexTemp = i
          adding = true
        }
      } else if (adding && item[0] === 0) {
        if (thereAreUnclosedTags && thereAreCloseTags(item[1])) {
          tagToMoveClose = item[1].substr(0, item[1].indexOf('>') + 1)
          tagToMoveOpen = ''
          array[i][1] = item[1].substr(
            item[1].indexOf('>') + 1,
            item[1].length + 1,
          )
          i = indexTemp
        } else {
          if (thereAreUnclosedTags && thereAreOpenTags(item[1])) {
            i = i - 1 //There are more unclosed tags, restart from here
          }
          indexTemp = 0
          adding = false
          tagToMoveOpen = ''
          tagToMoveClose = ''
        }
      } else if (adding) {
        array[i][1] = tagToMoveOpen + item[1] + tagToMoveClose
      }
    }
    return array
  },
  replacePlaceholder: function (string) {
    return string
      .replace(config.lfPlaceholderRegex, 'softReturnMonad')
      .replace(config.crPlaceholderRegex, 'crPlaceholder')
      .replace(config.crlfPlaceholderRegex, 'brMarker')
      .replace(config.tabPlaceholderRegex, 'tabMarkerMonad')
      .replace(config.nbspPlaceholderRegex, 'nbspPlMark')
  },

  restorePlaceholders: function (string) {
    return string
      .replace(/softReturnMonad/g, config.lfPlaceholder)
      .replace(/crPlaceholder/g, config.crPlaceholder)
      .replace(/brMarker/g, config.crlfPlaceholder)
      .replace(/tabMarkerMonad/g, config.tabPlaceholder)
      .replace(/nbspPlMark/g, config.nbspPlaceholder)
  },
  htmlEncode: function (value) {
    if (value) {
      return $('<div />').text(value).html()
    } else {
      return ''
    }
  },
  htmlDecode: function (value) {
    if (value) {
      return $('<div />').html(value).text()
    } else {
      return ''
    }
  },

  escapeRegExp(str = '') {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') // $& means the whole matched string
  },

  execDiff: function (mainStr, cfrStr) {
    let _str = cfrStr
    // let _str = cfrStr.replace( config.lfPlaceholderRegex, "\n" )
    //     .replace( config.crPlaceholderRegex, "\r" )
    //     .replace( config.crlfPlaceholderRegex, "\r\n" )
    //     .replace( config.tabPlaceholderRegex, "\t" )
    //     .replace( config.nbspPlaceholderRegex, String.fromCharCode( parseInt( 0xA0, 10 ) ) );
    let _edit = mainStr.replace(String.fromCharCode(parseInt(0x21e5, 10)), '\t')

    //Prepend Unicode Character 'ZERO WIDTH SPACE' invisible, not printable, no spaced character,
    //used to detect initial and final spaces in html diff
    _str =
      String.fromCharCode(parseInt(0x200b, 10)) +
      _str +
      String.fromCharCode(parseInt(0x200b, 10))
    _edit =
      String.fromCharCode(parseInt(0x200b, 10)) +
      _edit +
      String.fromCharCode(parseInt(0x200b, 10))

    let diff_obj = this.diffMatchPatch.diff_main(_edit, _str)
    this.diffMatchPatch.diff_cleanupEfficiency(diff_obj)
    return diff_obj
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
      /(http|https):\/\/([\w_-]+(?:(?:\.[\w_-]+)+))([\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-])/gim
    return textToReplace.replace(regExpUrl, function (match) {
      let href =
        match[match.length - 1] === '.'
          ? match.substring(0, match.length - 1)
          : match
      return '<a href="' + href + '" target="_blank">' + match + '</a>'
    })
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
          callback(start, end)
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

          if (isPreviousBreakWord && isNextBreakWord) callback(start, end)
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
          callback(start, end)
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
  getDefaultCharsSize: (value) => value.length * 1,
  getUtf8CharsSize: (value) => new Blob([value]).size,
  getUft16CharsSize: (value) => value.length * 2,
  getCJKMatches: (value, getSize) => {
    const regex =
      /[\u4E00-\u9FCC\u3400-\u4DB5\u{20000}-\u{2A6D6}\u{2B820}-\u{2CEAF}\u{2CEB0}-\u{2EBEF}\u{2B740}-\u{2B81F}\u{2A700}-\u{2B73F}\u30A0-\u30FF\uF900-\uFaff\u{1B000}-\u{1B0FF}\u{1B100}-\u{1B12F}\u{1B130}-\u{1B16F}\uAC00-\uD7AF\uD7B0-\uD7FF\u3000-\u303F\u3040-\u309F]/gu
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      const char = match[0]
      result.push({
        match: char,
        index: match.index,
        length: char.length,
        size: getSize(char),
      })
    }

    return result
  },
  getArmenianMatches: (value, getSize) => {
    const regex = /[\u0530-\u058F]/g
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      const char = match[0]
      result.push({
        match: char,
        index: match.index,
        length: char.length,
        size: getSize(char),
      })
    }

    return result
  },
  getGeorgianMatches: (value, getSize) => {
    const regex = /[\u10A0-\u10FF\u1C90-\u1CBF\u2D00-\u2D2F]/g
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      const char = match[0]
      result.push({
        match: char,
        index: match.index,
        length: char.length,
        size: getSize(char),
      })
    }

    return result
  },
  getSinhalaMatches: (value, getSize) => {
    const regex = /[\u0D80-\u0DFF]/g
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      const char = match[0]
      result.push({
        match: char,
        index: match.index,
        length: char.length,
        size: getSize(char),
      })
    }

    return result
  },
  getEmojiMatches: (value, getSize) => {
    const regex =
      /(\u00a9|\u00ae|[\u2000-\u3300]|\ud83c[\ud000-\udfff]|\ud83d[\ud000-\udfff]|\ud83e[\ud000-\udfff])/g
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      const char = match[0]
      result.push({
        match: char,
        index: match.index,
        length: char.length,
        size: getSize(char),
      })
    }

    return result
  },
  getLatinCharsMatches: (value, getSize) => {
    const result = []

    for (var i = 0; i < value.length; i++) {
      const char = value[i]
      if (value.charCodeAt(i) <= 255) {
        result.push({
          match: char,
          index: i,
          length: char.length,
          size: getSize(char),
        })
      }
    }
    return result
  },
  getFullwidthVariantsMatches: (value, getSize) => {
    const regex = /[\uFF01-\uFF60]/g
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      const char = match[0]
      result.push({
        match: char,
        index: match.index,
        length: char.length,
        size: getSize(char),
      })
    }

    return result
  },
  /* specify how chars size should be count */
  charsSizeMapping: {
    default: (value) => TEXT_UTILS.getDefaultCharsSize(value),
    custom: [
      (value) => TEXT_UTILS.getCJKMatches(value, TEXT_UTILS.getUft16CharsSize),
      (value) =>
        TEXT_UTILS.getArmenianMatches(value, TEXT_UTILS.getUft16CharsSize),
      (value) =>
        TEXT_UTILS.getGeorgianMatches(value, TEXT_UTILS.getUft16CharsSize),
      (value) =>
        TEXT_UTILS.getSinhalaMatches(value, TEXT_UTILS.getUft16CharsSize),
      (value) =>
        TEXT_UTILS.getEmojiMatches(value, TEXT_UTILS.getUft16CharsSize),
      (value) =>
        TEXT_UTILS.getFullwidthVariantsMatches(
          value,
          TEXT_UTILS.getUft16CharsSize,
        ),
    ],
  },
  removeHiddenCharacters: (value) => value.replace(/\u2060/g, ''),
  stripUnderscore: (value) =>
    value.replace(/_[^_]/g, (match) => match[1].toUpperCase()),
}

export default TEXT_UTILS
