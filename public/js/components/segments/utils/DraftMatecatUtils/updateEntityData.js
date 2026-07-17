import {Modifier, SelectionState, EditorState} from 'draft-js'

import getEntities from './getEntities'

const updateEntityData = (
  editorState,
  tagRange,
  lastSelection,
  entities = null,
) => {
  let contentState = editorState.getCurrentContent()
  if (!contentState.hasText()) return editorState

  const inlineStyle = editorState.getCurrentInlineStyle()

  // Se le passiamo usiamo quelle, altrimenti le ricalcoliamo
  let entitiesInEditor = entities ? entities : getEntities(editorState)
  entitiesInEditor.sort((a, b) => {
    return b.start - a.start
  })
  tagRange.sort((a, b) => {
    return b.offset - a.offset
  })

  entitiesInEditor.forEach((entity, index) => {
    contentState = contentState.replaceEntityData(
      entity.entityKey,
      tagRange[index].data,
    )

    const selectionState = new SelectionState({
      anchorKey: entity.blockKey,
      anchorOffset: entity.start,
      focusKey: entity.blockKey,
      focusOffset: entity.end,
    })

    // Replace text of entity with placeholder
    contentState = Modifier.replaceText(
      contentState,
      selectionState,
      tagRange[index].data.placeholder,
      inlineStyle,
      entity.entityKey,
    )

    editorState = EditorState.set(editorState, {
      currentContent: contentState,
    })
    console.log('Entità aggiornata -->', tagRange[index].data.placeholder)
  })

  editorState = EditorState.push(editorState, contentState, 'remove-range')
  // Ripristina l'ultima selezione nota
  editorState = EditorState.forceSelection(editorState, lastSelection)

  return editorState
}

export default updateEntityData
