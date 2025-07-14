import {each} from 'lodash'
import LexiqaHighlight from '../../LexiqaHighlight/LexiqaHighlight.component'
import * as DraftMatecatConstants from './editorConstants'
import canDecorateRange from './canDecorateRange'

const activateLexiqa = (
  editorState,
  lexiqaWarnings,
  sid,
  isSource,
  getUpdatedSegmentInfo,
) => {
  const generateLexiqaDecorator = (warnings, sid, isSource, decoratorName) => {
    return {
      name: DraftMatecatConstants.LEXIQA_DECORATOR,
      strategy: (contentBlock, callback, contentState) => {
        each(warnings, (warn) => {
          if (warn.blockKey === contentBlock.getKey()) {
            const canDecorate = canDecorateRange(
              warn.start,
              warn.end,
              contentBlock,
              contentState,
              decoratorName,
            )
            if (canDecorate) callback(warn.start, warn.end)
          }
        })
      },
      component: LexiqaHighlight,
      props: {
        warnings,
        sid,
        isSource,
        getUpdatedSegmentInfo,
      },
    }
  }

  // Remove focus on source to avoid cursor jumping at beginning of target
  /*if(isSource){
        editorState = EditorState.acceptSelection(editorState, editorState.getSelection().merge({
            hasFocus: false
        }));
    }*/
  return generateLexiqaDecorator(
    lexiqaWarnings,
    sid,
    isSource,
    DraftMatecatConstants.LEXIQA_DECORATOR,
  )
}

export default activateLexiqa
