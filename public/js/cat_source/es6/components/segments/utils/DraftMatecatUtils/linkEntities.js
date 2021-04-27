import getEntities from './getEntities'
import {ContentState} from 'draft-js'
/**
 *
 * @param editorState
 * @returns {ContentState} - A ContentState in which each tag that is not self-closable, is linked to another tag
 */
const linkEntities = (editorState) => {
  let contentState = editorState.getCurrentContent()

  const openEntities = getEntities(editorState).filter((entityObj) => {
    if (
      entityObj.entity.data.hasOwnProperty('closeTagId') &&
      entityObj.entity.data.closeTagId
    ) {
      return entityObj
    }
  })
  const closeEntities = getEntities(editorState).filter((entityObj) => {
    if (
      entityObj.entity.data.hasOwnProperty('openTagId') &&
      entityObj.entity.data.openTagId
    ) {
      return entityObj
    }
  })
  openEntities.forEach((ent) => {
    const closure = closeEntities.filter((entObj) => {
      if (entObj.entity.data.openTagId === ent.entity.data.closeTagId) {
        return entObj
      }
    })
    if (closure.length > 0) {
      contentState = contentState.mergeEntityData(closure[0].entityKey, {
        openTagKey: ent.entityKey,
      })
      contentState = contentState.mergeEntityData(ent.entityKey, {
        closeTagKey: closure[0].entityKey,
      })
    }
  })
  return contentState
}

export default linkEntities
