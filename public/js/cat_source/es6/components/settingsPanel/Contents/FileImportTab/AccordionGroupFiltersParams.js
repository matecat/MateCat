import React, {useState} from 'react'
import {Accordion} from '../../../common/Accordion/Accordion'
import {Json} from './FiltersParamsForms/Json'
import {Xml} from './FiltersParamsForms/Xml'
import {Yaml} from './FiltersParamsForms/Yaml'
import {MsWord} from './FiltersParamsForms/MsWord'
import {MsPowerpoint} from './FiltersParamsForms/MsPowerpoint'
import {MsExcel} from './FiltersParamsForms/MsExcel'

const ACCORDION_GROUP = {
  json: 'Json',
  xml: 'XML',
  yaml: 'YAML',
  msWord: 'MS Word',
  msExcel: 'MS Excel',
  msPowerpoint: 'MS Powerpoint',
}

export const AccordionGroupFiltersParams = () => {
  const [currentSection, setCurrentSection] = useState(
    Object.keys(ACCORDION_GROUP)[0],
  )

  const handleAccordion = (id) => setCurrentSection(id)

  const getSection = (section) =>
    section === ACCORDION_GROUP.json ? (
      <Json />
    ) : ACCORDION_GROUP.xml === section ? (
      <Xml />
    ) : ACCORDION_GROUP.yaml === section ? (
      <Yaml />
    ) : ACCORDION_GROUP.msWord === section ? (
      <MsWord />
    ) : ACCORDION_GROUP.msExcel === section ? (
      <MsExcel />
    ) : ACCORDION_GROUP.msPowerpoint === section ? (
      <MsPowerpoint />
    ) : (
      <span>we</span>
    )

  return (
    <div className="filters-params-accordion-group">
      {Object.entries(ACCORDION_GROUP).map(([id, section]) => (
        <Accordion
          key={id}
          id={id}
          title={section}
          expanded={currentSection === id}
          onShow={handleAccordion}
          className="filters-params-accordion"
        >
          {getSection(section)}
        </Accordion>
      ))}
    </div>
  )
}
