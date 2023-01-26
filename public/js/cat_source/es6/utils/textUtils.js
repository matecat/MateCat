import _ from 'lodash'
import {Base64} from 'js-base64'
import {regexWordDelimiter} from '../components/segments/utils/DraftMatecatUtils/textUtils'
import CommonUtils from './commonUtils'

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
    var phTagsObject = {}
    var diff
    source = source.replace(
      /&lt;(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?&gt;/gi,
      function (match) {
        var id = Math.floor(Math.random() * 10000)
        if (_.isUndefined(phTagsObject[match])) {
          phTagsObject[match] = {
            id,
            match,
          }
        } else {
          id = phTagsObject[match].id
        }
        return '<' + id + '>'
      },
    )

    target = target.replace(
      /&lt;(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?&gt;/gi,
      function (match) {
        var id = Math.floor(Math.random() * 10000000)
        if (_.isUndefined(phTagsObject[match])) {
          phTagsObject[match] = {
            id,
            match,
          }
        } else {
          id = phTagsObject[match].id
        }
        return '<' + id + '>'
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
    var self = this
    $.each(diff, function (index, text) {
      text[1] = text[1].replace(/<(.*?)>/gi, function (match, id) {
        try {
          var tag = _.find(phTagsObject, function (item) {
            return item.id === parseInt(id)
          })
          if (!_.isUndefined(tag)) {
            return tag.match
          }
          return match
        } catch (e) {
          return match
        }
      })
      var rootElem
      var newElem
      if (self.htmlDecode(text[1]) === ' ') {
        text[1] = '&nbsp;'
      }

      if (text[0] === -1) {
        rootElem = $(document.createElement('div'))
        newElem = $.parseHTML('<span class="deleted"/>')
        $(newElem).text(self.htmlDecode(text[1]))
        rootElem.append(newElem)
        diffTxt += $(rootElem).html()
      } else if (text[0] === 1) {
        rootElem = $(document.createElement('div'))
        newElem = $.parseHTML('<span class="added"/>')
        $(newElem).text(self.htmlDecode(text[1]))
        rootElem.append(newElem)
        diffTxt += $(rootElem).html()
      } else {
        diffTxt += text[1]
      }
    })

    return this.restorePlaceholders(diffTxt)
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
    var thereAreIncompletedTagsInDiff = function (text) {
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
        thereAreIncompletedTagsInDiff(text) &&
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
        thereAreIncompletedTagsInDiff(text) &&
        (item[1].split('<').length - 1 < item[1].split('>').length - 1 ||
          (item[1].indexOf('<') > -1 &&
            item[1].indexOf('>') > item[1].indexOf('<')))
      )
    }
    var i
    var indexTemp
    var adding = false
    var tagToMoveOpen = ''
    var tagToMoveClose = ''
    for (i = 0; i < array.length; i++) {
      var item = array[i]
      var thereAreUnclosedTags = thereAreIncompletedTagsInDiff(item[1])
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

  escapeRegExp(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') // $& means the whole matched string
  },

  ///*************************************************************************
  // test jsfiddle http://jsfiddle.net/YgKDu/
  placehold_xliff_tags(segment) {
    let LTPLACEHOLDER = '##LESSTHAN##'
    let GTPLACEHOLDER = '##GREATERTHAN##'
    segment = segment.replace(/&lt;/gi, LTPLACEHOLDER)
    segment = segment.replace(/&gt;/gi, GTPLACEHOLDER)
    return segment
  },
  view2rawxliff(segment) {
    // return segment+"____";
    // input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
    // output : <g id="43"> bang &amp; olufsen are &gt; 555 </g> <x/>

    // caso controverso <g id="4" x="&lt; dfsd &gt;">
    //segment=htmlDecode(segment);
    segment = this.placehold_xliff_tags(segment)
    segment = this.htmlEncode(segment)
    segment = this.restore_xliff_tags(segment)

    return segment
  },

  restore_xliff_tags(segment) {
    let LTPLACEHOLDER = '##LESSTHAN##'
    let GTPLACEHOLDER = '##GREATERTHAN##'
    let re_lt = new RegExp(LTPLACEHOLDER, 'g')
    let re_gt = new RegExp(GTPLACEHOLDER, 'g')
    segment = segment.replace(re_lt, '<')
    segment = segment.replace(re_gt, '>')
    return segment
  },
  ///*************************************************************************

  cleanupHTMLCharsForDiff(string) {
    return this.replacePlaceholder(string.replace(/&nbsp;/g, ''))
  },

  trackChangesHTML(source, target) {
    /*
        There are problems when you delete or add a tag next to another, the algorithm that makes the diff fails to recognize the tags,
        they come out of the function broken.
        Before passing them to the function that makes the diff we replace all the tags with placeholders and we keep a map of the tags
        indexed with the id of the tags.
         */
    var phTagsObject = {}
    var diff
    source = source.replace(
      /&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk).*?id="(.*?)".*?\/&gt;/gi,
      function (match, group1, group2) {
        if (_.isUndefined(phTagsObject[group2])) {
          phTagsObject[group2] = match
        }
        return '<' + Base64.encode(group2) + '> '
      },
    )

    target = target.replace(
      /&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk).*?id="(.*?)".*?\/&gt;/gi,
      function (match, gruop1, group2) {
        if (_.isUndefined(phTagsObject[group2])) {
          phTagsObject[group2] = match
        }
        return '<' + Base64.encode(group2) + '> '
      },
    )

    diff = this.diffMatchPatch.diff_main(
      this.cleanupHTMLCharsForDiff(source),
      this.cleanupHTMLCharsForDiff(target),
    )

    this.diffMatchPatch.diff_cleanupSemantic(diff)

    /*
        Before adding spans to identify added or subtracted portions we need to check and fix broken tags
         */
    diff = this.setUnclosedTagsInDiff(diff)
    var diffTxt = ''

    $.each(diff, function (index, text) {
      text[1] = text[1].replace(/<(.*?)>/gi, function (match, text) {
        try {
          var decodedText = Base64.decode(text)
          if (!_.isUndefined(phTagsObject[decodedText])) {
            return phTagsObject[decodedText]
          }
          return match
        } catch (e) {
          return match
        }
      })
      var rootElem
      var newElem
      if (this[0] === -1) {
        rootElem = $(document.createElement('div'))
        newElem = $.parseHTML('<span class="deleted"/>')
        $(newElem).text(TEXT_UTILS.htmlDecode(text[1]))
        rootElem.append(newElem)
        diffTxt += $(rootElem).html()
      } else if (text[0] === 1) {
        rootElem = $(document.createElement('div'))
        newElem = $.parseHTML('<span class="added"/>')
        $(newElem).text(TEXT_UTILS.htmlDecode(text[1]))
        rootElem.append(newElem)
        diffTxt += $(rootElem).html()
      } else {
        diffTxt += text[1]
      }
    })

    return this.restorePlaceholders(diffTxt)
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
    if (window.getSelection().isCollapsed) return false
    return selContainer.hasClass('area') || selContainer.hasClass('source')
  },

  //Change with TagUtils.decodePlaceholdersToPlainText
  clenaupTextFromPleaceholders: function (text) {
    text = text
      .replace(config.crPlaceholderRegex, '\r')
      .replace(config.lfPlaceholderRegex, '\n')
      .replace(config.crlfPlaceholderRegex, '\r\n')
      .replace(config.tabPlaceholderRegex, '\t')
      .replace(
        config.nbspPlaceholderRegex,
        String.fromCharCode(parseInt(0xa0, 10)),
      )
    return text
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
  getCJKMatches: (value) => {
    const regex =
      /[\u3041-\u3096\u30A0-\u30FF\u3400-\u4DB5\u4E00-\u9FCB\uF900-\uFA6A\u2E80-\u2FD5\uFF5F-\uFF9F\u3000-\u303F\u31F0-\u31FF\u3220-\u3243\u3280-\u337F-\uFF01-\uFF5E-\u3130-\u318F\uAC00-\uD7AF]/g
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      result.push({match: match[0], index: match.index})
    }

    return result
  },
  getEmojiMatches: (value) => {
    const regex =
      /(\u00a9|\u00ae|[\u2000-\u3300]|\ud83c[\ud000-\udfff]|\ud83d[\ud000-\udfff]|\ud83e[\ud000-\udfff])/g
    let match
    const result = []

    while ((match = regex.exec(value)) !== null) {
      result.push({match: match[0], index: match.index})
    }

    return result
  },
  removeHiddenCharacters: (value) => value.replace(/\u2060/g, ''),
}

export default TEXT_UTILS
