import {render, screen, waitFor, fireEvent, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import SegmentFooter from './SegmentFooter'
window.React = React

window.config = {
  mt_enabled: true,
  id_job: '6',
  segmentFilterEnabled: true,
  translation_matches_enabled: 1,
  source_rfc: 'en-US',
  target_rfc: 'it-IT',
  tag_projection_languages:
    '{"en-de":"English - German","en-es":"English - Spanish","en-fr":"English - French","en-it":"English - Italian","en-pt":"English - Portuguese","en-ru":"English - Russian","en-cs":"English - Czech","en-nl":"English - Dutch","en-fi":"English - Finnish","en-pl":"English - Polish","en-da":"English - Danish","en-sv":"English - Swedish","en-el":"English - Greek","en-hu":"English - Hungarian","en-lt":"English - Lithuanian","en-ja":"English - Japanese","en-et":"English - Estonian","en-sk":"English - Slovak","en-bg":"English - Bulgarian","en-bs":"English - Bosnian","en-ar":"English - Arabic","en-ca":"English - Catalan","en-zh":"English - Chinese","en-he":"English - Hebrew","en-hr":"English - Croatian","en-id":"English - Indonesian","en-is":"English - Icelandic","en-ko":"English - Korean","en-lv":"English - Latvian","en-mk":"English - Macedonian","en-ms":"English - Malay","en-mt":"English - Maltese","en-nb":"English - Norwegian Bokmål","en-nn":"English - Norwegian Nynorsk","en-ro":"English - Romanian","en-sl":"English - Slovenian","en-sq":"English - Albanian","en-sr":"English - Montenegrin","en-th":"English - Thai","en-tr":"English - Turkish","en-uk":"English - Ukrainian","en-vi":"English - Vietnamese","de-it":"German - Italian","de-fr":"German - French","de-cs":"German - Czech","fr-it":"French - Italian","fr-nl":"French - Dutch","it-es":"Italian - Spanish","da-sv":"Danish - Swedish","nl-pt":"Dutch - Portuguese","nl-fi":"Dutch - Finnish","zh-en":"Chinese - English","sv-da":"Swedish - Danish","cs-de":"Czech - German"}',
}
window.classnames = () => {}

require('../../../ui.core')
require('../../../ui.segment')
UI.start = () => {}
UI.checkCrossLanguageSettings = () => {}

const props = {
  sid: '608',
  segment: JSON.parse(
    '{"original_sid":"608","lxqDecodedTranslation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2] ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","id_file":"6","notes":null,"readonly":"false","original_translation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]##$_A0$##ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","contributions":{"matches":[{"target_note":"","memory_key":"","prop":[],"last_updated_by":"MT!","match":"MT","ICE":false,"reference":"Machine Translation provided by Google, Microsoft, Worldlingo or MyMemory customized engine.","subject":false,"created_by":"MT","usage_count":1,"create_date":"2022-01-19 16:43:07","target":"it-IT","translation":" La loro musica è stata variamente descritta come &lt;g id=\\"8\\"&gt;hard rock&lt;/g&gt;, &lt;g id=\\"9\\"&gt;rock blues&lt;/g&gt;, e &lt;g id=\\"10\\"&gt;metallo pesante&lt;/g&gt;,&lt;g id=\\"11\\"&gt;[2]&lt;/g&gt;##$_A0$##ma la band stessa lo chiama semplicemente &quot;&lt;g id=\\"12\\"&gt;rock and roll&lt;/g&gt;&quot;.&lt;g id=\\"13\\"&gt;[3]&lt;/g&gt;","segment":"##$_A0$##Their music has been variously described as##$_A0$##&lt;g id=\\"8\\"&gt;hard rock&lt;/g&gt;,##$_A0$##&lt;g id=\\"9\\"&gt;blues rock&lt;/g&gt;, and##$_A0$##&lt;g id=\\"10\\"&gt;heavy metal&lt;/g&gt;,&lt;g id=\\"11\\"&gt;[2]&lt;/g&gt;##$_A0$##but the band themselves call it simply \\"&lt;g id=\\"12\\"&gt;rock and roll&lt;/g&gt;\\".&lt;g id=\\"13\\"&gt;[3]&lt;/g&gt;","source_note":"","tm_properties":null,"raw_translation":" La loro musica è stata variamente descritta come <g id=\\"8\\">hard rock</g>, <g id=\\"9\\">rock blues</g>, e <g id=\\"10\\">metallo pesante</g>,<g id=\\"11\\">[2]</g> ma la band stessa lo chiama semplicemente &quot;<g id=\\"12\\">rock and roll</g>&quot;.<g id=\\"13\\">[3]</g>","source":"en-US","id":0,"last_update_date":"2022-01-19","raw_segment":" Their music has been variously described as <g id=\\"8\\">hard rock</g>, <g id=\\"9\\">blues rock</g>, and <g id=\\"10\\">heavy metal</g>,<g id=\\"11\\">[2]</g> but the band themselves call it simply \\"<g id=\\"12\\">rock and roll</g>\\".<g id=\\"13\\">[3]</g>","quality":70}]},"unlocked":false,"propagable":false,"openIssues":false,"context_groups":null,"jid":"6","currentInSearch":false,"edit_area_locked":false,"filename":"ACDC.docx","tagMismatch":{},"opened":true,"modified":false,"parsed_time_to_edit":["00","00","00",96],"originalDecodedTranslation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]°ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","tagged":false,"lxqDecodedSource":" Their music has been variously described as hard rock, blues rock, and heavy metal,[2] but the band themselves call it simply \\"rock and roll\\".[3]","revision_number":null,"target_chunk_lengths":{"len":[0],"statuses":["DRAFT"]},"cl_contributions":{"matches":[],"errors":[]},"inSearch":false,"sid":"608","searchParams":{},"occurrencesInSearch":null,"metadata":[],"repetitions_in_chunk":"1","version_number":"1","openSplit":false,"translation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]##$_A0$##ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","decodedSource":"°Their music has been variously described as°hard rock,°blues rock, and°heavy metal,[2]°but the band themselves call it simply \\"rock and roll\\".[3]","status":"DRAFT","targetTagMap":[{"offset":94,"length":9,"type":"nbsp","mutability":"IMMUTABLE","data":{"id":"","name":"nbsp","encodedText":"##$_A0$##","decodedText":"°","openTagId":null,"closeTagId":null,"openTagKey":null,"closeTagKey":null,"placeholder":"°","originalOffset":-1}}],"segment":"##$_A0$##Their music has been variously described as##$_A0$##&lt;g id=\\"8\\"&gt;hard rock&lt;/g&gt;,##$_A0$##&lt;g id=\\"9\\"&gt;blues rock&lt;/g&gt;, and##$_A0$##&lt;g id=\\"10\\"&gt;heavy metal&lt;/g&gt;,&lt;g id=\\"11\\"&gt;[2]&lt;/g&gt;##$_A0$##but the band themselves call it simply \\"&lt;g id=\\"12\\"&gt;rock and roll&lt;/g&gt;\\".&lt;g id=\\"13\\"&gt;[3]&lt;/g&gt;","missingTagsInTarget":[],"updatedSource":"##$_A0$##Their music has been variously described as##$_A0$##hard rock,##$_A0$##blues rock, and##$_A0$##heavy metal,[2]##$_A0$##but the band themselves call it simply \\"rock and roll\\".[3]","warnings":{},"source_chunk_lengths":[],"splitted":false,"lexiqa":{"target":{"urls":[],"mspolicheck":[],"numbers":[],"spaces":[],"specialchardetect":[],"punctuation":[],"spelling":[{"insource":false,"msg":"and","start":143,"errorid":"matecat-6-2b14d6279fd8_608_143_146_d1g_t","color":"#563d7c","length":3,"module":"d1g","suggestions":["ad","rand","band"],"ignored":false,"end":146,"category":"spelling"}],"blacklist":[],"glossary":[]}},"segment_hash":"f4d5a08434b1909daa43b85eb9574701","data_ref_map":null,"decodedTranslation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]°ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","selected":false,"versions":[{"diff":null,"created_at":"2022-01-13 10:57:29","propagated_from":0,"id_segment":608,"version_number":1,"translation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]##$_A0$##ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","id_job":6,"issues":[],"id":0},{"diff":null,"created_at":"2022-01-13 09:57:29","propagated_from":0,"id_segment":608,"version_number":0,"translation":"La loro musica è stata variamente descritta come&lt;g id=\\"8\\"&gt; hard rock&lt;/g&gt; ,&lt;g id=\\"9\\"&gt; rock blues&lt;/g&gt; , e&lt;g id=\\"10\\"&gt; metallo pesante&lt;/g&gt; ,&lt;g id=\\"11\\"&gt; [2]&lt;/g&gt; ma la band stessa lo chiama semplicemente &quot;&lt;g id=\\"12\\"&gt; rock and roll&lt;/g&gt; &quot;.&lt;g id=\\"13\\"&gt; [3]&lt;/g&gt;","id_job":6,"issues":[],"id":11}],"time_to_edit":"96","warning":"0","sourceTagMap":[],"glossary":[],"openComments":false,"ice_locked":"0","autopropagated_from":"0"}',
  ),
}

test('Rendering elements', () => {
  UI.registerFooterTabs()
  render(<SegmentFooter {...props} />)

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toHaveClass('hide')
})

test('Add tab', () => {
  UI.crossLanguageSettings = {primary: 'it-IT'}
  UI.registerFooterTabs()
  render(<SegmentFooter {...props} />)

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.getByTestId('multiMatches')).toBeInTheDocument()
})

test('Remove tab', () => {
  UI.crossLanguageSettings = undefined
  UI.registerFooterTabs()
  render(<SegmentFooter {...props} />)

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.queryByTestId('multiMatches')).toBeNull()
})

test('Translation Matches count result', () => {
  UI.registerFooterTabs()
  render(<SegmentFooter {...props} />)

  expect(screen.getByText('(1)')).toBeInTheDocument()
})

test('Translation conflicts (alternatives)', () => {
  UI.registerFooterTabs()
  const modifiedProps = {
    ...props,
    segment: {
      ...props.segment,
      alternatives: JSON.parse(
        `{"editable":[{"translation":"L'expérience elle-même doit donner aux clients un accès privilégié à des lieux ou à des choses qu'ils ne pourraient pas trouver par eux-mêmes. test","TOT":"1","involved_id":["11450636"]}],"not_editable":[],"prop_available":1}`,
      ),
    },
  }
  render(<SegmentFooter {...modifiedProps} />)

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toHaveClass('active')
})

test('Click tab', async () => {
  UI.registerFooterTabs()
  render(<SegmentFooter {...props} />)

  userEvent.click(screen.getByTestId('concordances'))
  await waitFor(() => {
    expect(screen.getByTestId('concordances')).toHaveClass('active')
  })
})

test('Move to next tab with keyboard shortcut', async () => {
  UI.registerFooterTabs()
  render(<SegmentFooter {...props} />)

  fireEvent.keyDown(document, {altKey: true, code: 'KeyS', keyCode: 83})
  fireEvent.keyDown(document, {altKey: true, code: 'KeyS', keyCode: 83})

  await waitFor(() => {
    expect(screen.getByTestId('glossary')).toHaveClass('active')
  })

  fireEvent.keyDown(document, {altKey: true, code: 'KeyS', keyCode: 83})

  await waitFor(() => {
    expect(screen.getByTestId('matches')).toHaveClass('active')
  })
})
