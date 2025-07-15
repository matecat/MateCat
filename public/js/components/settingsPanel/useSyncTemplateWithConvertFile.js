import {useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {executeOnce} from '../../utils/commonUtils'

function useSyncTemplateWithConvertFile({
  currentTemplate,
  setTemplates,
  defaultTemplate,
  idTemplate,
  getTemplates,
  checkIfUpdate,
  isCattool = config.is_cattool,
}) {
  const retrieveOnce = useRef(executeOnce())

  // retrieve templates
  useEffect(() => {
    if (!isCattool) {
      retrieveOnce.current(() =>
        getTemplates().then((templates) => {
          // sort by name
          templates.items.sort((a, b) => (a.name > b.name ? 1 : -1))
          const items = [defaultTemplate, ...templates.items]
          const selectedTemplateId =
            items.find(({id}) => id === idTemplate)?.id ?? 0

          setTemplates(
            items.map((template) => ({
              ...template,
              isSelected: template.id === selectedTemplateId,
            })),
          )
        }),
      )
    }
  }, [getTemplates, setTemplates, idTemplate, defaultTemplate, isCattool])

  // Select template when curren project template change
  useEffect(() => {
    if (!isCattool) {
      setTemplates((prevState) => {
        const selectedTemplateId =
          prevState.find(({id}) => id === idTemplate)?.id ?? 0

        return prevState.map((template) => ({
          ...template,
          isSelected: template.id === selectedTemplateId,
        }))
      })
    }
  }, [idTemplate, setTemplates, isCattool])

  // check when current template change
  useEffect(() => {
    if (!isCattool && currentTemplate && !currentTemplate.isTemporary) {
      /* eslint-disable no-unused-vars */
      const {
        name,
        uid,
        isTemporary,
        isSelected,
        created_at,
        modified_at,
        ...filteredTemplate
      } = currentTemplate
      /* eslint-enable no-unused-vars */

      checkIfUpdate(filteredTemplate)
    }
  }, [currentTemplate, checkIfUpdate, isCattool])
}

useSyncTemplateWithConvertFile.propTypes = {
  idTemplate: PropTypes.number,
  currentTemplate: PropTypes.object,
  defaultTemplate: PropTypes.object,
  getTemplates: PropTypes.func,
  setTemplates: PropTypes.func,
  checkIfUpdate: PropTypes.func,
}

export default useSyncTemplateWithConvertFile
