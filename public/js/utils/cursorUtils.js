// The index of the direct child of `container` that holds `node` (-1 when none does): a selection can end inside a
// tag chip, a mark or a lexiqa marking, any number of levels down (the label of a chip is in its own element).
const childIndexOf = (nodes, node, container) => {
  while (node && node.parentNode !== container) node = node.parentNode
  return nodes.indexOf(node)
}

const CursorUtils = {
  savedSel: null,
  savedSelActiveElement: null,

  getSelectionData(selection, container) {
    var data = {}
    var nodes = Array.from(container.childNodes) //array of nodes
    data.start_node = childIndexOf(nodes, selection.anchorNode, container)
    if (data.start_node === 0) {
      data.start_offset = selection.anchorOffset
    } else {
      data.start_offset = 0
      for (var i = 0; i < data.start_node; i++) {
        data.start_offset += nodes[i].textContent.length
      }
      data.start_offset += selection.anchorOffset
      data.start_node = 0
    }

    data.end_node = childIndexOf(nodes, selection.focusNode, container)
    if (data.end_node === 0) data.end_offset = selection.focusOffset
    else {
      data.end_offset = 0
      for (let i = 0; i < data.end_node; i++) {
        data.end_offset += nodes[i].textContent.length
      }
      data.end_offset += selection.focusOffset
      data.end_node = 0
    }
    data.selected_string = selection.toString()
    return data
  },
}

export default CursorUtils
