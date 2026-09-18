import React from 'react'
import TEXT_UTILS from '../../utils/textUtils'

/**
 * Note rendering for the segment footer's Messages tab.
 *
 * Plugins customise these by assigning over the members of this object
 * (`segmentNotes.getNotes = ...`) instead of patching a component prototype,
 * which stops working the moment the host becomes a function component.
 *
 * Every call between these members therefore goes back through `segmentNotes`.
 * A direct call to a sibling would capture the core implementation and silently
 * bypass whatever a plugin assigned.
 */
const segmentNotes = {
  // Assigned by plugins that hide notes matching a pattern. Undefined in core.
  excludeMatchingNotesRegExp: undefined,

  getFilteredMetadataKeys({metadata}) {
    return metadata.filter(({meta_key}) => meta_key !== 'sizeRestriction')
  },

  getNoteContentStructure(note) {
    return TEXT_UTILS.getContentWithAllowedLinkRedirect(note).length > 1
      ? TEXT_UTILS.getContentWithAllowedLinkRedirect(note).map(
          (content, index) =>
            typeof content === 'object' && content.isLink ? (
              <a key={index} href={content.link} target="_blank">
                {content.link}
              </a>
            ) : (
              content
            ),
        )
      : note
  },

  getNoteStructure(item, index) {
    if (item.note && item.note !== '') {
      if (
        segmentNotes.excludeMatchingNotesRegExp &&
        segmentNotes.excludeMatchingNotesRegExp.test(item.note)
      ) {
        return
      }
      let note = item.note
      const prefix = 'translation_context|¶|'
      if (note.startsWith(prefix)) {
        return null
      }
      const noteStructure = segmentNotes.getNoteContentStructure(note)
      let html =
        typeof noteStructure === 'string' ? (
          <div className="note" key={'note-' + index}>
            <span className="note-label">Note: </span>
            <span
              dangerouslySetInnerHTML={{
                __html: noteStructure,
              }}
            />
          </div>
        ) : (
          <div className="note" key={'note-' + index}>
            <span className="note-label">Note: </span>
            <span>{noteStructure}</span>
          </div>
        )
      return html
    } else if (
      item.json &&
      typeof item.json === 'object' &&
      Object.keys(item.json).length > 0
    ) {
      Object.keys(item.json).forEach(function (key, index) {
        let html = (
          <div className="note" key={'note-json' + index}>
            <span className="note-label">
              {key.charAt(0).toUpperCase() + key.slice(1)}:{' '}
            </span>
            <span> {item.json[key]} </span>
          </div>
        )
        return html
      })
    } else if (typeof item.json === 'string') {
      let text = item.json
      let html = (
        <div key={'note-json' + index} className="note">
          {text}
        </div>
      )
      return html
    }
    return null
  },

  getMetadataNoteTemplate({metadata}) {
    const filtered =
      typeof segmentNotes.getFilteredMetadataKeys === 'function'
        ? segmentNotes.getFilteredMetadataKeys({metadata})
        : metadata
    let metadataNotes = []
    for (const [index, item] of filtered.entries()) {
      const {meta_key, meta_value: body} = item
      const label = meta_key

      metadataNotes.push(
        <div className="note" key={`meta-${index}`}>
          <span className="note-label">{label}: </span>
          <span>{body}</span>
        </div>,
      )
    }
    return (
      <div className="metadata-notes" key="metadata-notes">
        {metadataNotes}
      </div>
    )
  },

  getNotes({notes, contextGroups, metadata}) {
    let notesHtml = []
    if (notes) {
      notes.forEach((item, index) => {
        const noteHtml = segmentNotes.getNoteStructure(item, index)
        if (noteHtml) {
          notesHtml.push(noteHtml)
        }
      })
    }
    if (contextGroups && contextGroups.context_json) {
      contextGroups.context_json.forEach((contextGroup, index) => {
        if (
          contextGroup.attr?.purpose &&
          contextGroup.attr.purpose === 'information' &&
          contextGroup.contexts.length > 0
        ) {
          const contextElems = contextGroup.contexts.map((context, i) => (
            <span key={'context-item' + i} className="context-item-name">
              {i > 0 ? ' ;' : ''}
              {context['raw-content']}
            </span>
          ))
          notesHtml.push(
            <div className="context-group" key={'context-group' + index}>
              <span className="context-group-name">Context: </span>
              {contextElems}
            </div>,
          )
        }
      })
    }

    // metadata notes
    const filtered =
      typeof segmentNotes.getFilteredMetadataKeys === 'function'
        ? segmentNotes.getFilteredMetadataKeys({metadata})
        : metadata.filter((item) => item.meta_key !== 'sizeRestriction')
    if (filtered?.length > 0) {
      notesHtml.push(segmentNotes.getMetadataNoteTemplate({metadata}))
    }
    return notesHtml
  },
}

export default segmentNotes
