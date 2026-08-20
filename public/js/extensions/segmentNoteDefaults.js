// What core renders in the segment notes tab.
//
// These were methods on the notes tab component, which is why extending them
// used to mean patching a prototype and reading `this.props`. They are plain
// functions of an explicit context now: everything an implementation needs
// arrives as an argument, so an extension does not need to know it is standing
// in for a React method.

import React from 'react'
import TEXT_UTILS from '../utils/textUtils'
import {extend} from './extensionPoints'
import {
  SEGMENT_METADATA_NOTES,
  SEGMENT_NOTE,
  SEGMENT_NOTE_CONTENT,
} from './extensionPointNames'

// The size restriction has its own place in the editor, so it is never a note.
export const filterMetadataKeys = (metadata = []) =>
  metadata.filter(({meta_key}) => meta_key !== 'sizeRestriction')

// Note text, as either a string or a list of nodes with the links turned into
// anchors.
export const getNoteContent = (note) =>
  TEXT_UTILS.getContentWithAllowedLinkRedirect(note).length > 1
    ? TEXT_UTILS.getContentWithAllowedLinkRedirect(note).map(
        (content, index) =>
          typeof content === 'object' && content.isLink ? (
            <a key={index} href={content.link} target="_blank" rel="noreferrer">
              {content.link}
            </a>
          ) : (
            content
          ),
      )
    : note

// One entry of the notes array. Returns null for an entry core does not show.
export const getNote = ({item, index}) => {
  if (item.note && item.note !== '') {
    const note = item.note
    const prefix = 'translation_context|¶|'
    if (note.startsWith(prefix)) {
      return null
    }
    const noteContent = extend(SEGMENT_NOTE_CONTENT)(note)
    return typeof noteContent === 'string' ? (
      <div className="note" key={'note-' + index}>
        <span className="note-label">Note: </span>
        <span
          dangerouslySetInnerHTML={{
            __html: noteContent,
          }}
        />
      </div>
    ) : (
      <div className="note" key={'note-' + index}>
        <span className="note-label">Note: </span>
        <span>{noteContent}</span>
      </div>
    )
  }

  // An entry whose json is an object has never rendered: the loop that built
  // the markup returned it from the callback rather than collecting it, so the
  // method fell through to null. Kept as null rather than quietly starting to
  // render something this refactor was not asked to add.
  if (
    item.json &&
    typeof item.json === 'object' &&
    Object.keys(item.json).length > 0
  ) {
    return null
  }

  if (typeof item.json === 'string') {
    return (
      <div key={'note-json' + index} className="note">
        {item.json}
      </div>
    )
  }

  return null
}

// The block that shows the segment's metadata as notes. `metadata` arrives
// already filtered, so an implementation never repeats that decision.
export const getMetadataNotes = ({metadata = []}) => (
  <div className="metadata-notes" key="metadata-notes">
    {metadata.map(({meta_key: label, meta_value: body}, index) => (
      <div className="note" key={`meta-${index}`}>
        <span className="note-label">{label}: </span>
        <span>{body}</span>
      </div>
    ))}
  </div>
)

// Everything the notes tab shows, in order: the notes themselves, the
// information context groups, then the metadata block.
export const getNotes = ({notes, contextGroups, metadata, segment}) => {
  const notesHtml = []

  if (notes) {
    notes.forEach((item, index) => {
      const noteHtml = extend(SEGMENT_NOTE)({item, index})
      if (noteHtml) {
        notesHtml.push(noteHtml)
      }
    })
  }

  if (contextGroups?.context_json) {
    contextGroups.context_json.forEach((contextGroup, index) => {
      if (
        contextGroup.attr?.purpose === 'information' &&
        contextGroup.contexts.length > 0
      ) {
        notesHtml.push(
          <div className="context-group" key={'context-group' + index}>
            <span className="context-group-name">Context: </span>
            {contextGroup.contexts.map((context, i) => (
              <span key={'context-item' + i} className="context-item-name">
                {i > 0 ? ' ;' : ''}
                {context['raw-content']}
              </span>
            ))}
          </div>,
        )
      }
    })
  }

  if (metadata?.length > 0) {
    notesHtml.push(extend(SEGMENT_METADATA_NOTES)({metadata, segment}))
  }

  return notesHtml
}
