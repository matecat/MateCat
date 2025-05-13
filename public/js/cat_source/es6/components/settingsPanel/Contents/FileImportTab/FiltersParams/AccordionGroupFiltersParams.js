import React, {useState} from 'react'
import {Accordion} from '../../../../common/Accordion/Accordion'
import {Json} from './Json'
import {Xml} from './Xml'
import {Yaml} from './Yaml'
import {MsWord} from './MsWord'
import {MsPowerpoint} from './MsPowerpoint'
import {MsExcel} from './MsExcel'
import {Dita} from './Dita'

const ACCORDION_GROUP = {
  json: 'JSON',
  xml: 'XML',
  yaml: 'YAML',
  msWord: 'MS Word',
  msExcel: 'MS Excel',
  msPowerpoint: 'MS PowerPoint',
  dita: 'DITA/DITAMAP',
}

export const AccordionGroupFiltersParams = () => {
  const [currentSection, setCurrentSection] = useState()

  const handleAccordion = (id) =>
    setCurrentSection((prevState) => (prevState !== id ? id : undefined))

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
      <Dita />
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
