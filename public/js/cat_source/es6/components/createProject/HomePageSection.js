import React from 'react'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
export const HomePageSection = () => {
  return (
    <section className="home-page-section">
      <div className={'layout-container'}>
        <h1>Why choose us</h1>
        <div className={'layout-grid'}>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-aiDriven.svg" />
              <h3>AI-driven precision and speed</h3>
            </div>
            <p>
              Matecat leverages cutting-edge AI, including Large Language
              Models, to provide top-quality matches, simplify the process of
              finding word definitions, ensure consistent terminology, and carry
              out locale-specific quality checks. Deliver high-quality more
              quickly thanks to advanced technology.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-goFaster.svg" />
              <h3>Save time with adaptive Machine Translation</h3>
            </div>
            <p>
              Go faster with top-quality matches in 200+ languages. Adaptive MT
              is a smart assistant that adapts to your translation memories and
              uses every correction to improve quality. Every time you confirm a
              segment, Matecat gets better at providing you with accurate
              translations for the upcoming ones, saving time.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-freeToUse.svg" />
              <h3>Free to use</h3>
            </div>
            <p>
              Matecat delivers a no-cost translation solution that’s accessible
              to all, including companies, freelancers, language service
              providers, and anyone else who needs it. Get unrestricted access
              to its features, with no limitations on users or projects. It’s
              translation made easy – and good for your budget, too!
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-cloud.svg" />
              <h3>Keep your data secure</h3>
            </div>
            <p>
              We guarantee secure cloud storage with advanced encryption and
              built-in redundancy. Your data is stored safely and backed up in
              multiple locations for extra security. With Matecat, you can focus
              on your translations, safe in the knowledge that your data is
              always protected yet accessible.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-liveSupport.svg" />
              <h3>A user-friendly experience</h3>
            </div>
            <p>
              Matecat was created with a focus on you. It's designed to be
              user-friendly and easy to handle. No matter your level of
              experience, Matecat lets you dive straight in and get to work
              without any hassle. There's no need to worry about complicated
              instructions or steep learning curves.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-collaboration.svg" />
              <h3>Work from anywhere</h3>
            </div>
            <p>
              Matecat is entirely web-based, which means you can access it from
              any computer with an internet connection. This gives you the
              flexibility to work from anywhere and means you don’t need to
              worry about installation, updates, or compatibility issues.
              Matecat is updated constantly and works with any browser.
            </p>
          </div>
        </div>
      </div>
      <div className="layout-bottom">
        <h3>Want to know more?</h3>
        <span>Check all benefits Matecat has to offer!</span>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.MEDIUM}
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
