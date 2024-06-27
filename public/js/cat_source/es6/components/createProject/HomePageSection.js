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
              <h3>AI-Driven Precision and Speed</h3>
            </div>
            <p>
              Matecat leverages cutting-edge AI, including Large Language Models
              (LLMs), to provide top-quality matches, simplify the process of
              finding word meanings, ensure terminology consistency and conduct
              locale-specific quality checks. The integration of advanced
              technology empowers you to deliver high-quality translations more
              quickly and efficiently.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-goFaster.svg" />
              <h3>Go faster with Adaptive Machine Translation</h3>
            </div>
            <p>
              Matecat makes it very easy for you and your team to work together
              on translation projects. It's like having a shared workspace where
              you can leave comments and tag people, just like chatting and
              editing documents in real-time. Plus, you can split urgent
              projects among multiple translators without interfering with each
              other. Translation teamwork has never been so effortless!
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-freeToUse.svg" />
              <h3>Free to use</h3>
            </div>
            <p>
              Matecat is all about you, the user. It's designed to be simple and
              easy to use right from the start. Whether you're an expert or a
              beginner, Matecat is user-friendly and hassle-free. Dive right in
              and start working with no complicated instructions or steep
              learning curves. Enjoy an experience that puts you first and helps
              you complete tasks quickly and easily.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-cloud.svg" />
              <h3>Secure Cloud Storage</h3>
            </div>
            <p>
              Matecat offers top-quality Machine Translation matches in over 200
              languages, and it gets smarter as you use it. It checks your past
              translations, adjusts based on your work, and even considers the
              whole document's context. It's like having a clever assistant who
              learns from you, saving you loads of time.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-liveSupport.svg" />
              <h3>Live support</h3>
            </div>
            <p>
              Matecat is your free, user-friendly, and budget-friendly
              translation solution for everyone. Whether you're a company, a
              freelancer, or in need of translation services, Matecat is
              designed for you. No limits on users or projects, making it
              perfect for big projects and collaborations. It's the easy and
              affordable way to streamline your translations, ensure
              consistency, and save time and money.
            </p>
          </div>
          <div className="content-box">
            <div className="content-box_header">
              <img src="/public/img/icons/home/home-collaboration.svg" />
              <h3>Built for collaboration</h3>
            </div>
            <p>
              Matecat takes your data security seriously. We use advanced
              encryption and have multiple backups in different places to keep
              your data safe. Focus on your translations with confidence,
              knowing your data is secure and always accessible.
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
