const insertFragment = (editorState, fragment) => {
  let newContent = Modifier.replaceWithFragment(
    editorState.getCurrentContent(),
    editorState.getSelection(),
    fragment,
  )
  return EditorState.push(editorState, newContent, 'insert-fragment')
}

export default insertFragment
