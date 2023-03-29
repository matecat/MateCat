/**
 * Get suggestion for phrase by AI
 * @param {string} phrase
 * @returns {Promise<object>}
 */

export const aiAssistantSuggestion = async ({phrase}) => {
  const p = () => new Promise((resolve) => setTimeout(() => resolve(), 2000))
  await p()

  return {phrase}
}
