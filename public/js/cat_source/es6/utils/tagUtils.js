import _ from 'lodash'
import {Base64} from 'js-base64'

// import SegmentStore  from '../stores/SegmentStore';
import TextUtils from './textUtils'
import {
  tagSignatures,
  getXliffRegExpression,
} from '../components/segments/utils/DraftMatecatUtils/tagModel'

const TAGS_UTILS = {
  // TODO: move it in another module
  // We need to send to BE the reverse (more or less) of what we receive in the getSegments (WHY??)
  // EX: From BE
  prepareTextToSend: function (tx) {
    let brTx1 = `##LESSTHAN##$1##GREATERTHAN##`
    tx = tx
      .replace(/</g, '##LESSTHANLT##')
      .replace(/>/g, '##GREATERTHANGT##')
      .replace(/&lt;/gi, '<')
      .replace(/<((ph.*?)\s*?\/)&gt;/gi, brTx1) // <ph \/&gt;
      .replace(/<(g .*?\bid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((x|bx|ex|bpt|ept|it|mrk)\sid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((ph.*?)\sid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((ph.*?)\sid[^<“]*?\/)>/gi, brTx1)
      .replace(/<(\/(g|x|bx|ex))&gt;/gi, brTx1)
      .replace(/</gi, '&lt;')
    tx = tx.replace(/&/g, '&amp;').replace(/</gi, '&lt;').replace(/>/gi, '&gt;')
    tx = tx
      .replace(/##LESSTHANLT##/g, '&lt;')
      .replace(/##GREATERTHANGT##/g, '&gt;')
      .replace(/##LESSTHAN##/g, '<')
      .replace(/##GREATERTHAN##/g, '>')
    return tx
  },

  transformTextFromBe: (tx) => {
    let brTx1 = `##LESSTHAN##$1##GREATERTHAN##`
    tx = tx
      .replace(/&lt;/gi, '<')
      .replace(/<((ph.*?)\s*?\/)&gt;/gi, brTx1) // <ph \/&gt;
      .replace(/<(g .*?\bid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((x|bx|ex|bpt|ept|it|mrk)\sid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((ph.*?)\sid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((ph.*?)\sid[^<“]*?\/)>/gi, brTx1)
      .replace(/<(\/(g|x|bx|ex))&gt;/gi, brTx1)
      .replace(/</gi, '&lt;')
    tx = tx.replace(/&lt;/gi, '<').replace(/&gt;/gi, '>').replace(/&amp;/g, '&')
    tx = tx.replace(/##LESSTHAN##/g, '&lt;').replace(/##GREATERTHAN##/g, '&gt;')
    return tx
  },

  transformTextForEditor: (tx) => {
    let brTx1 = `##LESSTHAN##$1##GREATERTHAN##`
    tx = tx
      .replace(/</g, '##LESSTHAN##')
      .replace(/>/g, '##GREATERTHAN##')
      .replace(/&lt;/gi, '<')
      .replace(/<((ph.*?)\s*?\/)&gt;/gi, brTx1) // <ph \/&gt;
      .replace(/<(g .*?\bid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((x|bx|ex|bpt|ept|it|mrk)\sid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((ph.*?)\sid[^<“]*?)&gt;/gi, brTx1)
      .replace(/<((ph.*?)\sid[^<“]*?\/)>/gi, brTx1)
      .replace(/<(\/(g|x|bx|ex))&gt;/gi, brTx1)
      .replace(/</gi, '&lt;')
    tx = tx.replace(/&/g, '&amp;').replace(/</gi, '&lt;').replace(/>/gi, '&gt;')
    tx = tx.replace(/##LESSTHAN##/g, '&lt;').replace(/##GREATERTHAN##/g, '&gt;')
    return tx
  },

  transformPlaceholdersAndTagsNew: function (text) {
    text = this.decodePlaceholdersToTextSimple(text || '')
    if (!(config.tagLockCustomizable && !UI.tagLockEnabled)) {
      // matchTag transform <g id='1'> and  </g> in opening "1" and closing "1"
      text = this.matchTag(this.decodeHtmlInTag(text))
    }
    return text
  },

  // Replace old function decodePlaceholdersToText
  decodePlaceholdersToTextSimple: function (str) {
    return str
      .replace(
        config.crlfPlaceholderRegex,
        `<span class="tag small ${tagSignatures['lineFeed'].style}"> </span><br>`,
      )
      .replace(
        config.lfPlaceholderRegex,
        `<span class="tag small ${tagSignatures['lineFeed'].style}"> </span><br>`,
      )
      .replace(
        config.crPlaceholderRegex,
        `<span class="tag small ${tagSignatures['carriageReturn'].style}"> </span><br>`,
      )
      .replace(
        config.tabPlaceholderRegex,
        `<span class="tag small ${
          tagSignatures['tab'].style
        }" contenteditable="false">${this.encodeToHtml(
          tagSignatures['tab'].placeholder,
        )}</span>`,
      )
      .replace(
        config.nbspPlaceholderRegex,
        `<span class="tag small ${
          tagSignatures['nbsp'].style
        }" contenteditable="false">${this.encodeToHtml(
          tagSignatures['nbsp'].placeholder,
        )}</span>`,
      )
      .replace(
        tagSignatures['wordJoiner'].regex,
        `<span class="tag small ${
          tagSignatures['wordJoiner'].style
        }" contenteditable="false">${this.encodeToHtml(
          tagSignatures['wordJoiner'].placeholder,
        )}</span>`,
      )
  },

  encodeToHtml: (str) => {
    const buf = []
    for (let i = str.length - 1; i >= 0; i--) {
      buf.unshift(['&#', str[i].charCodeAt(), ';'].join(''))
    }
    return buf.join('')
  },

  // Same as decodePlaceholdersToTextSimple but transform placeholder to plain text
  decodePlaceholdersToPlainText: function (str) {
    return str
      .replace(config.lfPlaceholderRegex, tagSignatures['lineFeed'].placeholder)
      .replace(
        config.crPlaceholderRegex,
        tagSignatures['carriageReturn'].placeholder,
      )
      .replace(
        config.crlfPlaceholderRegex,
        `${tagSignatures['carriageReturn'].placeholder}${tagSignatures['lineFeed'].placeholder}`,
      )
      .replace(config.tabPlaceholderRegex, tagSignatures['tab'].placeholder)
      .replace(config.nbspPlaceholderRegex, tagSignatures['nbsp'].placeholder)
  },

  /*transformTextForLockTags: function ( tx ) {
        let brTx1 = "<_plh_ contenteditable=\"false\" class=\"locked style-tag \">$1</_plh_>";
        let brTx2 =  "<span contenteditable=\"false\" class=\"locked style-tag\">$1</span>";


        tx = tx.replace( /&amp;/gi, "&" )
            .replace( /<span/gi, "<_plh_" )
            .replace( /<\/span/gi, "</_plh_" )
            .replace( /&lt;/gi, "<" )
            .replace( /(<(ph.*?)\s*?\/&gt;)/gi, brTx1 )
            .replace( /(<(g|x|bx|ex|bpt|ept|ph.*?|it|mrk)\sid[^<“]*?&gt;)/gi, brTx1 )
            .replace( /(<(ph.*?)\sid[^<“]*?\/>)/gi, brTx1 )
            .replace( /</gi, "&lt;" )
            .replace( /\&lt;_plh_/gi, "<span" )
            .replace( /\&lt;\/_plh_/gi, "</span" )
            .replace( /\&lt;lxqwarning/gi, "<lxqwarning" )
            .replace( /\&lt;\/lxqwarning/gi, "</lxqwarning" )
            .replace( /\&lt;div\>/gi, "<div>" )
            .replace( /\&lt;\/div\>/gi, "</div>" )
            .replace( /\&lt;br\>/gi, "<br />" )
            .replace( /\&lt;br \/>/gi, "<br />" )
            .replace( /\&lt;mark /gi, "<mark " )
            .replace( /\&lt;\/mark/gi, "</mark" )
            .replace( /\&lt;ins /gi, "<ins " ) // For translation conflicts tab
            .replace( /\&lt;\/ins/gi, "</ins" ) // For translation conflicts tab
            .replace( /\&lt;del /gi, "<del " ) // For translation conflicts tab
            .replace( /\&lt;\/del/gi, "</del" ) // For translation conflicts tab
            .replace( /\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />' )
            .replace( /(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2 );

        tx = tx.replace( /(<span contenteditable="false" class="[^"]*"\>)(:?<span contenteditable="false" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>" );
        tx = tx.replace( /(<\/span\>)$(\s){0,}/gi, "</span> " );
        tx = this.transformTagsWithHtmlAttribute(tx);
        // tx = tx.replace( /(<\/span\>\s)$/gi, "</span><br class=\"end\">" );  // This to show the cursor after the last tag, moved to editarea component
        return tx;
    },*/

  // Replace old function transformTextForLockTags
  decodeHtmlInTag: function (tx, isRTL = false) {
    let brTx1 = `<_plh_ contenteditable="false" class="tag small ${
      isRTL ? 'tag-close' : 'tag-open'
    }">$1</_plh_>`
    let brTx2 = `<span contenteditable="false" class="tag small ${
      isRTL ? 'tag-open' : 'tag-close'
    }">$1</span>`
    let brTx3 =
      '<_plh_ contenteditable="false" class="tag small tag-selfclosed">$1</_plh_>'
    let brTxPlPh1 =
      '<_plh_ contenteditable="false" class="tag small tag-selfclosed tag-ph">$1</_plh_>'
    let brTxPlPh12 =
      '<span contenteditable="false" class="tag small tag-selfclosed tag-ph">$1</span>'
    tx = tx
      // .replace(/&amp;/gi, '&')
      .replace(/<span/gi, '<_plh_')
      .replace(/<\/span/gi, '</_plh_')
      .replace(/&lt;/gi, '<')
      .replace(/(<(ph.*?)\s*?\/&gt;)/gi, brTxPlPh1) // <ph \/&gt;
      .replace(/(<g .*?\bid[^<“]*?&gt;)/gi, brTx1)
      .replace(/(<(x|bx|ex|bpt|ept|it|mrk)\sid[^<“]*?&gt;)/gi, brTx3)
      .replace(/(<(ph.*?)\sid[^<“]*?&gt;)/gi, brTxPlPh1)
      .replace(/(<(ph.*?)\sid[^<“]*?\/>)/gi, brTxPlPh1)
      .replace(/</gi, '&lt;')
      .replace(/&lt;_plh_/gi, '<span')
      .replace(/&lt;\/_plh_/gi, '</span')
      .replace(/&lt;lxqwarning/gi, '<lxqwarning')
      .replace(/&lt;\/lxqwarning/gi, '</lxqwarning')
      .replace(/&lt;mark /gi, '<mark ')
      .replace(/&lt;\/mark/gi, '</mark')
      .replace(/&lt;ins /gi, '<ins ') // For translation conflicts tab
      .replace(/&lt;\/ins/gi, '</ins') // For translation conflicts tab
      .replace(/&lt;del /gi, '<del ') // For translation conflicts tab
      .replace(/&lt;\/del/gi, '</del') // For translation conflicts tab
      .replace(/&lt;br/gi, '<br')
      .replace(/(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|it|mrk)\s*&gt;)/gi, brTx2)
      .replace(/(&lt;\s*\/\s*(ph)\s*&gt;)/gi, brTxPlPh12)

    tx = tx.replace(
      /(<span contenteditable="false" class="[^"]*">)(:?<span contenteditable="false" class="[^"]*">)(.*?)(<\/span>){2}/gi,
      '$1$3</span>',
    )
    tx = tx.replace(/(<\/span>)$(\s){0,}/gi, '</span> ')
    tx = this.transformTagsWithHtmlAttributeGeneral(tx)
    return tx
  },

  // Replace old function transformTagsWithHtmlAttribute
  // Each tag is replaced with its own placeholder except for <g id=""> tags that will be passed to matchTag()
  // for open-close match
  transformTagsWithHtmlAttributeGeneral: function (tx) {
    let returnValue
    try {
      tx = tx.replace(/&quot;/gi, '"')
      for (let key in tagSignatures) {
        if (tagSignatures[key].selfClosing) {
          const {placeholderRegex, decodeNeeded} = tagSignatures[key]
          if (placeholderRegex) {
            let globalRegex = new RegExp(
              placeholderRegex.source,
              placeholderRegex.flags + 'gi',
            )
            tx = tx.replace(globalRegex, function (match, text) {
              return decodeNeeded ? Base64.decode(text) : text
            })
          }
        }
      }
      returnValue = tx
    } catch (e) {
      console.error(
        'Error parsing tag in transformTagsWithHtmlAttributeGeneral function',
      )
      returnValue = tx
    }
    return returnValue
  },

  // Associate tag of type g with integer id
  matchTag: function (tx) {
    let returnValue = tx
    const openRegex = tagSignatures['g'].regex
    const closeRegex = tagSignatures['gCl'].regex
    try {
      let openingMatchArr
      let openings = []
      while ((openingMatchArr = openRegex.exec(tx)) !== null) {
        const openingGTag = {}
        openingGTag.length = openingMatchArr[0].length
        openingGTag.id = openingMatchArr[1]
        openingGTag.offset = openingMatchArr.index
        openings.push(openingGTag)
      }

      let closingMatchArr
      let closings = []
      while ((closingMatchArr = closeRegex.exec(tx)) !== null) {
        const closingGTag = {}
        closingGTag.length = closingMatchArr[0].length
        closingGTag.offset = closingMatchArr.index
        closings.push(closingGTag)
      }

      openings.sort((a, b) => {
        return b.offset - a.offset
      })
      closings.sort((a, b) => {
        return a.offset - b.offset
      })

      closings.forEach((closingTag) => {
        let i = 0,
          notFound = true
        while (i < openings.length && notFound) {
          if (
            closingTag.offset > openings[i].offset &&
            !openings[i].closeTagId
          ) {
            notFound = !notFound
            openings[i].closeTagId = true
            // Closing tag has no ID, so take the one available inside open tag
            closingTag.id = openings[i].id
          }
          i++
        }
        // display every orphan closure as '?'
        if (notFound) closingTag.id = '?'
      })

      tx = tx.replace(openRegex, function () {
        return (
          String.fromCharCode(parseInt('200B', 16)) +
          openings.pop().id +
          String.fromCharCode(parseInt('200B', 16))
        )
      })

      tx = tx.replace(closeRegex, function () {
        return (
          String.fromCharCode(parseInt('200B', 16)) +
          closings.shift().id +
          String.fromCharCode(parseInt('200B', 16))
        )
      })
      returnValue = tx
    } catch (e) {
      console.error('Error matching tag g in TagUtils.matchTag function')
    }
    return returnValue
  },

  cleanTextFromTag: function (text) {
    let tagsMap = []
    // Save tags
    for (let key in tagSignatures) {
      if (tagSignatures[key].regex) {
        const {regex} = tagSignatures[key]
        text = text.replace(regex, function () {
          tagsMap.push({
            match: arguments[0],
            offset: arguments[arguments.length - 2],
          })
          return arguments[0]
        })
      }
    }
    // Clean text
    for (let key in tagSignatures) {
      if (tagSignatures[key].regex) {
        const {regex} = tagSignatures[key]
        text = text.replace(regex, '')
      }
    }
    return {tagsMap, text}
  },

  /**
   * Add at the end of the target the missing tags
   * TODO: Remove this fn
   */
  autoFillTagsInTarget: function (segmentObj) {
    const regx = getXliffRegExpression()
    let sourceTags = segmentObj.segment.match(regx)

    let newhtml = segmentObj.translation

    let targetTags = segmentObj.translation.match(regx)

    if (targetTags == null) {
      targetTags = []
    } else {
      targetTags = targetTags.map(function (elem) {
        return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '')
      })
    }

    let missingTags = sourceTags.map(function (elem) {
      return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '')
    })
    //remove from source tags all the tags in target segment
    for (let i = 0; i < targetTags.length; i++) {
      let pos = missingTags.indexOf(targetTags[i])
      if (pos > -1) {
        missingTags.splice(pos, 1)
      }
    }

    //add tags into the target segment
    for (let i = 0; i < missingTags.length; i++) {
      if (!(config.tagLockCustomizable && !UI.tagLockEnabled)) {
        newhtml = newhtml + missingTags[i]
      } else {
        newhtml = newhtml + missingTags[i]
      }
    }
    return newhtml
  },

  /**
   * Check if the data-original attribute in the source of the segment contains special tags (Ex: <g id=1></g>)
   * (Note that in the data-original attribute there are the &amp;lt instead of &lt)
   * @param segmentSource
   * @returns {boolean}
   */
  hasDataOriginalTags: function (segmentSource) {
    var originalText = segmentSource
    const reg = getXliffRegExpression()
    return !_.isUndefined(originalText) && reg.test(originalText)
  },

  /**
   * Remove all xliff source tags from the string
   * @param currentString :  string to parse
   * @returns the decoded String
   * TODO: Same of function cleanTextFromTag
   */
  removeAllTags: function (currentString) {
    if (currentString) {
      var regExp = getXliffRegExpression()
      currentString = currentString.replace(regExp, '')
      return currentString
    } else {
      return ''
    }
  },

  checkXliffTagsInText: function (text) {
    var reg = getXliffRegExpression()
    return reg.test(text)
  },

  /**
   * Not used
   * Old function used before execute diff on text
   * @param mainStr
   * @param transDecoded
   * @param replacementsMap
   * @returns {[*, *, *]}
   * @private
   */
  _treatTagsAsBlock: function (mainStr, transDecoded, replacementsMap) {
    var placeholderPhRegEx = /(&lt;ph id="mtc_.*?\/&gt;)/g
    var reverseMapElements = {}

    var listMainStr = mainStr.match(placeholderPhRegEx)

    if (listMainStr === null) {
      return [mainStr, transDecoded, replacementsMap]
    }

    /**
     * UI.execDiff works at character level, when a tag differs only for a part of it in the source/translation it breaks the tag
     * Ex:
     *
     * Those 2 tags differs only by their IDs
     *
     * Original string: &lt;ph id="mtc_1" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="/&gt;
     * New String:      &lt;ph id="mtc_2" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="/&gt;
     *
     * After the dom rendering of the TextUtils.dmp.diff_prettyHtml function
     *
     *  <span contenteditable="false" class="locked style-tag ">
     *      <span contenteditable="false" class="locked locked-inside tag-html-container-open">&lt;ph id="mtc_</span>
     *
     *      <!-- ###### the only diff is the ID of the tag ###### -->
     *      <del class="diff">1</del>
     *      <ins class="diff">2</ins>
     *      <!-- ###### the only diff is the ID of the tag ###### -->
     *
     *      <span>" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ==</span>
     *      <span contenteditable="false" class="locked locked-inside inside-attribute" data-original="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="></span>
     *  </span>
     *
     *  When this happens, the function TagUtils.transformTextForLockTags fails to find the PH tag by regexp and do not lock the tags or lock it in a wrong way
     *
     *  So, transform the string in a single character ( Private Use Unicode char ) for the diff function, place it in a map and reinsert in the diff_obj after the UI.execDiff executed
     *
     * //U+E000..U+F8FF, 6,400 Private-Use Characters Unicode, should be impossible to have those in source/target
     */
    var charCodePlaceholder = 57344

    listMainStr.forEach(function (element) {
      var actualCharCode = String.fromCharCode(charCodePlaceholder)

      /**
       * override because we already have an element in the map, so the content is the same
       * ( duplicated TAG, should be impossible but it's easy to cover the case ),
       * use such character
       */
      if (reverseMapElements[element]) {
        actualCharCode = reverseMapElements[element]
      }

      replacementsMap[actualCharCode] = element
      reverseMapElements[element] = actualCharCode // fill the reverse map with the current element ( override if equal )
      mainStr = mainStr.replace(element, actualCharCode)
      charCodePlaceholder++
    })

    var listTransDecoded = transDecoded.match(placeholderPhRegEx)
    if (listTransDecoded) {
      listTransDecoded.forEach(function (element) {
        var actualCharCode = String.fromCharCode(charCodePlaceholder)

        /**
         * override because we already have an element in the map, so the content is the same
         * ( tag is present in source and target )
         * use such character
         */
        if (reverseMapElements[element]) {
          actualCharCode = reverseMapElements[element]
        }

        replacementsMap[actualCharCode] = element
        reverseMapElements[element] = actualCharCode // fill the reverse map with the current element ( override if equal )
        transDecoded = transDecoded.replace(element, actualCharCode)
        charCodePlaceholder++
      })
    }
    return [mainStr, transDecoded, replacementsMap]
  },

  // Execute diff between two string also handling tags
  executeDiff: function (item1, item2) {
    // Remove Tags from Main String
    const {text: mainStr} = this.cleanTextFromTag(item1)
    // Remove Tags from Alternative String
    const {text: transDecoded, tagsMap: transDecodedTagsMap} =
      this.cleanTextFromTag(item2)
    // Execute diff
    let diffObj = TextUtils.execDiff(mainStr, transDecoded)
    // Restore Tags
    let totalLength = 0
    diffObj.forEach((diffItem) => {
      if (diffItem[0] <= 0) {
        let includedTags = []
        let newTotalLength = totalLength + diffItem[1].length
        let firstLoopTotalLength = newTotalLength
        // sort tags by offset because next check is executed consecutively
        transDecodedTagsMap.sort((a, b) => {
          return a.offset - b.offset
        })
        // get every tag included inside the original string slice
        transDecodedTagsMap.forEach((tag) => {
          // offset+1 is for prepended Unicode Character 'ZERO WIDTH SPACE'
          if (
            tag.offset + 1 <= firstLoopTotalLength &&
            tag.offset + 1 >= firstLoopTotalLength - diffItem[1].length
          ) {
            // add tag reference to work array
            includedTags.push(tag)
            // add tag's length (tag.offset is computed on the dirty string with all tags)
            firstLoopTotalLength += tag.match.length
          }
        })
        includedTags.forEach((includedTag) => {
          const relativeTagOffset =
            diffItem[1].length - (newTotalLength - (includedTag.offset + 1))
          const strBefore = diffItem[1].slice(0, relativeTagOffset)
          const strAfter = diffItem[1].slice(relativeTagOffset)
          // insert tag
          const newString = strBefore + includedTag.match + strAfter
          // update total parsed length of the temp string
          newTotalLength += includedTag.match.length
          // update item inside diff_obj
          diffItem[1] = newString
        })
        // update total parsed length of the complete string
        totalLength += diffItem[1].length
      }
    })

    return diffObj
  },
}
export default TAGS_UTILS
