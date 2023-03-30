/**
 * Get suggestion for phrase by AI
 * @param {string} phrase
 * @returns {Promise<object>}
 */

export const aiAssistantSuggestion = async ({phrase}) => {
  const p = () => new Promise((resolve) => setTimeout(() => resolve(), 2000))
  await p()

  return {
    phrase:
      "In this context, a database refers to a structured collection of data, in this case specifically related to posts. The instruction is to display the content from every post that is stored in the database on a webpage. The database may contain various fields of information related to each post, such as the post's title, author, date, content, and any associated media or links. By displaying the content from every post in the database on the webpage, all of the posts will be accessible to users who visit the page.",
  }
}
