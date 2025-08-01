import {isUndefined} from 'lodash'
import $ from 'jquery'
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
}

export default TEXT_UTILS
