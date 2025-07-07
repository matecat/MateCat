const CursorUtils = {
  savedSel: null,
  savedSelActiveElement: null,

  getSelectionData(selection, container) {
    var data = {}
    data.start_node = $.inArray(selection.anchorNode, container.contents())
    if (data.start_node < 0) {
      //this means that the selection is probably ending inside a lexiqa tag,
      //or matecat tag/marking
      data.start_node = $.inArray(
        $(selection.anchorNode).parent()[0],
        container.contents(),
      )
    }
    var nodes = container.contents() //array of nodes
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

    data.end_node = $.inArray(selection.focusNode, container.contents())
    if (data.end_node < 0) {
      //this means that the selection is probably ending inside a lexiqa tag,
      //or matecat tag/marking
      data.end_node = $.inArray(
        $(selection.focusNode).parent()[0],
        container.contents(),
      )
    }
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
