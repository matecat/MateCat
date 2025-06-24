import React from 'react'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
export const HomePageSection = () => {
  return (
    <section className="home-page-section">
      <div className={'layout-container'}>
        <h1>Why Choose Us</h1>
        <div className={'layout-grid'}>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/img/icons/home/home-aiDriven.svg" />
              <h3>AI-Driven Precision and Speed</h3>
            </div>
            <p>
              Matecat employs cutting-edge AI to help translators save time and
              deliver their best quality. Consistent terminology is guaranteed
              by matching words in all their forms, ensuring that translators
              never miss glossary terms. Large Language Models help translators
              with unfamiliar terms by providing context-specific definitions.
              Locale-specific checks on punctuation, numbers, dates and other
              conventions make sure that translators can work without worrying
              about accidental slip-ups.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/img/icons/home/home-goFaster.svg" />
              <h3>Save time with adaptive Machine Translation</h3>
            </div>
            <p>
              Get top-quality machine translation matches in 200+ languages with
              Matecat's free adaptive machine translation provided by ModernMT.
              It's like a smart assistant that gets better as you go, saving you
              tons of time. For each segment, it checks previous translations to
              find similar sentences and then adjusts the result based on your
              past work. What's more, it learns from your translations on the
              fly and takes into account the context of the whole document to
              make sure it uses the correct style and terminology.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/img/icons/home/home-freeToUse.svg" />
              <h3>Free to use</h3>
            </div>
            <p>
              Matecat offers a powerful solution that's completely free.
              Companies, language service providers, freelancers, anybody can
              make the most of Matecat. It provides unlimited access to all its
              features, with no restrictions on the number of users or projects,
              opening up a world of possibilities for large projects and
              collaborations. With Matecat, you can streamline your process,
              ensure consistency across projects and save both time and money.
              It's a comprehensive and efficient solution for all your needs.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/img/icons/home/home-cloud.svg" />
              <h3>Keep your data secure</h3>
            </div>
            <p>
              Matecat prioritizes the security of your data. We provide secure
              cloud storage with advanced encryption to protect your data from
              unauthorized access and have built-in redundancy, meaning that
              your data is not just stored in one place, but backed up in
              multiple locations. This provides an extra layer of security,
              ensuring your data is safeguarded against potential loss. With
              Matecat, you can focus on your translations, safe in the knowledge
              that your data is not just secure but always accessible as well.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/img/icons/home/home-liveSupport.svg" />
              <h3>A User-friendly Experience</h3>
            </div>
            <p>
              Matecat was created with a focus on you. It's designed so that
              anybody can use it: whether you're a seasoned professional or a
              beginner, Matecat is built to be easy to understand and navigate,
              so you can start using it right away. It lets you and your team
              dive straight in and get to work without having to spend time
              studying tutorials or guides. With Matecat, there's no need to
              worry about complicated instructions or steep learning curves, so
              you can focus on completing tasks quickly and easily.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/img/icons/home/home-work.svg" />
              <h3>Work from anywhere</h3>
            </div>
            <p>
              Matecat is entirely web-based, so you can access it from any
              computer with an internet connection. This gives you the
              flexibility to work from anywhere and means you don't need to
              worry about installation, software updates, or compatibility
              issues. Matecat is updated constantly and works perfectly with any
              browser, while your data is stored securely in the cloud and is
              accessible from anywhere.
            </p>
          </div>
        </div>
      </div>
      <div className="layout-bottom">
        <h3>Want to know more?</h3>
        <span>Check all benefits Matecat has to offer!</span>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.BIG}
          onClick={() =>
            window.open('https://site.matecat.com/benefits', '_blank')
          }
        >
          Benefits
        </Button>
      </div>
    </section>
  )
}
