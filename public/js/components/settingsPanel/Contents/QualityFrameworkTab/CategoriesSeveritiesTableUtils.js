export const getCategoryLabelAndDescription = (category) => {
  const [line1, line2] = category.label.split('(')
  const label =
    line1.slice(-1) === ' ' ? line1.substring(0, line1.length - 1) : line1
  const description = line2 && line2.replace(/[()]/g, '')

  return {label, description}
}
export const formatCategoryDescription = (description) =>
  `${description[0] !== '(' ? '(' : ''}${description}${description[description.length - 1] !== ')' ? ')' : ''}`
export const getCodeFromLabel = (label) => label.substring(0, 3).toUpperCase()
