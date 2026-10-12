import {ContentState} from 'draft-js'
import tagFromEntity from './tagFromEntity'

test('builds a TagStruct from a draft-js entity instance', () => {
  let contentState = ContentState.createFromText('hello world')
  contentState = contentState.createEntity('g', 'IMMUTABLE', {
    id: '1',
    name: 'g',
    originalOffset: 3,
    openTagId: 'open-1',
    closeTagId: 'close-1',
    encodedText: '<g id="1">',
    placeholder: '<g id="1">',
    decodedText: '<g id="1">',
  })
  const entityKey = contentState.getLastCreatedEntityKey()
  const entityInstance = contentState.getEntity(entityKey)

  const tag = tagFromEntity({entity: entityInstance})

  expect(tag.offset).toBe(3)
  expect(tag.length).toBe('<g id="1">'.length)
  expect(tag.type).toBe('g')
  expect(tag.data.id).toBe('1')
  expect(tag.data.name).toBe('g')
  expect(tag.data.openTagId).toBe('open-1')
  expect(tag.data.closeTagId).toBe('close-1')
  expect(tag.data.encodedText).toBe('<g id="1">')
  expect(tag.data.placeholder).toBe('<g id="1">')
  expect(tag.data.decodedText).toBe('<g id="1">')
})
